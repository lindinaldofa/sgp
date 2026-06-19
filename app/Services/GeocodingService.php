<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodingService
{
    private const NOMINATIM_URL = 'https://nominatim.openstreetmap.org/search';

    /** @return array{lat: float, lng: float}|null */
    public function geocode(string $address): ?array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'SGP/1.0 (Sistema de Gestão Política)',
            ])->get(self::NOMINATIM_URL, [
                'q' => $address,
                'format' => 'json',
                'limit' => 1,
                'countrycodes' => 'br',
            ]);

            if ($response->successful() && count($response->json()) > 0) {
                $result = $response->json()[0];

                return [
                    'lat' => (float) $result['lat'],
                    'lng' => (float) $result['lon'],
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('Geocoding failed: '.$e->getMessage());
        }

        return null;
    }
}
