<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CampaignRecipient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Handle Amazon SES webhook notifications.
     *
     * Processes SNS notification types: Bounce, Complaint, Delivery.
     */
    public function ses(Request $request): Response
    {
        Log::channel('stack')->info('SES webhook received', $request->all());

        $payload = $request->json()->all();
        $notificationType = $payload['notificationType'] ?? $payload['eventType'] ?? null;
        $messageId = $payload['mail']['messageId'] ?? null;

        if ($messageId && $notificationType) {
            $status = match ($notificationType) {
                'Bounce' => 'bounced',
                'Complaint' => 'complained',
                'Delivery' => 'delivered',
                default => null,
            };

            if ($status) {
                CampaignRecipient::where('external_id', $messageId)
                    ->update(['status' => $status]);
            }
        }

        return response('OK', 200);
    }

    /**
     * Handle WhatsApp / Meta Cloud API webhook notifications.
     *
     * Processes delivery statuses from the statuses array.
     */
    public function whatsapp(Request $request): Response
    {
        Log::channel('stack')->info('WhatsApp webhook received', $request->all());

        $payload = $request->json()->all();
        $statuses = data_get($payload, 'entry.0.changes.0.value.statuses', []);

        foreach ($statuses as $status) {
            $messageId = $status['id'] ?? null;
            $statusValue = $status['status'] ?? null;

            if (! $messageId || ! $statusValue) {
                continue;
            }

            $mappedStatus = match ($statusValue) {
                'sent' => 'sent',
                'delivered' => 'delivered',
                'read' => 'opened',
                'failed' => 'failed',
                default => null,
            };

            if ($mappedStatus) {
                CampaignRecipient::where('external_id', $messageId)
                    ->update(['status' => $mappedStatus]);
            }
        }

        return response('OK', 200);
    }
}
