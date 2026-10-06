<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        return $this->successResponse('Registrasi berhasil.', $this->tokenPayload($user), 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->input('email'))->first();

        if ($user === null || ! Hash::check($request->input('password'), $user->password)) {
            return $this->errorResponse('Email atau password salah.', status: 401);
        }

        return $this->successResponse('Login berhasil.', $this->tokenPayload($user));
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return $this->successResponse('Berhasil logout.');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->successResponse(
            'Data pengguna berhasil diambil.',
            new UserResource($request->user()),
        );
    }

    /**
     * @return array{access_token: string, token_type: string, user: UserResource}
     */
    private function tokenPayload(User $user): array
    {
        return [
            'access_token' => $user->createToken('api')->plainTextToken,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ];
    }
}
