<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Bentuk envelope response API: { success, message, data, errors }.
 */
trait ApiResponse
{
    protected function successResponse(string $message, mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'errors' => (object) [],
        ], $status);
    }

    protected function errorResponse(string $message, array $errors = [], int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => $errors === [] ? (object) [] : $errors,
        ], $status);
    }

    /**
     * @return array{items: array<int, mixed>, pagination: array<string, int|null>}
     */
    protected function paginatedData(Request $request, ResourceCollection $collection, LengthAwarePaginator $paginator): array
    {
        return [
            'items' => $collection->resolve($request),
            'pagination' => $this->paginationMeta($paginator),
        ];
    }

    /**
     * @return array{current_page: int, per_page: int, total: int, last_page: int, from: int|null, to: int|null}
     */
    protected function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }
}
