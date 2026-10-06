<?php

namespace App\Http\Controllers\Api;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Report\SummaryRequest;
use App\Http\Resources\Report\SummaryResource;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function summary(SummaryRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $start = $request->startDate();
        $end = $request->endDate();

        $period = $request->user()->transactions()
            ->where('transaction_date', '>=', $start)
            ->where('transaction_date', '<=', $end)
            ->whereIn('transactions.type', [TransactionType::Income->value, TransactionType::Expense->value])
            ->when(($filters['wallet_id'] ?? null) !== null, fn ($query) => $query->where('wallet_id', $filters['wallet_id']))
            ->when(($filters['category_id'] ?? null) !== null, fn ($query) => $query->where('category_id', $filters['category_id']));

        $totalIncome = round((float) (clone $period)
            ->where('transactions.type', TransactionType::Income->value)
            ->sum('transactions.amount'), 2);

        $totalExpense = round((float) (clone $period)
            ->where('transactions.type', TransactionType::Expense->value)
            ->sum('transactions.amount'), 2);

        $byCategory = (clone $period)
            ->toBase()
            ->join('categories', 'categories.id', '=', 'transactions.category_id')
            ->groupBy('transactions.category_id', 'categories.name', 'categories.type')
            ->selectRaw('transactions.category_id, categories.name, categories.type, SUM(transactions.amount) as total')
            ->get()
            ->sortByDesc('total')
            ->values()
            ->map(fn (object $row) => [
                'category_id' => $row->category_id,
                'name' => $row->name,
                'type' => $row->type,
                'total' => $this->formatAmount((float) $row->total),
            ])
            ->all();

        $data = [
            'period' => [
                'start_date' => $start,
                'end_date' => $end,
            ],
            'total_income' => $this->formatAmount($totalIncome),
            'total_expense' => $this->formatAmount($totalExpense),
            'net_cashflow' => $this->formatAmount($totalIncome - $totalExpense),
            'transaction_count' => (clone $period)->count(),
            'by_category' => $byCategory,
        ];

        return $this->successResponse(
            'Ringkasan laporan keuangan berhasil diambil.',
            new SummaryResource($data),
        );
    }

    private function formatAmount(int|float $amount): string
    {
        return number_format(round($amount, 2), 2, '.', '');
    }
}
