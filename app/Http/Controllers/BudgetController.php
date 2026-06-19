<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BudgetAllowance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BudgetController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $budget = BudgetAllowance::where('tenant_id', $this->typedUser($request)->tenant_id)
            ->where('year', now()->year)
            ->first();

        return response()->json($budget);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'total_monthly' => ['required', 'numeric', 'min:0'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $budget = BudgetAllowance::updateOrCreate(
            [
                'tenant_id' => $this->typedUser($request)->tenant_id,
                'year' => $data['year'],
            ],
            [
                'total_monthly' => $data['total_monthly'],
                'updated_at' => now(),
            ],
        );

        return response()->json($budget);
    }
}
