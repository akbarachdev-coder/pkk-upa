<?php

namespace App\Http\Requests\Transaction;

use App\Enums\TransactionType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class StoreTransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = $this->input('type');
        $userId = $this->user()?->getKey();

        return [
            'type' => ['required', Rule::enum(TransactionType::class)],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999999999.99'],
            'transaction_date' => ['required', 'date_format:Y-m-d'],
            'wallet_id' => ['required', 'integer', $this->ownedWalletRule($userId)],
            'destination_wallet_id' => Rule::when(
                $type === TransactionType::Transfer->value,
                ['required', 'integer', 'different:wallet_id', $this->ownedWalletRule($userId)],
                ['nullable'],
            ),
            'category_id' => ['required', 'integer', $this->categoryRule($userId, $type)],
            'description' => ['nullable', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    private function ownedWalletRule(?int $userId): Exists
    {
        return Rule::exists('wallets', 'id')->where('user_id', $userId);
    }

    private function categoryRule(?int $userId, mixed $type): Exists
    {
        return Rule::exists('categories', 'id')->where(function ($query) use ($userId, $type) {
            $query->whereNull('user_id')->orWhere('user_id', $userId);

            if ($type !== TransactionType::Transfer->value) {
                $query->where('type', $type);
            }
        });
    }
}
