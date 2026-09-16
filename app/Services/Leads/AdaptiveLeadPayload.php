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
        'service' => ['request.project_type', 'request.service', 'project_type', 'tipo_progetto', 'requested_service', 'service', 'servizio', 'tipo_sito', 'tipo_di_sito', 'website_type', 'mezzo', 'tipo_mezzo', 'quale_mezzo_ti_occorre', 'prodotto'],
        'message' => ['request.message', 'request.notes', 'message', 'messaggio', 'notes', 'note', 'details', 'dettagli', 'description', 'descrizione'],
        'privacy' => ['consent.privacy_accepted', 'privacy_accepted', 'privacy_consent', 'consenso_privacy', 'gdpr_consent', 'gdpr', 'privacy'],
        'marketing' => ['consent.marketing_accepted', 'marketing_accepted', 'marketing_consent', 'consenso_marketing', 'newsletter'],
    ];

    /** @return array<string, mixed> */
    public function normalize(array $payload, InboundSource $source): array
    {
        $payload = $this->expandBusinessContainers($payload);
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
        $payload = $this->expandBusinessContainers($payload);
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

        $wanted = collect($aliases)->map(fn (string $alias): string => $this->normalizeKey(Str::afterLast($alias, '.')))->all();
        foreach (Arr::dot($payload) as $path => $value) {
            $key = $this->normalizeKey(Str::afterLast((string) $path, '.'));
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
        ])->map(fn (string $alias): string => $this->normalizeKey(Str::afterLast($alias, '.')))->unique()->all();

        $clean = function (array $data) use (&$clean, $excluded): array {
            $result = [];
            foreach ($data as $key => $value) {
                $normalizedKey = $this->normalizeKey((string) $key);
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

    /**
     * WPForms integrations commonly wrap answers in data/fields or send a list
     * of {label|name, value} objects. Convert those variants to label => value
     * before aliases and business data are evaluated.
     *
     * @return array<string, mixed>
     */
    private function expandBusinessContainers(array $payload): array
    {
        $expanded = $payload;
        foreach (['data', 'fields', 'form_data', 'answers', 'responses'] as $containerKey) {
            if (! array_key_exists($containerKey, $expanded)) {
                continue;
            }
            $container = $expanded[$containerKey];
            if (is_string($container)) {
                try {
                    $decoded = json_decode($container, true, 128, JSON_THROW_ON_ERROR);
                    $container = is_array($decoded) ? $decoded : $container;
                } catch (\JsonException) {
                    // Keep a non-JSON string as ordinary source data.
                }
            }
            if (! is_array($container)) {
                continue;
            }
            $answers = $this->namedAnswers($container);
            if ($answers === []) {
                continue;
            }
            unset($expanded[$containerKey]);
            foreach ($answers as $label => $value) {
                $this->appendAnswer($expanded, $label, $value);
            }
        }

        return $expanded;
    }

    /** @return array<string, mixed> */
    private function namedAnswers(array $data): array
    {
        $answers = [];
        foreach ($data as $key => $item) {
            if (! is_array($item)) {
                if (! is_int($key) && trim((string) $key) !== '') {
                    $this->appendAnswer($answers, (string) $key, $item);
                }
                continue;
            }

            $label = null;
            foreach (['label', 'name', 'caption', 'title', 'question'] as $labelKey) {
                if (isset($item[$labelKey]) && is_scalar($item[$labelKey]) && trim((string) $item[$labelKey]) !== '') {
                    $label = trim((string) $item[$labelKey]);
                    break;
                }
            }
            $hasValue = false;
            $value = null;
            foreach (['value', 'answer', 'values', 'value_raw'] as $valueKey) {
                if (array_key_exists($valueKey, $item)) {
                    $value = $item[$valueKey];
                    $hasValue = true;
                    break;
                }
            }
            if ($label !== null && $hasValue) {
                $this->appendAnswer($answers, $label, $value);
                continue;
            }

            if (! is_int($key) && ! array_is_list($item)) {
                $this->appendAnswer($answers, (string) $key, $item);
                continue;
            }
            foreach ($this->namedAnswers($item) as $nestedLabel => $nestedValue) {
                $this->appendAnswer($answers, $nestedLabel, $nestedValue);
            }
        }

        return $answers;
    }

    private function appendAnswer(array &$answers, string $label, mixed $value): void
    {
        $label = trim($label);
        if ($label === '') {
            return;
        }
        $candidate = $label;
        $suffix = 2;
        while (array_key_exists($candidate, $answers)) {
            if ($answers[$candidate] === $value) {
                return;
            }
            $candidate = $label.' ('.$suffix++.')';
        }
        $answers[$candidate] = $value;
    }

    private function usable(mixed $value): bool
    {
        return is_scalar($value) && $value !== '';
    }

    private function normalizeKey(string $key): string
    {
        $key = Str::of($key)->ascii()->snake()->lower()->toString();

        return trim((string) preg_replace('/[^a-z0-9]+/', '_', $key), '_');
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
