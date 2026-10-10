<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * layouts/app.blade.php renders session('success') / session('error') for
 * every page. Nine views extending it rendered the same flash again, so
 * every save on Gates, Shifts, Expenses, Communication Center etc. showed
 * its message twice. Found by screenshot during the Society Rules journey.
 */
class FlashMessageDuplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_view_extending_the_app_layout_renders_its_own_flash(): void
    {
        $offenders = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));

        foreach ($it as $file) {
            if (!str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $src = file_get_contents($file->getPathname());
            if (str_contains($src, "@extends('layouts.app')")
                && preg_match("/session\(\s*'(success|error)'\s*\)/", $src)) {
                $offenders[] = str_replace(resource_path('views') . DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }

        $this->assertSame([], $offenders, 'These views duplicate the flash message the app layout already shows.');
    }

    public function test_a_saved_gate_shows_its_success_message_once(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');

        $html = $this->actingAs($admin)->followingRedirects()->post(route('admin.gates.store'), [
            'name' => 'Main Gate', 'type' => 'main',
        ])->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'Gate added.'));
    }
}
