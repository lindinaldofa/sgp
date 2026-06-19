<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CampaignChannel;
use App\Models\MessageTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MessageTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $templates = MessageTemplate::query()
            ->when($request->filled('channel'), fn ($q) => $q->where('channel', $request->channel))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json($templates);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'channel' => ['required', Rule::enum(CampaignChannel::class)],
            'body_text' => ['required', 'string'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body_html' => ['nullable', 'string'],
            'variables' => ['nullable', 'array'],
        ]);

        $template = MessageTemplate::create($data);

        return response()->json($template, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $template = MessageTemplate::findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'channel' => ['sometimes', 'required', Rule::enum(CampaignChannel::class)],
            'body_text' => ['sometimes', 'required', 'string'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body_html' => ['nullable', 'string'],
            'variables' => ['nullable', 'array'],
        ]);

        $template->update($data);

        return response()->json($template->fresh());
    }

    public function destroy(int $id): JsonResponse
    {
        $template = MessageTemplate::findOrFail($id);

        abort_if((bool) $template->is_system, 422, 'System templates cannot be deleted.');

        $template->delete();

        return response()->json(null, 204);
    }
}
