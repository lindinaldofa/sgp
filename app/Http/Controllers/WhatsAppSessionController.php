<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\WhatsAppSessionResource;
use App\Models\WhatsAppSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class WhatsAppSessionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $sessions = WhatsAppSession::query()
            ->when($request->boolean('active_only'), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->get();

        return WhatsAppSessionResource::collection($sessions);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->typedUser($request)->tenant_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'session_id' => [
                'required', 'string', 'max:100',
                Rule::unique('whatsapp_sessions', 'session_id')->where('tenant_id', $tenantId),
            ],
            'is_active' => ['boolean'],
        ]);

        $session = WhatsAppSession::create($data);

        return (new WhatsAppSessionResource($session))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, string $ulid): JsonResponse
    {
        $session = WhatsAppSession::where('ulid', $ulid)->firstOrFail();
        $tenantId = $this->typedUser($request)->tenant_id;

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'session_id' => [
                'sometimes', 'required', 'string', 'max:100',
                Rule::unique('whatsapp_sessions', 'session_id')->where('tenant_id', $tenantId)->ignore($session->id),
            ],
            'is_active' => ['boolean'],
        ]);

        $session->update($data);

        return response()->json(new WhatsAppSessionResource($session->fresh()));
    }

    public function destroy(string $ulid): JsonResponse
    {
        $session = WhatsAppSession::where('ulid', $ulid)->firstOrFail();

        abort_if(
            $session->campaigns()->exists(),
            422,
            'Esta sessão está associada a campanhas existentes e não pode ser excluída. Desative-a em vez disso.',
        );

        $session->delete();

        return response()->json(null, 204);
    }
}
