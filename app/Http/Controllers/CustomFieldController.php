<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CustomFieldType;
use App\Http\Resources\CustomFieldResource;
use App\Models\CustomField;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class CustomFieldController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $fields = CustomField::query()->orderBy('sort_order')->orderBy('label')->get();

        return CustomFieldResource::collection($fields);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(CustomFieldType::class)],
            'options' => ['nullable', 'array'],
            'options.*.label' => ['required_with:options', 'string', 'max:255'],
            'options.*.value' => ['required_with:options', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $field = CustomField::create([
            'tenant_id' => $this->typedUser($request)->tenant_id,
            ...$data,
        ]);

        return response()->json(new CustomFieldResource($field), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $field = CustomField::findOrFail($id);

        $data = $request->validate([
            'label' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', Rule::enum(CustomFieldType::class)],
            'options' => ['nullable', 'array'],
            'options.*.label' => ['required_with:options', 'string', 'max:255'],
            'options.*.value' => ['required_with:options', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $field->update($data);

        return response()->json(new CustomFieldResource($field->refresh()));
    }

    public function destroy(int $id): JsonResponse
    {
        CustomField::findOrFail($id)->delete();

        return response()->json(null, 204);
    }
}
