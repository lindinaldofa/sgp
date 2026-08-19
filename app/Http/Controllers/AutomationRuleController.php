<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CampaignChannel;
use App\Http\Resources\AutomationRuleResource;
use App\Models\AutomationRule;
use App\Models\Campaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class AutomationRuleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $rules = AutomationRule::with(['template', 'campaign', 'tag'])
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return AutomationRuleResource::collection($rules);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'trigger' => ['required', Rule::in(['new_contact', 'birthday'])],
            'tag_id' => ['nullable', 'integer', 'exists:tags,id'],
            'campaign_id' => ['required_without:template_id', 'nullable', 'string', 'exists:campaigns,ulid'],
            'template_id' => ['required_without:campaign_id', 'nullable', 'integer', 'exists:message_templates,id'],
            'channel' => ['required_with:template_id', 'nullable', Rule::enum(CampaignChannel::class)],
            'is_active' => ['boolean'],
            'send_time' => ['nullable', 'string'],
            'days_before' => ['nullable', 'integer'],
        ]);

        $data = $this->resolveCampaignId($data);

        $rule = AutomationRule::create($data);

        return (new AutomationRuleResource($rule->load(['template', 'campaign', 'tag'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $rule = AutomationRule::findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'trigger' => ['sometimes', 'required', Rule::in(['new_contact', 'birthday'])],
            'tag_id' => ['nullable', 'integer', 'exists:tags,id'],
            'campaign_id' => ['nullable', 'string', 'exists:campaigns,ulid'],
            'template_id' => ['nullable', 'integer', 'exists:message_templates,id'],
            'channel' => ['nullable', Rule::enum(CampaignChannel::class)],
            'is_active' => ['boolean'],
            'send_time' => ['nullable', 'string'],
            'days_before' => ['nullable', 'integer'],
        ]);

        $data = $this->resolveCampaignId($data);

        $rule->update($data);

        return response()->json(new AutomationRuleResource($rule->refresh()->load(['template', 'campaign', 'tag'])));
    }

    public function destroy(int $id): JsonResponse
    {
        AutomationRule::findOrFail($id)->delete();

        return response()->json(null, 204);
    }

    /**
     * The API always identifies campaigns by ULID; `automation_rules.campaign_id`
     * is the internal numeric FK, so translate before persisting.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function resolveCampaignId(array $data): array
    {
        if (! empty($data['campaign_id'])) {
            $data['campaign_id'] = Campaign::where('ulid', $data['campaign_id'])->value('id');
        }

        return $data;
    }
}
