<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AddContactToCampaignAction;
use App\Actions\ResolveCampaignRecipientsAction;
use App\Enums\CampaignChannel;
use App\Enums\CampaignStatus;
use App\Enums\RecipientStatus;
use App\Enums\WhatsAppMessageType;
use App\Http\Resources\CampaignRecipientResource;
use App\Http\Resources\CampaignResource;
use App\Jobs\SendEmailCampaignChunkJob;
use App\Jobs\SendSmsChunkJob;
use App\Jobs\SendWhatsAppChunkJob;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\CommunicationConfig;
use App\Models\Contact;
use App\Models\Segment;
use App\Models\WhatsAppSession;
use Illuminate\Bus\Batch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CampaignController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Campaign::query()
            ->with('whatsappSession')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('channel'), fn ($q) => $q->where('channel', $request->channel))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return CampaignResource::collection($query);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'channel' => ['required', Rule::enum(CampaignChannel::class)],
            'subject' => ['nullable', 'string', 'max:255'],
            'body_html' => ['nullable', 'string'],
            'body_text' => ['nullable', 'string'],
            'whatsapp_message_type' => ['nullable', Rule::enum(WhatsAppMessageType::class)],
            'whatsapp_session_id' => ['nullable', 'string', 'exists:whatsapp_sessions,ulid'],
            'segment_filters' => ['nullable', 'array'],
        ]);

        if (CampaignChannel::from($data['channel']) === CampaignChannel::WhatsApp && empty($data['whatsapp_session_id'])) {
            return response()->json(['message' => 'Selecione a sessão do WhatsApp que será usada para o envio.'], 422);
        }

        $data['status'] = CampaignStatus::Draft;
        $data['user_id'] = $this->typedUser($request)->id;
        $data = $this->resolveSegmentIds($data);
        $data = $this->resolveWhatsappSessionId($data);

        $campaign = Campaign::create($data);

        return (new CampaignResource($campaign))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $ulid): CampaignResource
    {
        $campaign = Campaign::where('ulid', $ulid)->with('whatsappSession')->withCount('recipients')->firstOrFail();

        return new CampaignResource($campaign);
    }

    public function update(Request $request, string $ulid): JsonResponse
    {
        $campaign = Campaign::where('ulid', $ulid)->firstOrFail();

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'channel' => ['sometimes', 'required', Rule::enum(CampaignChannel::class)],
            'subject' => ['nullable', 'string', 'max:255'],
            'body_html' => ['nullable', 'string'],
            'body_text' => ['nullable', 'string'],
            'whatsapp_message_type' => ['nullable', Rule::enum(WhatsAppMessageType::class)],
            'whatsapp_session_id' => ['nullable', 'string', 'exists:whatsapp_sessions,ulid'],
            'segment_filters' => ['nullable', 'array'],
        ]);

        // Content, audience and (for WhatsApp) the sending session stay editable
        // for the campaign's whole lifetime — recipients keep trickling in via
        // automations/manual adds even after the first send, and the OpenWA
        // session in use may need to change mid-flight. The channel itself is
        // the one thing that can't change once the campaign left Draft: it
        // would orphan whatever's already been sent/tracked under the old one.
        abort_if(
            $campaign->status !== CampaignStatus::Draft
                && array_key_exists('channel', $data)
                && $data['channel'] !== $campaign->channel->value,
            422,
            'O canal de uma campanha não pode ser alterado após sair do rascunho.',
        );

        $effectiveChannel = CampaignChannel::from($data['channel'] ?? $campaign->channel->value);
        $hasSession = array_key_exists('whatsapp_session_id', $data)
            ? ! empty($data['whatsapp_session_id'])
            : $campaign->whatsapp_session_id !== null;

        if ($effectiveChannel === CampaignChannel::WhatsApp && ! $hasSession) {
            return response()->json(['message' => 'Selecione a sessão do WhatsApp que será usada para o envio.'], 422);
        }

        $data = $this->resolveSegmentIds($data);
        $data = $this->resolveWhatsappSessionId($data);
        $campaign->update($data);

        return (new CampaignResource($campaign->fresh()))->response();
    }

    /**
     * The API always identifies segments by ULID; segment_filters.segment_ids
     * must be stored as the internal numeric FK so ResolveCampaignRecipientsAction
     * can query `segments.id` directly.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function resolveSegmentIds(array $data): array
    {
        if (($data['segment_filters']['audience_type'] ?? null) === 'segment' && ! empty($data['segment_filters']['segment_ids'])) {
            $data['segment_filters']['segment_ids'] = Segment::whereIn('ulid', $data['segment_filters']['segment_ids'])->pluck('id')->all();
        }

        return $data;
    }

    /**
     * The API always identifies WhatsApp sessions by ULID; stored as the
     * internal numeric FK to match the `campaigns.whatsapp_session_id` column.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function resolveWhatsappSessionId(array $data): array
    {
        if (! empty($data['whatsapp_session_id'])) {
            $data['whatsapp_session_id'] = WhatsAppSession::where('ulid', $data['whatsapp_session_id'])->value('id');
        }

        return $data;
    }

    public function uploadMedia(Request $request, string $ulid): JsonResponse
    {
        $campaign = Campaign::where('ulid', $ulid)->firstOrFail();

        $request->validate([
            'media' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,mp4,3gp,mov,mp3,ogg,m4a,pdf,doc,docx,xls,xlsx', 'max:51200'],
        ]);

        if ($campaign->media_path) {
            Storage::disk('public')->delete($campaign->media_path);
        }

        $path = $request->file('media')->store("campaigns/{$campaign->id}", 'public');
        $campaign->update(['media_path' => $path]);

        return response()->json(['mediaUrl' => url(Storage::url($path))]);
    }

    public function destroy(Request $request, string $ulid): JsonResponse
    {
        $campaign = Campaign::where('ulid', $ulid)->firstOrFail();

        // Non-draft campaigns are normally locked to prevent losing send history,
        // but an admin may need to clean up a mistaken/test send — allow it for
        // that role regardless of status.
        abort_if(
            $campaign->status !== CampaignStatus::Draft && ! $this->typedUser($request)->isAdmin(),
            422,
            'Only draft campaigns can be deleted.',
        );

        if ($campaign->media_path) {
            Storage::disk('public')->delete($campaign->media_path);
        }

        $campaign->delete();

        return response()->json(null, 204);
    }

    public function send(Request $request, string $ulid, ResolveCampaignRecipientsAction $resolveRecipients): JsonResponse
    {
        $campaign = Campaign::where('ulid', $ulid)->firstOrFail();

        // "Sending" is allowed too: a previous call may have deferred part of the
        // audience because of the daily limit, and this lets the admin resume the
        // rest (e.g. the next day) without the campaign ever being stuck.
        abort_if(
            ! in_array($campaign->status, [CampaignStatus::Draft, CampaignStatus::Scheduled, CampaignStatus::Sending]),
            422,
            'Campaign cannot be sent in its current status.',
        );

        $resolveRecipients->execute($campaign);

        $pendingIds = $campaign->recipients()->where('status', 'pending')->pluck('id')->all();

        if (empty($pendingIds)) {
            return response()->json(['message' => 'Nenhum destinatário elegível foi encontrado para esta campanha.'], 422);
        }

        $config = CommunicationConfig::where('tenant_id', $campaign->tenant_id)->first();
        $batchSize = max(1, $config->batch_size ?? 50);
        $batchDelay = max(0, $config->batch_delay_seconds ?? 30);
        $dailyLimit = match ($campaign->channel) {
            CampaignChannel::Email => $config->daily_email_limit ?? null,
            CampaignChannel::Sms => $config->daily_sms_limit ?? null,
            CampaignChannel::WhatsApp => $config->daily_whatsapp_limit ?? null,
        };

        $idsToSend = $pendingIds;
        $deferredCount = 0;

        if ($dailyLimit !== null) {
            $sentToday = CampaignRecipient::whereDate('sent_at', today())
                ->whereHas('campaign', fn ($q) => $q->where('tenant_id', $campaign->tenant_id)->where('channel', $campaign->channel))
                ->count();

            $remainingQuota = max(0, $dailyLimit - $sentToday);

            if (count($pendingIds) > $remainingQuota) {
                $idsToSend = array_slice($pendingIds, 0, $remainingQuota);
                $deferredCount = count($pendingIds) - count($idsToSend);
            }
        }

        if (empty($idsToSend)) {
            return response()->json([
                'message' => 'O limite diário de envio deste canal já foi atingido. Tente novamente amanhã.',
            ], 422);
        }

        $campaign->update([
            'status' => CampaignStatus::Sending,
            'started_at' => $campaign->started_at ?? now(),
        ]);

        // Batches of `batchSize`, staggered `batchDelay` seconds apart — mirrors
        // Mautic's batch sending, so we never burst the SES/SNS/OpenWA APIs.
        $jobs = [];
        foreach (array_chunk($idsToSend, $batchSize) as $index => $chunk) {
            $job = match ($campaign->channel) {
                CampaignChannel::Email => new SendEmailCampaignChunkJob($campaign->id, $chunk),
                CampaignChannel::Sms => new SendSmsChunkJob($campaign->id, $chunk),
                CampaignChannel::WhatsApp => new SendWhatsAppChunkJob($campaign->id, $chunk),
            };

            if ($index > 0) {
                $job->delay(now()->addSeconds($index * $batchDelay));
            }

            $jobs[] = $job;
        }

        Bus::batch($jobs)
            ->name("campaign-{$campaign->id}-send")
            ->then(function (Batch $batch) use ($campaign) {
                $stillPending = $campaign->recipients()->where('status', 'pending')->exists();
                if (! $stillPending) {
                    $campaign->update(['status' => CampaignStatus::Sent, 'completed_at' => now()]);
                }
            })
            ->dispatch();

        $message = $deferredCount > 0
            ? sprintf(
                'Envio iniciado para %d destinatários em lotes de %d. %d ficaram pendentes por causa do limite diário e serão enviados na próxima tentativa.',
                count($idsToSend),
                $batchSize,
                $deferredCount,
            )
            : sprintf('Envio iniciado para %d destinatários, em lotes de %d.', count($idsToSend), $batchSize);

        return response()->json(['message' => $message]);
    }

    public function schedule(Request $request, string $ulid): CampaignResource
    {
        $campaign = Campaign::where('ulid', $ulid)->firstOrFail();

        abort_if($campaign->status !== CampaignStatus::Draft, 422, 'Only draft campaigns can be scheduled.');

        $data = $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now'],
        ]);

        $campaign->update([
            'scheduled_at' => $data['scheduled_at'],
            'status' => CampaignStatus::Scheduled,
        ]);

        return new CampaignResource($campaign->fresh());
    }

    public function cancel(string $ulid): CampaignResource
    {
        $campaign = Campaign::where('ulid', $ulid)->firstOrFail();

        $campaign->update(['status' => CampaignStatus::Cancelled]);

        return new CampaignResource($campaign->fresh());
    }

    public function recipients(string $ulid): AnonymousResourceCollection
    {
        $campaign = Campaign::where('ulid', $ulid)->firstOrFail();

        $recipients = CampaignRecipient::with('contact')
            ->where('campaign_id', $campaign->id)
            ->paginate(15);

        return CampaignRecipientResource::collection($recipients);
    }

    public function addRecipient(Request $request, string $ulid, AddContactToCampaignAction $addToCampaign): JsonResponse
    {
        $campaign = Campaign::where('ulid', $ulid)->firstOrFail();

        abort_if($campaign->status === CampaignStatus::Cancelled, 422, 'Campanha cancelada não aceita novos destinatários.');

        $data = $request->validate([
            'contact_id' => ['required', 'string', 'exists:contacts,ulid'],
        ]);

        $contact = Contact::where('ulid', $data['contact_id'])->firstOrFail();

        if (! $contact->lgpd_consent || $contact->unsubscribed_at) {
            return response()->json([
                'message' => 'Este contato não pode receber comunicações (sem consentimento LGPD ou descadastrado).',
            ], 422);
        }

        $result = $addToCampaign->execute($campaign, $contact, dispatch: 'if_active');

        if (! $result['created']) {
            return response()->json(['message' => 'Este contato já é destinatário desta campanha.'], 422);
        }

        return (new CampaignRecipientResource($result['recipient']->load('contact')))
            ->response()
            ->setStatusCode(201);
    }

    public function bulkAddRecipients(Request $request, string $ulid, AddContactToCampaignAction $addToCampaign): JsonResponse
    {
        $campaign = Campaign::where('ulid', $ulid)->firstOrFail();

        abort_if($campaign->status === CampaignStatus::Cancelled, 422, 'Campanha cancelada não aceita novos destinatários.');

        $data = $request->validate([
            'contact_ids' => ['required', 'array', 'min:1'],
            'contact_ids.*' => ['string', 'exists:contacts,ulid'],
        ]);

        $contacts = Contact::whereIn('ulid', $data['contact_ids'])->get();

        $added = 0;
        $skipped = 0;
        foreach ($contacts as $contact) {
            if (! $contact->lgpd_consent || $contact->unsubscribed_at) {
                $skipped++;

                continue;
            }

            $result = $addToCampaign->execute($campaign, $contact, dispatch: 'if_active');
            $result['created'] ? $added++ : $skipped++;
        }

        return response()->json([
            'message' => "{$added} contato(s) adicionado(s) à campanha \"{$campaign->name}\"."
                .($skipped > 0 ? " {$skipped} ignorado(s) (já eram destinatários ou não podem receber comunicações)." : ''),
            'added' => $added,
            'skipped' => $skipped,
        ]);
    }

    public function removeRecipient(string $ulid, int $recipientId): JsonResponse
    {
        $campaign = Campaign::where('ulid', $ulid)->firstOrFail();
        $recipient = CampaignRecipient::where('campaign_id', $campaign->id)->findOrFail($recipientId);

        abort_if($recipient->status !== RecipientStatus::Pending, 422, 'Só é possível remover destinatários que ainda não foram processados.');

        $recipient->delete();
        $campaign->decrement('total_recipients');

        return response()->json(null, 204);
    }
}
