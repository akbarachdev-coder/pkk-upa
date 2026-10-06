<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\IndexTransactionRequest;
use App\Http\Requests\Transaction\StoreTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TransactionController extends Controller
{
    public function index(IndexTransactionRequest $request): JsonResponse
    {
        $filters = $request->validated();

        $transactions = $request->user()->transactions()
            ->with(['wallet', 'destinationWallet', 'category'])
            ->when(($filters['start_date'] ?? null) !== null, fn ($query) => $query->where('transaction_date', '>=', $filters['start_date']))
            ->when(($filters['end_date'] ?? null) !== null, fn ($query) => $query->where('transaction_date', '<=', $filters['end_date']))
            ->when(($filters['type'] ?? null) !== null, fn ($query) => $query->where('type', $filters['type']))
            ->when(($filters['wallet_id'] ?? null) !== null, fn ($query) => $query->where('wallet_id', $filters['wallet_id']))
            ->when(($filters['category_id'] ?? null) !== null, fn ($query) => $query->where('category_id', $filters['category_id']))
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate($request->perPage());

        return $this->successResponse(
            'Riwayat transaksi berhasil diambil.',
            $this->paginatedData($request, TransactionResource::collection($transactions), $transactions),
        );
    }

    public function store(StoreTransactionRequest $request, TransactionService $transactions): JsonResponse
    {
        $data = $request->validated();
        unset($data['attachment']);

        $attachment = $request->file('attachment');

        if ($attachment !== null) {
            $path = $attachment->store('attachments', config('finance.attachment_disk'));

            if ($path === false) {
                throw ValidationException::withMessages([
                    'attachment' => 'Lampiran gagal diunggah.',
                ]);
            }

            $data['attachment_path'] = $path;
        }

        $transaction = $transactions->create($request->user(), $data);
        $transaction->loadMissing(['wallet', 'destinationWallet', 'category']);

        return $this->successResponse(
            'Transaksi berhasil dicatat.',
            new TransactionResource($transaction),
            201,
        );
    }

    public function destroy(Request $request, Transaction $transaction, TransactionService $transactions): JsonResponse
    {
        $this->authorizeOwnership($transaction, $request->user());

        $transactions->delete($transaction);

        return $this->successResponse('Transaksi berhasil dihapus.');
    }
}
