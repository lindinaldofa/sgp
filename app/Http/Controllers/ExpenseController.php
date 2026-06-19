<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ExpenseCategory;
use App\Enums\ExpenseStatus;
use App\Http\Resources\ExpenseDocumentResource;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Models\ExpenseDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $expenses = Expense::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            ->when($request->filled('month'), fn ($q) => $q->whereMonth('expense_date', (int) $request->month))
            ->when($request->filled('year'), fn ($q) => $q->whereYear('expense_date', (int) $request->year))
            ->latest('expense_date')
            ->paginate($request->integer('per_page', 15));

        return ExpenseResource::collection($expenses);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category'     => ['required', Rule::enum(ExpenseCategory::class)],
            'description'  => ['required', 'string', 'max:500'],
            'supplier'     => ['required', 'string', 'max:255'],
            'supplier_cnpj'=> ['nullable', 'string', 'max:18', 'regex:/^[\d.\-\/]+$/'],
            'amount'       => ['required', 'numeric', 'min:0.01'],
            'expense_date' => ['required', 'date'],
            'notes'        => ['nullable', 'string'],
        ]);

        $data['user_id'] = $this->typedUser($request)->id;
        $data['status']  = ExpenseStatus::Contracted;

        $expense = Expense::create($data);

        return (new ExpenseResource($expense))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $ulid): ExpenseResource
    {
        $expense = Expense::with(['approvedBy', 'documents'])
            ->where('ulid', $ulid)
            ->firstOrFail();

        return new ExpenseResource($expense);
    }

    public function update(Request $request, string $ulid): ExpenseResource
    {
        $expense = Expense::where('ulid', $ulid)->firstOrFail();

        abort_if($expense->status === ExpenseStatus::Paid, 422, 'Paid expenses cannot be edited.');

        $data = $request->validate([
            'category'     => ['sometimes', 'required', Rule::enum(ExpenseCategory::class)],
            'description'  => ['sometimes', 'required', 'string', 'max:500'],
            'supplier'     => ['sometimes', 'required', 'string', 'max:255'],
            'supplier_cnpj'=> ['nullable', 'string', 'max:18', 'regex:/^[\d.\-\/]+$/'],
            'amount'       => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'expense_date' => ['sometimes', 'required', 'date'],
            'notes'        => ['nullable', 'string'],
        ]);

        $expense->update($data);
        $expense->refresh()->load('documents');

        return new ExpenseResource($expense);
    }

    public function destroy(string $ulid): JsonResponse
    {
        $expense = Expense::where('ulid', $ulid)->firstOrFail();

        abort_if($expense->status === ExpenseStatus::Paid, 422, 'Paid expenses cannot be deleted.');

        foreach ($expense->documents as $doc) {
            Storage::delete($doc->path);
        }

        $expense->delete();

        return response()->json(null, 204);
    }

    public function changeStatus(Request $request, string $ulid): ExpenseResource
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(ExpenseStatus::class)],
            'notes'  => ['nullable', 'string', 'max:500'],
        ]);

        $expense   = Expense::where('ulid', $ulid)->firstOrFail();
        $newStatus = ExpenseStatus::from($data['status']);

        abort_if(
            ! in_array($newStatus, $expense->status->allowedTransitions()),
            422,
            'Transition from "'.$expense->status->label().'" to "'.$newStatus->label().'" is not allowed.',
        );

        $update = ['status' => $newStatus];

        if ($newStatus === ExpenseStatus::Paid) {
            $update['approved_by'] = $this->typedUser($request)->id;
            $update['approved_at'] = now();
        } elseif ($newStatus === ExpenseStatus::Contracted) {
            $update['approved_by'] = null;
            $update['approved_at'] = null;
        }

        if (! empty($data['notes'])) {
            $update['notes'] = $data['notes'];
        }

        $expense->update($update);

        return new ExpenseResource($expense->refresh()->load(['approvedBy', 'documents']));
    }

    public function approve(Request $request, string $ulid): ExpenseResource
    {
        $user = $this->typedUser($request);

        abort_if(
            ! $user->role?->canManageFinancial(),
            403,
            'You do not have permission to approve expenses.',
        );

        $expense = Expense::where('ulid', $ulid)->firstOrFail();

        abort_if($expense->status !== ExpenseStatus::Contracted, 422, 'Only contracted expenses can be marked as paid.');

        $expense->update([
            'status'      => ExpenseStatus::Paid,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        return new ExpenseResource($expense->refresh()->load(['approvedBy', 'documents']));
    }

    // ──── Documents ────────────────────────────────────────────────────

    public function uploadDocument(Request $request, string $ulid): ExpenseDocumentResource
    {
        $request->validate([
            'file'          => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'document_type' => ['required', 'in:receipt,invoice,nf,bill,other'],
        ]);

        $expense = Expense::where('ulid', $ulid)->firstOrFail();

        $file = $request->file('file');
        $path = $file->store("expense-docs/{$expense->ulid}", 'local');

        $doc = $expense->documents()->create([
            'document_type' => $request->input('document_type'),
            'original_name' => $file->getClientOriginalName(),
            'path'          => $path,
        ]);

        return new ExpenseDocumentResource($doc);
    }

    public function downloadDocument(string $ulid, string $docUlid): mixed
    {
        $expense = Expense::where('ulid', $ulid)->firstOrFail();
        $doc     = ExpenseDocument::where('ulid', $docUlid)
            ->where('expense_id', $expense->id)
            ->firstOrFail();

        return Storage::download($doc->path, $doc->original_name);
    }

    public function deleteDocument(string $ulid, string $docUlid): JsonResponse
    {
        $expense = Expense::where('ulid', $ulid)->firstOrFail();
        $doc     = ExpenseDocument::where('ulid', $docUlid)
            ->where('expense_id', $expense->id)
            ->firstOrFail();

        Storage::delete($doc->path);
        $doc->delete();

        return response()->json(null, 204);
    }

    // ──── Budget / Summary / Export ────────────────────────────────────

    public function summary(Request $request): JsonResponse
    {
        $request->validate([
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'year'  => ['required', 'integer'],
        ]);

        $summary = Expense::query()
            ->whereMonth('expense_date', (int) $request->month)
            ->whereYear('expense_date', (int) $request->year)
            ->selectRaw('category, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('category')
            ->get()
            ->map(fn ($row) => [
                'category' => $row->category,
                'total'    => (float) $row->total,
                'count'    => (int) $row->count,
            ]);

        return response()->json(['data' => $summary]);
    }

    public function export(Request $request): StreamedResponse
    {
        $request->validate([
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'year'  => ['nullable', 'integer'],
        ]);

        $expenses = Expense::query()
            ->when($request->filled('month'), fn ($q) => $q->whereMonth('expense_date', (int) $request->month))
            ->when($request->filled('year'), fn ($q) => $q->whereYear('expense_date', (int) $request->year))
            ->orderBy('expense_date')
            ->get();

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="despesas.csv"',
        ];

        return response()->stream(function () use ($expenses): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                throw new \RuntimeException('Failed to open output stream');
            }

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['data', 'descrição', 'fornecedor', 'CNPJ', 'valor', 'categoria', 'status'], ';');

            foreach ($expenses as $expense) {
                fputcsv($handle, [
                    $expense->expense_date?->toDateString(),
                    $expense->description,
                    $expense->supplier,
                    $expense->supplier_cnpj,
                    number_format((float) $expense->amount, 2, ',', '.'),
                    $expense->category?->label(),
                    $expense->status?->label(),
                ], ';');
            }

            fclose($handle);
        }, 200, $headers);
    }
}
