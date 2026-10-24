<?php

namespace Tests\Feature;

use App\Models\Owner;
use App\Models\VillaArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The app also runs from a sub-folder (http://localhost/SocietyFlow/public/...). A URL written into a page as
 * '/api/owners/villa-areas' then points at the web server's root, outside the app, and 404s - which is how the owner
 * form's Villa Area / Villa No. pickers came up empty, and saving that form wiped the owner's villa. Pages build app
 * URLs with url() / route() instead.
 */
class SubfolderInstallUrlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_page_script_or_link_hard_codes_a_path_from_the_web_server_root(): void
    {
        // the first segment of every app route ('api', 'owners', 'guard', ...): a quoted '/<segment>/...' is a root-relative app URL
        $segments = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => explode('/', ltrim($route->uri(), '/'))[0])
            ->filter(fn ($segment) => $segment !== '' && !str_contains($segment, '{'))
            ->unique()
            ->map(fn ($segment) => preg_quote($segment, '#'))
            ->implode('|');
        $pattern = '#[\'"`]/(' . $segments . ')(?=[/?\'"`$])#';

        $offenders = [];
        $views = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS));
        foreach ($views as $file) {
            if (!str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            foreach (file($file->getPathname()) as $i => $line) {
                if (!preg_match_all($pattern, $line, $matches, PREG_OFFSET_CAPTURE)) {
                    continue;
                }
                foreach ($matches[0] as [$text, $offset]) {
                    // url('/x'), route(...) and asset('/x') already resolve against the app's own root
                    if (preg_match('#(url|asset|secure_url|redirect|to)\(\s*$#', substr($line, 0, $offset))) {
                        continue;
                    }
                    $offenders[] = str_replace(resource_path('views') . DIRECTORY_SEPARATOR, '', $file->getPathname()) . ':' . ($i + 1) . '  ' . trim($line);
                }
            }
        }

        $this->assertSame([], $offenders, "Root-relative app URLs break a sub-folder install - use {{ url('/') }} or route():\n" . implode("\n", $offenders));
    }

    public function test_the_owner_form_looks_its_villas_up_inside_the_sub_folder(): void
    {
        $society = $this->makeSociety();
        $society->update(['property_type' => 'villa']);
        $admin = $this->makeUserWithRole($society, 'Admin');
        $area = VillaArea::create(['society_id' => $society->id, 'name' => 'Area 1', 'status' => 'active']);
        $owner = Owner::create(['society_id' => $society->id, 'name' => 'Villa Owner', 'email' => 'villa.owner@test.local', 'phone' => '9000000160',
            'status' => 'active', 'property_type' => 'villa', 'villa_area_id' => $area->id, 'villa_no' => '160']);

        // the request as Apache hands it over for http://localhost/SocietyFlow/public/owners/{id}/edit
        $html = $this->actingAs($admin)
            ->withServerVariables(['SCRIPT_NAME' => '/SocietyFlow/public/index.php', 'PHP_SELF' => '/SocietyFlow/public/index.php', 'SCRIPT_FILENAME' => public_path('index.php')])
            ->get('http://localhost/SocietyFlow/public' . route('owners.edit', $owner, false))
            ->assertOk()->getContent();

        $this->assertStringContainsString('http://localhost/SocietyFlow/public/api/owners/villa-areas', $html);
        $this->assertStringContainsString('http://localhost/SocietyFlow/public/api/owners/villa-numbers', $html);
        $this->assertStringNotContainsString('fetch(`/api/', $html);
    }
}
