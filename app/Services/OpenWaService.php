<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CommunicationConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends WhatsApp messages through a self-hosted OpenWA instance.
 *
 * @see https://github.com/rmyndharis/OpenWA
 */
class OpenWaService
{
    public function sendText(string $phone, string $message, int $tenantId): bool
    {
        $config = CommunicationConfig::where('tenant_id', $tenantId)->first();

        if (! $config?->openwa_base_url || ! $config->openwa_api_key || ! $config->openwa_session_id) {
            Log::warning("OpenWA not configured for tenant {$tenantId}");

            return false;
        }

        $chatId = $this->toChatId($phone);
        if (! $chatId) {
            return false;
        }

        $baseUrl = rtrim($config->openwa_base_url, '/');

        try {
            $response = Http::withHeader('X-API-Key', $config->openwa_api_key)
                ->post("{$baseUrl}/api/sessions/{$config->openwa_session_id}/messages/send-text", [
                    'chatId' => $chatId,
                    'text' => $message,
                ]);

            if (! $response->successful()) {
                Log::warning("OpenWA send failed for tenant {$tenantId}: HTTP {$response->status()} {$response->body()}");
            }

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('OpenWA send failed: '.$e->getMessage());

            return false;
        }
    }

    private function toChatId(string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (! str_starts_with($digits, '55')) {
            $digits = '55'.$digits;
        }

        return "{$digits}@c.us";
    }
}
