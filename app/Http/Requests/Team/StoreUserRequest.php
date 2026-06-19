<?php

declare(strict_types=1);

namespace App\Http\Requests\Team;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->role->canManageUsers();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $authUser = $this->user();
        $allowedRoles = $authUser instanceof User && $authUser->role === UserRole::Admin
            ? Rule::enum(UserRole::class)
            : Rule::in(['manager', 'assessor', 'financial', 'leader', 'readonly']);

        return [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'role'     => ['required', $allowedRoles],
            'password' => ['required', 'string', 'min:8'],
            'phone'    => ['nullable', 'string', 'max:30'],
        ];
    }
}
