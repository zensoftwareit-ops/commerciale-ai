<?php

namespace App\Services\Quotations;

use App\Models\Lead;
use App\Models\PricingRule;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class CalculatePricingFormula
{
    public function __construct(private readonly RoadDistanceCalculator $distances, private readonly UniversalPricingCalculator $universal) {}

    /** @return array{applicable:bool,total:?float,line_items:array,assumptions:array,missing:array,calculation:array} */
    public function handle(Lead $lead, PricingRule $rule): array
    {
        $universal = $this->universal->handle($lead, $rule);
        if ($universal['applicable']) return $universal;
        $tiers = collect($rule->daily_rate_tiers ?? [])->filter(fn ($tier) => is_array($tier));
        $distanceRate = $rule->distance_rate_per_km !== null ? (float) $rule->distance_rate_per_km : null;
        if ($tiers->isEmpty() && $distanceRate === null) return ['applicable' => false, 'total' => null, 'line_items' => [], 'assumptions' => [], 'missing' => [], 'calculation' => []];

        $missing = []; $lines = []; $assumptions = []; $calculation = []; $total = 0.0;
        if ($tiers->isNotEmpty()) {
            $days = $this->durationDays($lead);
            if (! $days) {
                $missing[] = 'durata del noleggio';
            } else {
                $tier = $tiers->first(fn ($candidate) => $days >= (int) ($candidate['min_days'] ?? 0)
                    && (($candidate['max_days'] ?? null) === null || $days <= (int) $candidate['max_days']));
                if (! $tier) {
                    $missing[] = 'scaglione tariffario per '.$days.' giorni';
                } else {
                    $rate = (float) $tier['rate_per_day'];
                    $subtotal = round($days * $rate, 2); $total += $subtotal;
                    $lines[] = 'Noleggio: '.$days.' giorni × € '.number_format($rate, 2, ',', '.').' = € '.number_format($subtotal, 2, ',', '.');
                    $calculation['rental'] = ['days' => $days, 'rate_per_day' => $rate, 'subtotal' => $subtotal];
                }
            }
        }
        if ($distanceRate !== null && $distanceRate > 0) {
            $destination = $this->semanticValue($lead, 'destination');
            if (blank($rule->origin_address)) $missing[] = 'località di partenza per il trasporto';
            if (blank($destination)) $missing[] = 'destinazione del trasporto';
            if (filled($rule->origin_address) && filled($destination)) {
                $oneWay = $this->distances->kilometers((string) $rule->origin_address, (string) $destination);
                $billable = $rule->distance_round_trip ? $oneWay * 2 : $oneWay;
                $subtotal = round($billable * $distanceRate, 2); $total += $subtotal;
                $lines[] = 'Trasporto '.($rule->distance_round_trip ? 'andata/ritorno' : 'sola andata').': '.number_format($billable, 1, ',', '.').' km × € '.number_format($distanceRate, 2, ',', '.').' = € '.number_format($subtotal, 2, ',', '.');
                $calculation['transport'] = ['origin' => $rule->origin_address, 'destination' => $destination, 'one_way_km' => $oneWay, 'billable_km' => $billable, 'rate_per_km' => $distanceRate, 'subtotal' => $subtotal];
                $assumptions[] = 'Distanza stradale calcolata automaticamente tra '.$rule->origin_address.' e '.$destination.'.';
            }
        }

        return ['applicable' => true, 'total' => $missing === [] ? round($total, 2) : null, 'line_items' => $lines,
            'assumptions' => $assumptions, 'missing' => $missing, 'calculation' => $calculation];
    }

    private function durationDays(Lead $lead): ?int
    {
        $duration = $this->semanticValue($lead, 'duration');
        if (preg_match('/\d+/', (string) $duration, $match)) return max(1, (int) $match[0]);
        $start = $this->semanticValue($lead, 'start_date'); $end = $this->semanticValue($lead, 'end_date');
        try { return filled($start) && filled($end) ? max(1, Carbon::parse($start)->diffInDays(Carbon::parse($end))) : null; } catch (\Throwable) { return null; }
    }

    private function semanticValue(Lead $lead, string $semantic): mixed
    {
        foreach (Arr::dot($lead->request_data ?? []) as $path => $value) {
            if (! filled($value) || ! is_scalar($value)) continue;
            $field = Str::of((string) $path)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->value();
            $matches = match ($semantic) {
                'duration' => preg_match('/\b(?:durata|giorni)\b/', $field),
                'start_date' => preg_match('/\b(?:dal giorno|data inizio|inizio noleggio)\b/', $field),
                'end_date' => preg_match('/\b(?:al giorno|data fine|fine noleggio)\b/', $field),
                'destination' => preg_match('/\b(?:destinazione|localita|luogo|consegna|indirizzo)\b/', $field),
                default => false,
            };
            if ($matches) return $value;
        }
        return null;
    }
}
