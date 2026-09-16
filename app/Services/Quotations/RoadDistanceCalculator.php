<?php

namespace App\Services\Quotations;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class RoadDistanceCalculator
{
    public function kilometers(string $origin, string $destination): float
    {
        $key = (string) config('commerciale-ai.routing.api_key');
        if ($key === '') throw new RuntimeException('Configura OPENROUTESERVICE_API_KEY per calcolare i costi chilometrici.');

        $cacheKey = 'road-distance:'.hash('sha256', mb_strtolower(trim($origin)).'|'.mb_strtolower(trim($destination)));
        return Cache::remember($cacheKey, now()->addDays(30), fn (): float => $this->requestKilometers($origin, $destination, $key));
    }

    private function requestKilometers(string $origin, string $destination, string $key): float
    {
        $originCoordinates = $this->geocode($origin, $key);
        $destinationCoordinates = $this->geocode($destination, $key);
        $response = Http::withHeaders(['Authorization' => $key])->acceptJson()
            ->timeout((int) config('commerciale-ai.routing.timeout', 20))
            ->post(rtrim((string) config('commerciale-ai.routing.api_url'), '/').'/v2/directions/driving-car/json', [
                'coordinates' => [$originCoordinates, $destinationCoordinates],
                'instructions' => false,
            ]);
        if ($response->failed() || ! is_numeric($response->json('routes.0.summary.distance'))) {
            throw new RuntimeException('Il servizio cartografico non ha restituito una distanza stradale utilizzabile.');
        }

        return round(((float) $response->json('routes.0.summary.distance')) / 1000, 1);
    }

    private function geocode(string $address, string $key): array
    {
        $response = Http::acceptJson()->timeout((int) config('commerciale-ai.routing.timeout', 20))
            ->get(rtrim((string) config('commerciale-ai.routing.api_url'), '/').'/geocode/search', [
                'api_key' => $key, 'text' => $address, 'size' => 1,
            ]);
        $coordinates = $response->json('features.0.geometry.coordinates');
        if ($response->failed() || ! is_array($coordinates) || count($coordinates) < 2) {
            throw new RuntimeException('Località non trovata dal servizio cartografico: '.$address.'.');
        }

        return [(float) $coordinates[0], (float) $coordinates[1]];
    }
}
