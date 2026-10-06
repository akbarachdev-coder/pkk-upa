<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wallet\StoreWalletRequest;
use App\Http\Requests\Wallet\UpdateWalletRequest;
use App\Http\Resources\TransactionResource;
use App\Http\Resources\WalletResource;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $wallets = $request->user()->wallets()->orderBy('name')->orderBy('id')->get();

        return $this->successResponse(
            'Daftar dompet berhasil diambil.',
            WalletResource::collection($wallets),
        );
    }

    public function store(StoreWalletRequest $request): JsonResponse
    {
        $wallet = $request->user()->wallets()->create($request->validated());

        return $this->successResponse('Dompet berhasil ditambahkan.', new WalletResource($wallet), 201);
    }

    public function show(Request $request, Wallet $wallet): JsonResponse
    {
        $this->authorizeOwnership($wallet, $request->user());

        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $transactions = $wallet->transactions()
            ->with(['category'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 15);

        $data = [
            'wallet' => (new WalletResource($wallet))->toArray($request),
            'transactions' => $this->paginatedData(
                $request,
                TransactionResource::collection($transactions),
                $transactions,
            ),
        ];

        return $this->successResponse('Detail dompet berhasil diambil.', $data);
    }

    public function update(UpdateWalletRequest $request, Wallet $wallet): JsonResponse
    {
        $this->authorizeOwnership($wallet, $request->user());

        $wallet->update($request->validated());

        return $this->successResponse('Dompet berhasil diperbarui.', new WalletResource($wallet));
    }
}
