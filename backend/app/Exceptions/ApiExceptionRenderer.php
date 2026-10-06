<?php

namespace App\Exceptions;

use App\Http\Controllers\Concerns\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Merender semua exception pada route api/* ke dalam envelope JSON
 * { success, message, data, errors }.
 */
class ApiExceptionRenderer
{
    use ApiResponse;

    public static function register(Exceptions $exceptions): void
    {
        $renderer = new self;

        $exceptions->render(function (Throwable $exception, Request $request) use ($renderer): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            return $renderer->render($exception, $request);
        });
    }

    public function render(Throwable $exception, Request $request): ?JsonResponse
    {
        return match (true) {
            $exception instanceof ValidationException => $this->renderValidationException($exception),
            $exception instanceof AuthenticationException => $this->errorResponse(
                'Tidak terautentikasi. Silakan login terlebih dahulu.',
                status: 401,
            ),
            $exception instanceof NotFoundHttpException => $this->errorResponse('Data tidak ditemukan.', status: 404),
            $exception instanceof MethodNotAllowedHttpException => $this->errorResponse(
                'Metode request tidak diizinkan.',
                status: 405,
            ),
            $exception instanceof AccessDeniedHttpException => $this->errorResponse(
                'Anda tidak memiliki akses untuk melakukan aksi ini.',
                status: 403,
            ),
            $exception instanceof ThrottleRequestsException => $this->errorResponse(
                'Terlalu banyak permintaan. Silakan coba lagi nanti.',
                status: 429,
            ),
            $exception instanceof QueryException => $this->serverErrorResponse($exception),
            $exception instanceof HttpExceptionInterface => $this->errorResponse(
                $exception->getMessage() !== '' ? $exception->getMessage() : 'Permintaan gagal diproses.',
                status: $exception->getStatusCode(),
            ),
            default => $this->serverErrorResponse($exception),
        };
    }

    private function renderValidationException(ValidationException $exception): JsonResponse
    {
        $errors = $exception->errors();
        $messages = Arr::flatten($errors);
        $message = count($messages) === 1 ? $messages[0] : 'Data yang diterima tidak valid.';

        return $this->errorResponse($message, $errors, 422);
    }

    private function serverErrorResponse(Throwable $exception): JsonResponse
    {
        $message = config('app.debug')
            ? $exception->getMessage()
            : 'Terjadi kesalahan pada server. Silakan coba lagi nanti.';

        return $this->errorResponse($message, status: 500);
    }
}
