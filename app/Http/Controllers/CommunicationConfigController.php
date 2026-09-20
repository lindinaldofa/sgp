<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CampaignChannel;
use App\Models\CommunicationConfig;
use App\Models\WhatsAppSession;
use App\Services\OpenWaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class CommunicationConfigController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $config = CommunicationConfig::where('tenant_id', $this->typedUser($request)->tenant_id)->first();

        return response()->json($config);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'aws_region' => ['nullable', 'string', 'max:20'],
            'ses_from_email' => ['nullable', 'email', 'max:255'],
            'ses_from_name' => ['nullable', 'string', 'max:255'],
            'sns_region' => ['nullable', 'string', 'max:20'],
            'sns_sms_sender_id' => ['nullable', 'string', 'max:20'],
            'openwa_base_url' => ['nullable', 'url', 'max:500'],
            'openwa_api_key' => ['nullable', 'string'],
            'openwa_session_id' => ['nullable', 'string', 'max:100'],
            'daily_email_limit' => ['nullable', 'integer', 'min:0'],
            'daily_sms_limit' => ['nullable', 'integer', 'min:0'],
            'daily_whatsapp_limit' => ['nullable', 'integer', 'min:0'],
            'batch_size' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'batch_delay_seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
        ]);

        $config = CommunicationConfig::updateOrCreate(
            ['tenant_id' => $this->typedUser($request)->tenant_id],
            $data,
        );

        return response()->json($config);
    }

    public function test(Request $request, OpenWaService $whatsapp): JsonResponse
    {
        $data = $request->validate([
            'channel' => ['required', Rule::enum(CampaignChannel::class)],
            'to' => ['required', 'string', 'max:255'],
            'whatsapp_session_id' => ['nullable', 'string', 'exists:whatsapp_sessions,ulid'],
        ]);

        $tenantId = $this->typedUser($request)->tenant_id;

        if (CampaignChannel::from($data['channel']) === CampaignChannel::Sms) {
            return response()->json(['message' => 'Teste de SMS ainda não é suportado por este endpoint.'], 422);
        }

        $sessionId = isset($data['whatsapp_session_id'])
            ? WhatsAppSession::where('ulid', $data['whatsapp_session_id'])->value('session_id')
            : null;

        $success = match (CampaignChannel::from($data['channel'])) {
            CampaignChannel::WhatsApp => $whatsapp->sendText(
                $data['to'],
                'Mensagem de teste do SGP — sua integração com o OpenWA está funcionando.',
                $tenantId,
                $sessionId,
            ),
            CampaignChannel::Email => $this->sendTestEmail($tenantId, $data['to']),
            CampaignChannel::Sms => false,
        };

        if (! $success) {
            return response()->json(['message' => 'Falha ao enviar mensagem de teste. Verifique as configurações.'], 422);
        }

        return response()->json(['message' => 'Mensagem de teste enviada com sucesso.']);
    }

    private function sendTestEmail(int $tenantId, string $to): bool
    {
        $config = CommunicationConfig::where('tenant_id', $tenantId)->first();

        if (! $config?->ses_from_email) {
            return false;
        }

        try {
            Mail::html('Este é um e-mail de teste do SGP para validar sua integração com o Amazon SES.', function ($message) use ($to, $config) {
                $message->to($to)
                    ->subject('SGP — Teste de configuração de e-mail')
                    ->from($config->ses_from_email, $config->ses_from_name);
            });

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
