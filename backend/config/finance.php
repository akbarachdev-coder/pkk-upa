<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Negative Balance
    |--------------------------------------------------------------------------
    |
    | Ketika false, transaksi pengeluaran (atau transfer) yang membuat saldo
    | dompet menjadi negatif akan ditolak oleh sistem.
    |
    */

    'allow_negative_balance' => (bool) env('FINANCE_ALLOW_NEGATIVE_BALANCE', false),

    /*
    |--------------------------------------------------------------------------
    | Attachment Disk
    |--------------------------------------------------------------------------
    |
    | Disk yang digunakan untuk menyimpan lampiran transaksi seperti bukti
    | transfer atau nota pembelian.
    |
    */

    'attachment_disk' => env('FINANCE_ATTACHMENT_DISK', 'public'),

];
