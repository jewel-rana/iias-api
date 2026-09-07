<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseHead;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $query = Expense::query()
            ->with('head')
            ->latest('expense_date')
            ->latest('id');

        if ($request->filled('recurrence')) {
            $query->where('recurrence', $request->string('recurrence'));
        }

        if ($request->filled('kind')) {
            $query->whereHas('head', fn ($q) => $q->where('kind', $request->string('kind')));
        }

        if ($request->filled('expense_head_id')) {
            $query->where('expense_head_id', $request->integer('expense_head_id'));
        }

        $expenses = $query->limit(200)->get();

        return response()->json([
            'data' => $expenses->map(fn (Expense $e) => $this->transform($e)),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'expense_head_id' => ['required', 'exists:expense_heads,id'],
            'recurrence' => ['required', 'string', 'in:monthly,occasional'],
            'amount' => ['required', 'integer', 'min:1'],
            'expense_date' => ['required', 'date'],
            'payment_method' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $head = ExpenseHead::query()->findOrFail($data['expense_head_id']);

        $expense = Expense::query()->create([
            'title' => $data['title'],
            'expense_head_id' => $head->id,
            'category' => $head->code,
            'recurrence' => $data['recurrence'],
            'amount' => $data['amount'],
            'expense_date' => $data['expense_date'],
            'payment_method' => $data['payment_method'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $request->user()?->id,
        ]);

        return response()->json($this->transform($expense->load('head')), 201);
    }

    private function transform(Expense $e): array
    {
        return [
            'id' => (string) $e->id,
            'title' => $e->title,
            'expense_head_id' => $e->expense_head_id ? (string) $e->expense_head_id : null,
            'head_name' => $e->head?->name ?? $e->category,
            'head_kind' => $e->head?->kind ?? 'other',
            'category' => $e->category,
            'recurrence' => $e->recurrence,
            'amount' => $e->amount,
            'expense_date' => $e->expense_date?->toDateString(),
            'payment_method' => $e->payment_method,
            'notes' => $e->notes,
        ];
    }
}
