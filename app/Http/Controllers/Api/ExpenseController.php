<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseHead;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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

    public function salaryDues(Request $request)
    {
        $request->validate([
            'expense_head_id' => ['nullable', 'exists:expense_heads,id'],
        ]);

        $heads = ExpenseHead::query()
            ->where('kind', 'salary')
            ->where('is_active', true)
            ->when(
                $request->filled('expense_head_id'),
                fn ($q) => $q->where('id', $request->integer('expense_head_id'))
            )
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $now = now()->startOfMonth();
        $data = $heads->map(function (ExpenseHead $head) use ($now) {
            $start = Carbon::parse($head->created_at)->startOfMonth();
            $cap = $now->copy()->subMonths(23);
            if ($start->lt($cap)) {
                $start = $cap;
            }

            $paid = Expense::query()
                ->where('expense_head_id', $head->id)
                ->whereNotNull('period_month')
                ->get()
                ->keyBy(fn (Expense $e) => Carbon::parse($e->period_month)->startOfMonth()->toDateString());

            $periods = [];
            for ($month = $start->copy(); $month->lte($now); $month->addMonth()) {
                $key = $month->toDateString();
                $expense = $paid->get($key);
                $periods[] = [
                    'month' => $key,
                    'status' => $expense ? 'paid' : 'due',
                    'expense_id' => $expense ? (string) $expense->id : null,
                    'amount' => $expense?->amount,
                ];
            }

            return [
                'expense_head_id' => (string) $head->id,
                'head_name' => $head->name,
                'due_count' => collect($periods)->where('status', 'due')->count(),
                'periods' => $periods,
            ];
        });

        return response()->json(['data' => $data->values()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'expense_head_id' => ['required', 'exists:expense_heads,id'],
            'recurrence' => ['required', 'string', 'in:monthly,occasional'],
            'amount' => ['required', 'integer', 'min:1'],
            'expense_date' => ['required', 'date'],
            'period_month' => ['nullable', 'date'],
            'payment_method' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $head = ExpenseHead::query()->findOrFail($data['expense_head_id']);
        $periodMonth = null;

        if ($head->kind === 'salary') {
            $period = Carbon::parse($data['period_month'] ?? $data['expense_date'])->startOfMonth();
            $periodMonth = $period->toDateString();

            $alreadyPaid = Expense::query()
                ->where('expense_head_id', $head->id)
                ->whereDate('period_month', $periodMonth)
                ->exists();

            if ($alreadyPaid) {
                throw ValidationException::withMessages([
                    'period_month' => ['Salary for '.$period->format('F Y').' is already paid for '.$head->name.'.'],
                ]);
            }
        }

        $expense = Expense::query()->create([
            'title' => $data['title'],
            'expense_head_id' => $head->id,
            'category' => $head->code,
            'recurrence' => $data['recurrence'],
            'amount' => $data['amount'],
            'expense_date' => $data['expense_date'],
            'period_month' => $periodMonth,
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
            'period_month' => $e->period_month?->toDateString(),
            'payment_method' => $e->payment_method,
            'notes' => $e->notes,
        ];
    }
}
