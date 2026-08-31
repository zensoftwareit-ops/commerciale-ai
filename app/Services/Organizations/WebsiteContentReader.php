<?php

namespace App\Services\Organizations;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WebsiteContentReader
{
    private const MAX_PAGES = 4;

    private const MAX_PAGE_BYTES = 1_500_000;

    private const MAX_PAGE_TEXT = 12_000;

    private const MAX_TOTAL_TEXT = 40_000;

    /** @return array{url: string, pages: array<int, array{url: string, title: string, text: string}>} */
    public function read(string $url): array
    {
        $startUrl = $this->validateUrl($url);
        $originHost = $this->host($startUrl);
        $queue = [$startUrl];
        $visited = [];
        $pages = [];
        $totalCharacters = 0;

        while ($queue !== [] && count($pages) < self::MAX_PAGES && $totalCharacters < self::MAX_TOTAL_TEXT) {
            $currentUrl = array_shift($queue);
            $visitKey = $this->visitKey($currentUrl);
            if (isset($visited[$visitKey])) {
                continue;
            }
            $visited[$visitKey] = true;

            try {
                $result = $this->fetch($currentUrl, $originHost);
            } catch (\Throwable $exception) {
                if ($pages === []) {
                    throw $exception;
                }

                continue;
            }
            $text = mb_substr($this->extractText($result['body']), 0, min(self::MAX_PAGE_TEXT, self::MAX_TOTAL_TEXT - $totalCharacters));
            if ($text === '') {
                continue;
            }

            $pages[] = [
                'url' => $result['url'],
                'title' => $this->extractTitle($result['body']),
                'text' => $text,
            ];
            $totalCharacters += mb_strlen($text);

            if (count($pages) === 1) {
                $queue = array_values(array_unique(array_merge($queue, $this->discoverUsefulLinks($result['body'], $result['url'], $originHost))));
            }
        }

        if ($pages === []) {
            throw new RuntimeException('Il sito non ha restituito contenuti testuali pubblici utilizzabili.');
        }

        return ['url' => $startUrl, 'pages' => $pages];
    }

    /** @return array{url: string, body: string} */
    private function fetch(string $url, string $originHost): array
    {
        for ($redirects = 0; $redirects <= 3; $redirects++) {
            $url = $this->validateUrl($url);
            if (! $this->sameWebsite($originHost, $this->host($url))) {
                throw new RuntimeException('Il sito ha reindirizzato verso un dominio differente.');
            }

            $parts = parse_url($url);
            $host = $this->host($url);
            $port = (int) ($parts['port'] ?? (($parts['scheme'] ?? '') === 'https' ? 443 : 80));
            $ips = $this->resolvePublicIps($host);
            $options = ['allow_redirects' => false, 'connect_timeout' => 5, 'stream' => true];
            if (defined('CURLOPT_RESOLVE')) {
                $selectedIp = collect($ips)->first(fn (string $ip): bool => ! str_contains($ip, ':')) ?? $ips[0];
                $ip = str_contains($selectedIp, ':') ? '['.$selectedIp.']' : $selectedIp;
                $options['curl'] = [CURLOPT_RESOLVE => [$host.':'.$port.':'.$ip]];
            }

            $response = Http::accept('text/html, text/plain;q=0.9')
                ->withUserAgent('Daria-Setup-Wizard/1.0')
                ->withOptions($options)
                ->timeout(12)
                ->get($url);

            if ($response->status() >= 300 && $response->status() < 400) {
                $location = $response->header('Location');
                if (! is_string($location) || trim($location) === '') {
                    throw new RuntimeException('Il sito ha restituito un reindirizzamento non valido.');
                }
                $url = (string) UriResolver::resolve(new Uri($url), new Uri(trim($location)));

                continue;
            }

            $this->assertUsableResponse($response);
            $body = $response->toPsrResponse()->getBody()->read(self::MAX_PAGE_BYTES + 1);
            if (strlen($body) > self::MAX_PAGE_BYTES) {
                throw new RuntimeException('Una pagina del sito supera la dimensione massima consentita.');
            }

            return ['url' => $url, 'body' => $body];
        }

        throw new RuntimeException('Il sito ha effettuato troppi reindirizzamenti.');
    }

