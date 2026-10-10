<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Found in the phone-width browser pass: the "Add Payment Detail" dialog (and four siblings) were a fixed
 * `w-96` (384px) card, 24px wider than a 360px phone, so its right edge and buttons were clipped.
 * A fixed `w-96` is only acceptable when the same class list also caps it to the screen (`max-w-...`).
 */
class ModalWidthContractTest extends TestCase
{
    public function test_no_dialog_card_is_a_bare_fixed_384px_width(): void
    {
        $offenders = [];
        foreach (File::allFiles(resource_path('views')) as $file) {
            if (!str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            foreach (preg_split('/\R/', $file->getContents()) as $i => $line) {
                if (preg_match('/class="[^"]*(?<![:\w-])w-96(?![\w-])[^"]*"/', $line, $m) && !str_contains($m[0], 'max-w-')) {
                    $offenders[] = str_replace(resource_path('views') . DIRECTORY_SEPARATOR, '', $file->getPathname()) . ':' . ($i + 1);
                }
            }
        }

        $this->assertSame([], $offenders, "fixed w-96 without a max-w cap overflows a 360px phone:\n" . implode("\n", $offenders));
    }
}
