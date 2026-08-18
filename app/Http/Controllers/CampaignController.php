<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ResolveCampaignRecipientsAction;
use App\Enums\CampaignChannel;
use App\Enums\CampaignStatus;
use App\Http\Resources\CampaignRecipientResource;
use App\Http\Resources\CampaignResource;
use App\Jobs\SendEmailCampaignChunkJob;
use App\Jobs\SendSmsChunkJob;
use App\Jobs\SendWhatsAppChunkJob;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class CampaignController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Campaign::query()
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
            'whatsapp_template_name' => ['nullable', 'string'],
            'whatsapp_template_lang' => ['nullable', 'string'],
            'segment_filters' => ['nullable', 'array'],
        ]);

        $data['status'] = CampaignStatus::Draft;
        $data['user_id'] = $this->typedUser($request)->id;

        $campaign = Campaign::create($data);

        return (new CampaignResource($campaign))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $ulid): CampaignResource
    {
        $campaign = Campaign::where('ulid', $ulid)->withCount('recipients')->firstOrFail();

        return new CampaignResource($campaign);
    }

    public function update(Request $request, string $ulid): CampaignResource
    {
        $campaign = Campaign::where('ulid', $ulid)->firstOrFail();

        abort_if($campaign->status !== CampaignStatus::Draft, 422, 'Only draft campaigns can be updated.');

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'channel' => ['sometimes', 'required', Rule::enum(CampaignChannel::class)],
            'subject' => ['nullable', 'string', 'max:255'],
            'body_html' => ['nullable', 'string'],
            'body_text' => ['nullable', 'string'],
            'whatsapp_template_name' => ['nullable', 'string'],
            'whatsapp_template_lang' => ['nullable', 'string'],
            'segment_filters' => ['nullable', 'array'],
        ]);

        $campaign->update($data);

        return new CampaignResource($campaign->fresh());
    }

    public function destroy(string $ulid): JsonResponse
    {
        $campaign = Campaign::where('ulid', $ulid)->firstOrFail();

        abort_if($campaign->status !== CampaignStatus::Draft, 422, 'Only draft campaigns can be deleted.');

        $campaign->delete();

        return response()->json(null, 204);
    }

    public function send(Request $request, string $ulid, ResolveCampaignRecipientsAction $resolveRecipients): JsonResponse
    {
        $campaign = Campaign::where('ulid', $ulid)->firstOrFail();

        abort_if(
            ! in_array($campaign->status, [CampaignStatus::Draft, CampaignStatus::Scheduled]),
            422,
            'Campaign cannot be sent in its current status.',
        );

        $resolveRecipients->execute($campaign);

        $recipientIds = $campaign->recipients()->pluck('id')->toArray();

        if (empty($recipientIds)) {
            return response()->json(['message' => 'Nenhum destinatário elegível foi encontrado para esta campanha.'], 422);
        }

        $campaign->update(['status' => CampaignStatus::Sending, 'started_at' => now()]);

        match ($campaign->channel) {
            CampaignChannel::Email => SendEmailCampaignChunkJob::dispatch($campaign->id, $recipientIds),
            CampaignChannel::Sms => SendSmsChunkJob::dispatch($campaign->id, $recipientIds),
            CampaignChannel::WhatsApp => SendWhatsAppChunkJob::dispatch($campaign->id, $recipientIds),
        };

        return response()->json(['message' => 'Campaign is being sent.']);
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
}