    private function assertUsableResponse(Response $response): void
    {
        if (! $response->successful()) {
            throw new RuntimeException('Il sito non e accessibile (HTTP '.$response->status().').');
        }
        $contentLength = (int) $response->header('Content-Length');
        if ($contentLength > self::MAX_PAGE_BYTES) {
            throw new RuntimeException('Una pagina del sito supera la dimensione massima consentita.');
        }
        $contentType = strtolower((string) $response->header('Content-Type'));
        if ($contentType !== '' && ! str_contains($contentType, 'text/html') && ! str_contains($contentType, 'text/plain')) {
            throw new RuntimeException('Il sito non ha restituito una pagina HTML o testuale.');
        }
    }

    private function validateUrl(string $url): string
    {
        $url = trim($url);
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Inserisci un URL aziendale completo e valido.');
        }
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('Sono consentiti soltanto URL pubblici HTTP o HTTPS senza credenziali.');
        }
        $port = $parts['port'] ?? null;
        if ($port !== null && ! (($scheme === 'http' && (int) $port === 80) || ($scheme === 'https' && (int) $port === 443))) {
            throw new RuntimeException('La scansione del sito consente soltanto le porte web standard.');
        }
        $this->resolvePublicIps($this->host($url));

        return $url;
    }

    /** @return array<int, string> */
    protected function resolvePublicIps(string $host): array
    {
        $host = strtolower(rtrim($host, '.'));
        if ($host === 'localhost' || ! str_contains($host, '.') || preg_match('/\.(?:local|internal|localhost|test)$/', $host)) {
            throw new RuntimeException('Il sito deve usare un dominio pubblico.');
        }

        $ips = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips[] = $host;
        } else {
            $records = dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
            foreach ($records as $record) {
                $ip = $record['ip'] ?? $record['ipv6'] ?? null;
                if (is_string($ip)) {
                    $ips[] = $ip;
                }
            }
        }
        $ips = array_values(array_unique($ips));
        if ($ips === []) {
            throw new RuntimeException('Il dominio indicato non risulta raggiungibile.');
        }
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException('Non e consentito scansionare indirizzi locali o riservati.');
            }
        }

        return $ips;
    }

    /** @return array<int, string> */
    private function discoverUsefulLinks(string $html, string $baseUrl, string $originHost): array
    {
        preg_match_all('/<a\b[^>]*\bhref\s*=\s*(["\'])(.*?)\1/is', $html, $matches);
        $candidates = [];
        foreach ($matches[2] ?? [] as $href) {
            $href = html_entity_decode(trim((string) $href), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($href === '' || str_starts_with($href, '#') || preg_match('/^(?:mailto|tel|javascript):/i', $href)) {
                continue;
            }
            try {
                $resolved = (string) UriResolver::resolve(new Uri($baseUrl), new Uri($href));
                $parts = parse_url($resolved);
                if (! $this->sameWebsite($originHost, $this->host($resolved))) {
                    continue;
                }
                $path = strtolower((string) ($parts['path'] ?? '/'));
                $score = preg_match('/(?:servizi|services|prodotti|products|chi-siamo|about|azienda|faq|prezzi|pricing|soluzioni|solutions)/', $path) ? 0 : 1;
                $clean = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '').($parts['path'] ?? '/');
                $candidates[] = ['url' => $clean, 'score' => $score];
            } catch (\Throwable) {
                continue;
            }
        }
        usort($candidates, fn (array $left, array $right): int => $left['score'] <=> $right['score']);

        return array_slice(array_values(array_unique(array_column($candidates, 'url'))), 0, 12);
    }

    private function extractText(string $html): string
    {
        $html = preg_replace('/<(script|style|noscript|svg|template)\b[^>]*>.*?<\/\1>/is', ' ', $html) ?? $html;
        $html = preg_replace('/<(br|\/p|\/div|\/li|\/h[1-6])\b[^>]*>/i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\t ]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\R{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function extractTitle(string $html): string
    {
        if (preg_match('/<title\b[^>]*>(.*?)<\/title>/is', $html, $match)) {
            return mb_substr(trim(html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')), 0, 200);
        }

        return 'Pagina aziendale';
    }

    private function host(string $url): string
    {
        return strtolower(rtrim((string) parse_url($url, PHP_URL_HOST), '.'));
    }

    private function sameWebsite(string $left, string $right): bool
    {
        return preg_replace('/^www\./', '', $left) === preg_replace('/^www\./', '', $right);
    }

    private function visitKey(string $url): string
    {
        $parts = parse_url($url);

        return strtolower(($parts['scheme'] ?? '').'://'.($parts['host'] ?? '').($parts['path'] ?? '/'));
    }
}
