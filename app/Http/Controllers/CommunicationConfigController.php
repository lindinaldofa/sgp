<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CommunicationConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'daily_email_limit' => ['nullable', 'integer', 'min:0'],
            'daily_sms_limit' => ['nullable', 'integer', 'min:0'],
            'daily_whatsapp_limit' => ['nullable', 'integer', 'min:0'],
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'smtp_from_email' => ['nullable', 'email', 'max:255'],
            'smtp_from_name' => ['nullable', 'string', 'max:255'],
            'sms_api_key' => ['nullable', 'string', 'max:255'],
            'whatsapp_api_key' => ['nullable', 'string', 'max:255'],
            'whatsapp_phone_id' => ['nullable', 'string', 'max:255'],
        ]);

        $config = CommunicationConfig::updateOrCreate(
            ['tenant_id' => $this->typedUser($request)->tenant_id],
            $data,
        );

        return response()->json($config);
    }
}
