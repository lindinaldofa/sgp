<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\WhatsAppMessageType;
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
        return $this->send($tenantId, $phone, 'send-text', ['text' => $message]);
    }

    /**
     * Sends a media message (image, video, audio or document) by URL.
     * The URL must be publicly reachable by the OpenWA server.
     */
    public function sendMedia(
        WhatsAppMessageType $type,
        string $phone,
        string $mediaUrl,
        int $tenantId,
        ?string $caption = null,
        ?string $filename = null,
    ): bool {
        $endpoint = match ($type) {
            WhatsAppMessageType::Image => 'send-image',
            WhatsAppMessageType::Video => 'send-video',
            WhatsAppMessageType::Audio => 'send-audio',
            WhatsAppMessageType::Document => 'send-document',
            WhatsAppMessageType::Text => throw new \InvalidArgumentException('Use sendText() for text messages.'),
        };

        $payload = array_filter([
            'url' => $mediaUrl,
            'caption' => $caption,
            'filename' => $filename,
        ], fn ($value) => $value !== null && $value !== '');

        return $this->send($tenantId, $phone, $endpoint, $payload);
    }

    /** @param array<string, mixed> $payload */
    private function send(int $tenantId, string $phone, string $endpoint, array $payload): bool
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
                ->post("{$baseUrl}/api/sessions/{$config->openwa_session_id}/messages/{$endpoint}", [
                    'chatId' => $chatId,
                    ...$payload,
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
