<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExpenseHead;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ExpenseHeadController extends Controller
{
    public function index(Request $request)
    {
        $query = ExpenseHead::query()->orderBy('sort_order')->orderBy('name');

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        if ($request->filled('kind')) {
            $query->where('kind', $request->string('kind'));
        }

        return response()->json([
            'data' => $query->get()->map(fn (ExpenseHead $h) => $this->transform($h)),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:80', 'unique:expense_heads,code'],
            'kind' => ['required', 'string', 'in:salary,festival_bonus,operational,charity,other'],
            'default_recurrence' => ['required', 'string', 'in:monthly,occasional'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $code = $data['code'] ?? Str::slug($data['name'], '_');
        if ($code === '') {
            $code = 'head_'.Str::lower(Str::random(6));
        }

        $head = ExpenseHead::query()->create([
            'name' => $data['name'],
            'code' => $code,
            'kind' => $data['kind'],
            'default_recurrence' => $data['default_recurrence'],
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? ((int) ExpenseHead::query()->max('sort_order') + 1),
        ]);

        return response()->json($this->transform($head), 201);
    }

    public function update(Request $request, ExpenseHead $expenseHead)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique('expense_heads', 'code')->ignore($expenseHead->id),
            ],
            'kind' => ['required', 'string', 'in:salary,festival_bonus,operational,charity,other'],
            'default_recurrence' => ['required', 'string', 'in:monthly,occasional'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $expenseHead->update([
            'name' => $data['name'],
            'code' => $data['code'] ?? $expenseHead->code,
            'kind' => $data['kind'],
            'default_recurrence' => $data['default_recurrence'],
            'is_active' => $data['is_active'],
            'sort_order' => $data['sort_order'] ?? $expenseHead->sort_order,
        ]);

        return response()->json($this->transform($expenseHead->fresh()));
    }

    public function destroy(ExpenseHead $expenseHead)
    {
        if ($expenseHead->expenses()->exists()) {
            $expenseHead->update(['is_active' => false]);

            return response()->json([
                'message' => 'Head is in use, marked inactive instead of deleted.',
                'data' => $this->transform($expenseHead->fresh()),
            ]);
        }

        $expenseHead->delete();

        return response()->json(['message' => 'Deleted']);
    }

    private function transform(ExpenseHead $h): array
    {
        return [
            'id' => (string) $h->id,
            'name' => $h->name,
            'code' => $h->code,
            'kind' => $h->kind,
            'default_recurrence' => $h->default_recurrence,
            'is_active' => $h->is_active,
            'sort_order' => $h->sort_order,
        ];
    }
}
