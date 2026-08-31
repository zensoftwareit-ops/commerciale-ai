<?php

namespace Tests\Unit;

use App\Services\Organizations\WebsiteContentReader;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class WebsiteContentReaderTest extends TestCase
{
    public function test_it_reads_the_home_page_and_useful_same_site_pages(): void
    {
        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/servizi')) {
                return Http::response('<html><title>Servizi</title><body>Siti web e consulenza digitale per PMI.</body></html>', 200, ['Content-Type' => 'text/html']);
            }

            return Http::response('<html><title>Azienda</title><body>Studio digitale.<a href="/servizi">I servizi</a><a href="https://other.example/about">Esterno</a></body></html>', 200, ['Content-Type' => 'text/html']);
        });
        $reader = new class extends WebsiteContentReader
        {
            protected function resolvePublicIps(string $host): array
            {
                return ['93.184.216.34'];
            }
        };

        $result = $reader->read('https://example.com');

        $this->assertCount(2, $result['pages']);
        $this->assertSame('Azienda', $result['pages'][0]['title']);
        $this->assertSame('https://example.com/servizi', $result['pages'][1]['url']);
        $this->assertStringContainsString('consulenza digitale', $result['pages'][1]['text']);
    }

    public function test_it_rejects_private_network_addresses(): void
    {
        Http::fake();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('locali o riservati');

        app(WebsiteContentReader::class)->read('http://127.0.0.1');
    }
}
