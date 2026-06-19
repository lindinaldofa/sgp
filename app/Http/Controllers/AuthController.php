<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Credenciais inválidas.'], 401);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Conta desativada.'], 403);
        }

        if (! $user->tenant?->is_active) {
            return response()->json(['message' => 'Gabinete inativo.'], 403);
        }

        $user->update(['last_login_at' => now()]);
        $token = $user->createToken('sgp-api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user->load('tenant')),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->typedUser($request)->currentAccessToken()->delete();

        return response()->json(['message' => 'Deslogado com sucesso.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(new UserResource($this->typedUser($request)->load('tenant')));
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => 'Link de redefinição enviado.'])
            : response()->json(['message' => 'E-mail não encontrado.'], 422);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password])->save();
                $user->tokens()->delete();
            },
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => 'Senha redefinida com sucesso.'])
            : response()->json(['message' => 'Token inválido ou expirado.'], 422);
    }
}
