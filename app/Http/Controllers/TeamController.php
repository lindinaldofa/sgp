<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateUserAction;
use App\Actions\DeactivateUserAction;
use App\Data\CreateUserData;
use App\Enums\UserRole;
use App\Http\Requests\Team\StoreUserRequest;
use App\Http\Requests\Team\UpdateUserRequest;
use App\Http\Resources\AuditLogResource;
use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class TeamController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $users = User::where('tenant_id', $this->typedUser($request)->tenant_id)
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->when($request->role, fn ($q) => $q->where('role', $request->role))
            ->orderBy('name')
            ->paginate(30);

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request, CreateUserAction $action): JsonResponse
    {
        $user = $action->execute(new CreateUserData(
            tenantId: $this->typedUser($request)->tenant_id,
            name: $request->name,
            email: $request->email,
            role: $request->enum('role', UserRole::class) ?? throw new \UnexpectedValueException('Role is required'),
            password: $request->string('password')->toString(),
        ));

        return response()->json(new UserResource($user), 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = User::where('tenant_id', $this->typedUser($request)->tenant_id)->findOrFail($id);

        return response()->json(new UserResource($user));
    }

    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        $user = User::where('tenant_id', $this->typedUser($request)->tenant_id)->findOrFail($id);

        $data = $request->validated();

        if (isset($data['password']) && $data['password'] !== null && $data['password'] !== '') {
            $data['password'] = \Illuminate\Support\Facades\Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return response()->json(new UserResource($user));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = User::where('tenant_id', $this->typedUser($request)->tenant_id)->findOrFail($id);

        if ($user->id === $this->typedUser($request)->id) {
            return response()->json(['message' => 'Não é possível remover a própria conta.'], 422);
        }

        $user->delete();

        return response()->json(null, 204);
    }

    public function activate(Request $request, int $id): JsonResponse
    {
        $user = User::where('tenant_id', $this->typedUser($request)->tenant_id)->findOrFail($id);
        $user->update(['is_active' => true]);

        return response()->json(new UserResource($user));
    }

    public function deactivate(Request $request, int $id, DeactivateUserAction $action): JsonResponse
    {
        $user = User::where('tenant_id', $this->typedUser($request)->tenant_id)->findOrFail($id);
        $action->execute($user);

        return response()->json(new UserResource($user->fresh()));
    }

    public function auditLogs(Request $request): AnonymousResourceCollection
    {
        $logs = AuditLog::where('tenant_id', $this->typedUser($request)->tenant_id)
            ->with('user')
            ->latest()
            ->paginate(50);

        return AuditLogResource::collection($logs);
    }

    public function settings(Request $request): JsonResponse
    {
        $tenant   = $this->typedUser($request)->tenant;
        $settings = $tenant?->settings ?? [];

        return response()->json(array_merge($settings, [
            'name'          => $tenant?->name,
            'slug'          => $tenant?->slug,
            'plan'          => $tenant?->plan,
            'is_active'     => $tenant?->is_active,
            'storage_used'  => $tenant?->storage_used,
            'storage_limit' => $tenant?->storage_limit,
            'logo_path'     => $tenant?->logo_path
                ? Storage::url($tenant->logo_path)
                : null,
        ]));
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $tenant = $this->typedUser($request)->tenant;

        if ($tenant && $request->has('name')) {
            $tenant->update(['name' => $request->input('name')]);
        }

        $extra = $request->except(['name', 'logo_path']);
        if ($tenant && !empty($extra)) {
            $tenant->update(['settings' => array_merge($tenant->settings ?? [], $extra)]);
        }

        $tenant?->refresh();
        $settings = $tenant?->settings ?? [];

        return response()->json(array_merge($settings, [
            'name'          => $tenant?->name,
            'slug'          => $tenant?->slug,
            'plan'          => $tenant?->plan,
            'is_active'     => $tenant?->is_active,
            'storage_used'  => $tenant?->storage_used,
            'storage_limit' => $tenant?->storage_limit,
            'logo_path'     => $tenant?->logo_path
                ? Storage::url($tenant->logo_path)
                : null,
        ]));
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
        ]);

        $tenant = $this->typedUser($request)->tenant;

        if (!$tenant) {
            return response()->json(['message' => 'Tenant não encontrado.'], 404);
        }

        // Remove old logo
        if ($tenant->logo_path) {
            Storage::disk('public')->delete($tenant->logo_path);
        }

        $path = $request->file('logo')->store("logos/tenant-{$tenant->id}", 'public');
        $tenant->update(['logo_path' => $path]);

        return response()->json(['logo_path' => Storage::url((string) $path)]);
    }

    public function deleteLogo(Request $request): JsonResponse
    {
        $tenant = $this->typedUser($request)->tenant;

        if ($tenant?->logo_path) {
            Storage::disk('public')->delete($tenant->logo_path);
            $tenant->update(['logo_path' => null]);
        }

        return response()->json(null, 204);
    }
}
