<?php

namespace Tests\Feature;

use App\Http\Middleware\PreventAllCaching;
use App\Http\Middleware\PreventBackHistory;
use App\Http\Middleware\SetCacheHeaders;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

/**
 * Regression: PreventBackHistory and PreventAllCaching (global, so web AND
 * API) and SetCacheHeaders (web group) called ->header(), which exists
 * only on Illuminate's Response - BinaryFileResponse/StreamedResponse do
 * not have it. Every route serving a file (response()->file(),
 * Storage::download()) therefore returned 500. Latent until Society Rules
 * & Documents added the first such route; found by its own tests.
 */
class CacheHeadersFileResponseTest extends TestCase
{
    public static function middleware(): array
    {
        return [
            'SetCacheHeaders'    => [SetCacheHeaders::class],
            'PreventAllCaching'  => [PreventAllCaching::class],
            'PreventBackHistory' => [PreventBackHistory::class],
        ];
    }

    /** @dataProvider middleware */
    public function test_binary_file_response_passes_through_with_no_cache_headers(string $class): void
    {
        $path = tempnam(sys_get_temp_dir(), 'sf');
        file_put_contents($path, '%PDF-1.4 test');

        $response = (new $class())->handle(Request::create('/x'), fn () => new BinaryFileResponse($path));

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        @unlink($path);
    }

    /** @dataProvider middleware */
    public function test_streamed_response_passes_through_with_no_cache_headers(string $class): void
    {
        $response = (new $class())->handle(Request::create('/x'), fn () => new StreamedResponse(fn () => print 'x'));

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }
}
