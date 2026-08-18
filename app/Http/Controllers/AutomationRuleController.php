<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CampaignChannel;
use App\Models\AutomationRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AutomationRuleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $rules = AutomationRule::with('template')
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json($rules);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'trigger' => ['required', 'string', 'max:255'],
            'tag_id' => ['nullable', 'integer', 'exists:tags,id'],
            'channel' => ['required', Rule::enum(CampaignChannel::class)],
            'template_id' => ['required', 'integer', 'exists:message_templates,id'],
            'is_active' => ['boolean'],
            'send_time' => ['nullable', 'string'],
            'days_before' => ['nullable', 'integer'],
        ]);

        $rule = AutomationRule::create($data);

        return response()->json($rule->load('template'), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $rule = AutomationRule::findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'trigger' => ['sometimes', 'required', 'string', 'max:255'],
            'tag_id' => ['nullable', 'integer', 'exists:tags,id'],
            'channel' => ['sometimes', 'required', Rule::enum(CampaignChannel::class)],
            'template_id' => ['sometimes', 'required', 'integer', 'exists:message_templates,id'],
            'is_active' => ['boolean'],
            'send_time' => ['nullable', 'string'],
            'days_before' => ['nullable', 'integer'],
        ]);

        $rule->update($data);

        return response()->json($rule->refresh()->load('template'));
    }
}
