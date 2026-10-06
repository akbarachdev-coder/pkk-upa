<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Satu-satunya tempat saldo dompet (wallets.balance) diubah.
 *
 * Setiap mutasi saldo dibungkus DB::transaction dan mengunci baris dompet
 * (lockForUpdate) agar aman dari race condition pada request bersamaan.
 */
class TransactionService
{
    /**
     * @param  array{
     *     type: string,
     *     amount: int|float|string,
     *     transaction_date: string,
     *     wallet_id: int,
     *     category_id: int,
     *     destination_wallet_id?: int|null,
     *     description?: string|null,
     *     attachment_path?: string|null,
     * }  $data
     */
    public function create(User $user, array $data): Transaction
    {
        return DB::transaction(function () use ($user, $data) {
            $type = TransactionType::from($data['type']);
            $amount = $this->normalizeAmount($data['amount']);

            $wallet = $this->lockWallet($user->getKey(), (int) $data['wallet_id'], 'wallet_id');

            $destinationWallet = null;

            if ($type === TransactionType::Transfer) {
                $destinationWallet = $this->lockWallet(
                    $user->getKey(),
                    (int) $data['destination_wallet_id'],
                    'destination_wallet_id',
                );
            }

            if ($type !== TransactionType::Income) {
                $this->guardSufficientBalance($wallet, $amount, 'amount');
            }

            $transaction = $user->transactions()->create([
                'type' => $type->value,
                'amount' => $amount,
                'transaction_date' => $data['transaction_date'],
                'description' => $data['description'] ?? null,
                'attachment_path' => $data['attachment_path'] ?? null,
                'wallet_id' => $wallet->getKey(),
                'destination_wallet_id' => $destinationWallet?->getKey(),
                'category_id' => (int) $data['category_id'],
            ]);

            if ($type === TransactionType::Income) {
                $this->increaseBalance($wallet, $amount);
            }

            if ($type === TransactionType::Expense) {
                $this->decreaseBalance($wallet, $amount);
            }

            if ($type === TransactionType::Transfer) {
                $this->decreaseBalance($wallet, $amount);
                $this->increaseBalance($destinationWallet, $amount);
            }

            return $transaction;
        });
    }

    /**
     * Menghapus transaksi dan mengembalikan mutasi saldo dompetnya.
     */
    public function delete(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $type = $transaction->type;
            $amount = $this->normalizeAmount($transaction->amount);

            $wallet = $this->lockWallet($transaction->user_id, $transaction->wallet_id, 'wallet_id');
            $destinationWallet = $transaction->destination_wallet_id !== null
                ? $this->lockWallet(
                    $transaction->user_id,
                    $transaction->destination_wallet_id,
                    'destination_wallet_id',
                )
                : null;

            if ($type === TransactionType::Income) {
                $this->guardSufficientBalance($wallet, $amount, 'amount');
                $this->decreaseBalance($wallet, $amount);
            }

            if ($type === TransactionType::Expense) {
                $this->increaseBalance($wallet, $amount);
            }

            if ($type === TransactionType::Transfer) {
                $this->increaseBalance($wallet, $amount);

                if ($destinationWallet !== null) {
                    $this->guardSufficientBalance($destinationWallet, $amount, 'amount');
                    $this->decreaseBalance($destinationWallet, $amount);
                }
            }

            $this->deleteAttachment($transaction);
            $transaction->delete();
        });
    }

    private function lockWallet(int $userId, int $walletId, string $attribute): Wallet
    {
        $wallet = Wallet::query()
            ->whereKey($walletId)
            ->where('user_id', $userId)
            ->lockForUpdate()
            ->first();

        if ($wallet === null) {
            throw ValidationException::withMessages([
                $attribute => 'Dompet tidak ditemukan.',
            ]);
        }

        return $wallet;
    }

    private function guardSufficientBalance(Wallet $wallet, float $amount, string $attribute): void
    {
        if (config('finance.allow_negative_balance')) {
            return;
        }

        if (round((float) $wallet->balance, 2) < $amount) {
            throw ValidationException::withMessages([
                $attribute => sprintf(
                    'Saldo dompet "%s" tidak mencukupi. Saldo tersedia: %s.',
                    $wallet->name,
                    number_format((float) $wallet->balance, 2, ',', '.'),
                ),
            ]);
        }
    }

    private function increaseBalance(Wallet $wallet, float $amount): void
    {
        $wallet->balance = round((float) $wallet->balance + $amount, 2);
        $wallet->save();
    }

    private function decreaseBalance(Wallet $wallet, float $amount): void
    {
        $wallet->balance = round((float) $wallet->balance - $amount, 2);
        $wallet->save();
    }

    private function deleteAttachment(Transaction $transaction): void
    {
        if ($transaction->attachment_path === null) {
            return;
        }

        Storage::disk(config('finance.attachment_disk'))->delete($transaction->attachment_path);
    }

    private function normalizeAmount(int|float|string $amount): float
    {
        return round((float) $amount, 2);
    }
}
