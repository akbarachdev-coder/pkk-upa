<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class TransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'wallet_id' => $this->wallet_id,
            'destination_wallet_id' => $this->destination_wallet_id,
            'category_id' => $this->category_id,
            'type' => $this->type?->value,
            'amount' => $this->amount,
            'transaction_date' => $this->transaction_date?->format('Y-m-d'),
            'description' => $this->description,
            'attachment_path' => $this->attachment_path,
            'attachment_url' => $this->attachmentUrl(),
            'wallet' => $this->relationSummary('wallet'),
            'destination_wallet' => $this->relationSummary('destinationWallet'),
            'category' => $this->relationSummary('category'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function relationSummary(string $relation): ?array
    {
        $related = $this->getRelationValue($relation);

        if ($related === null) {
            return null;
        }

        return [
            'id' => $related->id,
            'name' => $related->name,
        ];
    }

    private function attachmentUrl(): ?string
    {
        if ($this->attachment_path === null) {
            return null;
        }

        return Storage::disk(config('finance.attachment_disk'))->url($this->attachment_path);
    }
}
