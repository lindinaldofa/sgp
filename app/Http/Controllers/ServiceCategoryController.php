<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\ServiceCategoryResource;
use App\Models\ServiceCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServiceCategoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $categories = ServiceCategory::with('children')
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();

        return ServiceCategoryResource::collection($categories);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:7'],
            'icon' => ['nullable', 'string', 'max:50'],
            'parent_id' => ['nullable', 'integer', 'exists:service_categories,id'],
        ]);

        $category = ServiceCategory::create([
            'tenant_id' => $this->typedUser($request)->tenant_id,
            ...$validated,
        ]);

        return response()->json(new ServiceCategoryResource($category->load('children')), 201);
    }
}
