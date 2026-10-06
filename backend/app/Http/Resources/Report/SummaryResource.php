<?php

namespace App\Http\Resources\Report;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{period: array{start_date: string, end_date: string}, total_income: string, total_expense: string, net_cashflow: string, transaction_count: int, by_category: array<int, mixed>} $resource
 */
class SummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'period' => $this->resource['period'],
            'total_income' => $this->resource['total_income'],
            'total_expense' => $this->resource['total_expense'],
            'net_cashflow' => $this->resource['net_cashflow'],
            'transaction_count' => $this->resource['transaction_count'],
            'by_category' => $this->resource['by_category'],
        ];
    }
}
