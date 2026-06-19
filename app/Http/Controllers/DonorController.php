<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Donor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DonorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $donors = Donor::with('contact')
            ->latest('donated_at')
            ->paginate($request->integer('per_page', 15));

        return response()->json($donors);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'donated_at' => ['required', 'date'],
            'cpf_cnpj' => ['nullable', 'string', 'max:18'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'campaign' => ['nullable', 'string', 'max:255'],
            'receipt_issued' => ['boolean'],
        ]);

        $donor = Donor::create($data);

        return response()->json($donor, 201);
    }

    public function destroy(int $id): JsonResponse
    {
        $donor = Donor::findOrFail($id);

        $donor->delete();

        return response()->json(null, 204);
    }
}
