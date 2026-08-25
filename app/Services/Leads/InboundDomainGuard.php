<?php

namespace App\Services\Leads;

use App\Models\InboundSource;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class InboundDomainGuard
{
    /** @return array{mode: string, domain: ?string} */
    public function verify(Request $request, array $payload, InboundSource $source): array
    {
        $allowed = collect($source->allowed_domains ?? [])->map(self::normalizeDomain(...))->filter()->unique()->values();
        abort_if($allowed->isEmpty(), 403, 'Nessun dominio consentito configurato per la sorgente.');

        [$mode, $domain] = $this->domainEvidence($request, $payload);
        if ($domain !== null) {
            $valid = $allowed->contains(fn (string $allowedDomain): bool => $domain === $allowedDomain || str_ends_with($domain, '.'.$allowedDomain));
            abort_unless($valid, 403, 'Dominio sorgente non consentito.');
        }

        return ['mode' => $domain ? $mode : 'endpoint_token', 'domain' => $domain];
    }

    public static function normalizeDomain(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        $candidate = trim(Str::lower($value));
        $host = parse_url(str_contains($candidate, '://') ? $candidate : 'https://'.$candidate, PHP_URL_HOST);
        if (! is_string($host) || (! str_contains($host, '.') && $host !== 'localhost')) {
            return null;
        }

        return trim($host, '.');
    }

    /** @return array{string, ?string} */
    private function domainEvidence(Request $request, array $payload): array
    {
        foreach (['Origin' => 'origin_header', 'Referer' => 'referer_header'] as $header => $mode) {
            if ($domain = self::normalizeDomain($request->header($header))) {
                return [$mode, $domain];
            }
        }

        $names = ['source_url', 'site_url', 'website_url', 'origin', 'domain', 'hostname', 'source'];
        foreach (Arr::dot($payload) as $path => $value) {
            $key = Str::of(Str::afterLast((string) $path, '.'))->snake()->lower()->toString();
            if (in_array($key, $names, true) && $domain = self::normalizeDomain($value)) {
                return ['payload', $domain];
            }
        }

        return ['endpoint_token', null];
    }
}
