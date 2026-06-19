<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CommunicationConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppCloudApiService
{
    /**
     * @param array<int, mixed> $parameters
     */
    public function sendTemplate(
        string $phone,
        string $templateName,
        int $tenantId,
        array $parameters = [],
        string $language = 'pt_BR',
    ): bool {
        $config = CommunicationConfig::where('tenant_id', $tenantId)->first();

        if (! $config?->whatsapp_phone_number_id || ! $config->whatsapp_access_token) {
            Log::warning("WhatsApp not configured for tenant {$tenantId}");

            return false;
        }

        $phone = (string) preg_replace('/\D/', '', $phone);
        if (! str_starts_with($phone, '55')) {
            $phone = '55'.$phone;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language],
            ],
        ];

        if (! empty($parameters)) {
            $payload['template']['components'] = [[
                'type' => 'body',
                'parameters' => array_map(
                    fn ($p) => ['type' => 'text', 'text' => $p],
                    $parameters,
                ),
            ]];
        }

        try {
            $response = Http::withToken($config->whatsapp_access_token)
                ->post("https://graph.facebook.com/v18.0/{$config->whatsapp_phone_number_id}/messages", $payload);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('WhatsApp send failed: '.$e->getMessage());

            return false;
        }
    }

    public function sendText(string $phone, string $message, int $tenantId): bool
    {
        $config = CommunicationConfig::where('tenant_id', $tenantId)->first();

        if (! $config?->whatsapp_phone_number_id || ! $config->whatsapp_access_token) {
            return false;
        }

        $phone = (string) preg_replace('/\D/', '', $phone);
        if (! str_starts_with($phone, '55')) {
            $phone = '55'.$phone;
        }

        try {
            $response = Http::withToken($config->whatsapp_access_token)
                ->post("https://graph.facebook.com/v18.0/{$config->whatsapp_phone_number_id}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $phone,
                    'type' => 'text',
                    'text' => ['body' => $message],
                ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('WhatsApp send failed: '.$e->getMessage());

            return false;
        }
    }
}
