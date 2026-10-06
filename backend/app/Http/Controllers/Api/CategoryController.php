<?php

namespace App\Http\Controllers\Api;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', Rule::enum(TransactionType::class)->only([
                TransactionType::Income,
                TransactionType::Expense,
            ])],
        ]);

        $type = $validated['type'] ?? null;
        $userId = $request->user()->getKey();

        $categories = Category::query()
            ->where(function ($query) use ($userId) {
                $query->whereNull('user_id')->orWhere('user_id', $userId);
            })
            ->when($type !== null, fn ($query) => $query->where('type', $type))
            ->orderBy('type')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $this->successResponse(
            'Daftar kategori berhasil diambil.',
            CategoryResource::collection($categories),
        );
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $request->user()->categories()->create($request->validated());

        return $this->successResponse('Kategori berhasil ditambahkan.', new CategoryResource($category), 201);
    }
}
