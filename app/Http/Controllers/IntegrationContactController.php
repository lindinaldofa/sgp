<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateContactFromIntegrationAction;
use App\Http\Resources\ContactResource;
use App\Models\IntegrationSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntegrationContactController extends Controller
{
    public function store(Request $request, CreateContactFromIntegrationAction $action): JsonResponse
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['message' => 'Missing bearer token.'], 401);
        }

        $source = IntegrationSource::findActiveByPlainToken($token);

        if (! $source) {
            return response()->json(['message' => 'Invalid or inactive integration token.'], 401);
        }

        $validated = $request->validate([
            'name' => ['required_without:email', 'nullable', 'string', 'max:255'],
            'email' => ['required_without:name', 'nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'instagram' => ['nullable', 'string', 'max:100'],
            'tiktok' => ['nullable', 'string', 'max:100'],
            'facebook' => ['nullable', 'string', 'max:150'],
            'twitter' => ['nullable', 'string', 'max:100'],
            'cpf' => ['nullable', 'string', 'max:14'],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['nullable', 'string'],
            'profession' => ['nullable', 'string', 'max:255'],
            'zip_code' => ['nullable', 'string', 'max:10'],
            'address' => ['nullable', 'string', 'max:255'],
            'address_number' => ['nullable', 'string', 'max:20'],
            'neighborhood' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:2'],
            'lgpd_consent' => ['nullable', 'boolean'],
            'custom_fields' => ['nullable', 'array'],
        ]);

        $contact = $action->execute($source, $validated);

        return (new ContactResource($contact->load('tags')))->response()->setStatusCode(201);
    }
}
