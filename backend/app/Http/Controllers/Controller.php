<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Model;

abstract class Controller
{
    use ApiResponse;

    /**
     * Membatasi akses hanya untuk pemilik data. Record milik pengguna lain
     * dilaporkan sebagai 404 agar keberadaannya tidak bocor.
     *
     * @param  Wallet|Category|Transaction  $model
     */
    protected function authorizeOwnership(Model $model, User $user): void
    {
        abort_unless($model->isOwnedBy($user), 404);
    }
}
