<?php

namespace App\Services\Leads;

use App\Models\InboundSource;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class AdaptiveLeadPayload
{
    private const ALIASES = [
        'external_id' => ['external_id', 'submission_id', 'entry_id', 'lead_id', 'request_id', 'id_richiesta', 'request_uuid', 'uuid', 'id'],
        'name' => ['contact.name', 'contact.full_name', 'customer.name', 'customer.full_name', 'full_name', 'fullname', 'nome_cognome', 'nome_e_cognome', 'nominativo', 'name', 'nome'],
        'first_name' => ['contact.first_name', 'customer.first_name', 'first_name', 'firstname', 'nome'],
        'last_name' => ['contact.last_name', 'customer.last_name', 'last_name', 'lastname', 'surname', 'cognome'],
        'email' => ['contact.email', 'customer.email', 'email', 'e_mail', 'email_address', 'mail'],
        'phone' => ['contact.phone', 'customer.phone', 'phone', 'phone_number', 'telephone', 'telefono', 'numero_telefono', 'cellulare', 'mobile'],
        'company' => ['contact.company', 'customer.company', 'company', 'company_name', 'business', 'business_name', 'azienda', 'ragione_sociale'],
        'service' => ['request.project_type', 'request.service', 'project_type', 'tipo_progetto', 'requested_service', 'service', 'servizio', 'tipo_sito', 'tipo_di_sito', 'website_type'],
        'message' => ['request.message', 'request.notes', 'message', 'messaggio', 'notes', 'note', 'details', 'dettagli', 'description', 'descrizione'],
        'privacy' => ['consent.privacy_accepted', 'privacy_accepted', 'privacy_consent', 'consenso_privacy', 'gdpr_consent', 'gdpr', 'privacy'],
        'marketing' => ['consent.marketing_accepted', 'marketing_accepted', 'marketing_consent', 'consenso_marketing', 'newsletter'],
    ];

    /** @return array<string, mixed> */
    public function normalize(array $payload, InboundSource $source): array
    {
        $name = $this->find($payload, self::ALIASES['name']);
        if (! filled($name)) {
            $name = trim(implode(' ', array_filter([
                $this->find($payload, self::ALIASES['first_name']),
                $this->find($payload, self::ALIASES['last_name']),
            ], 'filled')));
        }

        $email = $this->string($this->find($payload, self::ALIASES['email']), 255);
        $phone = $this->string($this->find($payload, self::ALIASES['phone']), 50);
        $company = $this->string($this->find($payload, self::ALIASES['company']), 255);
        $message = $this->string($this->find($payload, self::ALIASES['message']), 5000);
        $requestData = $this->businessData($payload);
        if ($message && ! array_key_exists('message', $requestData)) {
            $requestData = ['message' => $message, ...$requestData];
        }

        return [
            'external_id' => $this->string($this->find($payload, self::ALIASES['external_id']), 255),
            'source_label' => $source->name,
            'name' => $this->string($name, 255) ?: $company ?: $email ?: 'Lead da '.$source->name,
            'email' => $email,
            'phone' => $phone,
            'company' => $company,
            'requested_service' => $this->string($this->find($payload, self::ALIASES['service']), 255),
            'request_data' => $requestData,
            'consent_data' => [
                'privacy_accepted' => $this->boolean($this->find($payload, self::ALIASES['privacy'])),
                'marketing_accepted' => $this->boolean($this->find($payload, self::ALIASES['marketing'])),
            ],
        ];
    }

    public function explicitPrivacyRefusal(array $payload): bool
    {
        $value = $this->find($payload, self::ALIASES['privacy']);

        return $value !== null && $this->boolean($value) === false;
    }

    private function find(array $payload, array $aliases): mixed
    {
        foreach ($aliases as $alias) {
            $value = data_get($payload, $alias);
            if ($this->usable($value)) {
                return $value;
            }
        }

        $wanted = collect($aliases)->map(fn (string $alias): string => Str::afterLast($alias, '.'))->all();
        foreach (Arr::dot($payload) as $path => $value) {
            $key = Str::of(Str::afterLast((string) $path, '.'))->snake()->lower()->toString();
            if (in_array($key, $wanted, true) && $this->usable($value)) {
                return $value;
            }
        }

        return null;
    }

    private function businessData(array $payload): array
    {
        $excluded = collect([
            ...self::ALIASES['name'], ...self::ALIASES['first_name'], ...self::ALIASES['last_name'],
            ...self::ALIASES['email'], ...self::ALIASES['phone'], ...self::ALIASES['company'],
            ...self::ALIASES['privacy'], ...self::ALIASES['marketing'],
        ])->map(fn (string $alias): string => Str::afterLast($alias, '.'))->unique()->all();

        $clean = function (array $data) use (&$clean, $excluded): array {
            $result = [];
            foreach ($data as $key => $value) {
                $normalizedKey = Str::of((string) $key)->snake()->lower()->toString();
                if (in_array($normalizedKey, $excluded, true)) {
                    continue;
                }
                if (is_array($value)) {
                    $value = $clean($value);
                    if ($value === []) {
                        continue;
                    }
                }
                $result[$key] = $value;
            }

            return $result;
        };

        return $clean($payload);
    }

    private function usable(mixed $value): bool
    {
        return is_scalar($value) && $value !== '';
    }

    private function string(mixed $value, int $limit): ?string
    {
        return is_scalar($value) && trim((string) $value) !== '' ? Str::limit(trim((string) $value), $limit, '') : null;
    }

    private function boolean(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    }
}
