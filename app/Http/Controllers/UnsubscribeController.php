<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\RecipientStatus;
use App\Models\CampaignRecipient;
use Illuminate\Http\JsonResponse;

class UnsubscribeController extends Controller
{
    public function handle(int $recipient): JsonResponse
    {
        $campaignRecipient = CampaignRecipient::with('contact')->find($recipient);

        if (! $campaignRecipient || ! $campaignRecipient->contact) {
            return response()->json(['message' => 'Link inválido ou expirado.'], 404);
        }

        $campaignRecipient->update(['status' => RecipientStatus::Unsubscribed]);
        $campaignRecipient->contact->update(['unsubscribed_at' => now()]);

        return response()->json([
            'message' => 'Você foi descadastrado com sucesso e não receberá mais nossas mensagens.',
            'contactName' => $campaignRecipient->contact->name,
        ]);
    }
}
