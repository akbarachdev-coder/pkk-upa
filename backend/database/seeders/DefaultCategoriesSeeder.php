<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class DefaultCategoriesSeeder extends Seeder
{
    /**
     * Kategori bawaan (global) yang dapat digunakan oleh semua pengguna.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Gaji', 'type' => 'income'],
            ['name' => 'Bonus', 'type' => 'income'],
            ['name' => 'Investasi', 'type' => 'income'],
            ['name' => 'Pemasukan Lain', 'type' => 'income'],
            ['name' => 'Operasional', 'type' => 'expense'],
            ['name' => 'Transportasi', 'type' => 'expense'],
            ['name' => 'Makan & Minum', 'type' => 'expense'],
            ['name' => 'Belanja', 'type' => 'expense'],
            ['name' => 'Tagihan', 'type' => 'expense'],
            ['name' => 'Pengeluaran Lain', 'type' => 'expense'],
        ];

        foreach ($categories as $category) {
            Category::query()->firstOrCreate([
                'user_id' => null,
                'name' => $category['name'],
                'type' => $category['type'],
            ]);
        }
    }
}
