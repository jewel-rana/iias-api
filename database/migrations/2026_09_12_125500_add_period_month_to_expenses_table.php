<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->date('period_month')->nullable()->after('expense_date');
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('
                UPDATE expenses e
                INNER JOIN expense_heads h ON h.id = e.expense_head_id
                SET e.period_month = DATE_FORMAT(e.expense_date, "%Y-%m-01")
                WHERE h.kind = "salary" AND e.period_month IS NULL
            ');
        } else {
            DB::table('expenses')
                ->orderBy('id')
                ->get()
                ->each(function ($expense) {
                    if (! $expense->expense_head_id || $expense->period_month) {
                        return;
                    }
                    $kind = DB::table('expense_heads')->where('id', $expense->expense_head_id)->value('kind');
                    if ($kind !== 'salary') {
                        return;
                    }
                    $date = \Carbon\Carbon::parse($expense->expense_date)->startOfMonth()->toDateString();
                    DB::table('expenses')->where('id', $expense->id)->update(['period_month' => $date]);
                });
        }
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('period_month');
        });
    }
};
