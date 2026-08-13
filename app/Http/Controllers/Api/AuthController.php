<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->string('email'))->first();
        if (! $user || ! Hash::check($request->string('password'), $user->password)) {
            abort(422, 'Credenciais inválidas.');
        }
        if (! $user->is_active) {
            abort(403, 'Usuário inativo.');
        }

        return response()->json(['data' => ['user' => new UserResource($user->load('accessGroups.permissions')), 'token' => $user->createToken($request->input('device_name', 'angular-web'))->plainTextToken]]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('accessGroups.permissions'));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(status: 204);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink($request->only('email'));

        return response()->json(['message' => 'Se o e-mail existir, as instruções de recuperação foram enviadas.']);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate(['token' => ['required'], 'email' => ['required', 'email'], 'password' => ['required', 'confirmed', 'min:8']]);
        $status = Password::reset($request->only('email', 'password', 'password_confirmation', 'token'), fn (User $user, string $password) => $user->forceFill(['password' => $password])->save());
        abort_unless($status === Password::PASSWORD_RESET, 422, __($status));

        return response()->json(['message' => 'Senha redefinida com sucesso.']);
    }
}
