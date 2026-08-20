<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditEventCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RequestPasswordRecoveryCodeRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyPasswordRecoveryCodeRequest;
use App\Http\Resources\UserResource;
use App\Models\PasswordResetCode;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\PasswordRecoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PasswordRecoveryService $passwordRecovery,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->string('email'))->first();
        if (! $user || ! Hash::check($request->string('password'), $user->password)) {
            $this->audit->record(AuditEventCategory::LoginFailed, metadata: [
                'attempted_email' => $request->string('email')->toString(),
                'reason' => 'invalid_credentials',
            ]);
            abort(422, 'Credenciais inválidas.');
        }
        if (! $user->is_active) {
            $this->audit->record(AuditEventCategory::LoginFailed, $user, metadata: ['reason' => 'inactive_user']);
            abort(403, 'Usuário inativo.');
        }

        $this->audit->record(AuditEventCategory::Login, $user, user: $user);

        return response()->json(['data' => ['user' => new UserResource($user->load('accessGroups.permissions')), 'token' => $user->createToken($request->input('device_name', 'angular-web'))->plainTextToken]]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('accessGroups.permissions'));
    }

    public function logout(Request $request): JsonResponse
    {
        $this->audit->record(AuditEventCategory::Logout, $request->user());
        $request->user()->currentAccessToken()?->delete();

        return response()->json(status: 204);
    }

    public function forgotPassword(RequestPasswordRecoveryCodeRequest $request): JsonResponse
    {
        $email = $request->string('email')->toString();

        if ($request->isHoneypotTriggered()) {
            $this->audit->record(AuditEventCategory::PasswordResetRequested, metadata: [
                'attempted_email' => $email,
                'blocked_by_honeypot' => true,
            ]);

            return $this->recoveryCodeRequestedResponse();
        }

        $user = $this->passwordRecovery->sendCode($email);
        $this->audit->record(AuditEventCategory::PasswordResetRequested, $user, metadata: [
            'attempted_email' => $email,
        ]);

        return $this->recoveryCodeRequestedResponse();
    }

    public function verifyPasswordRecoveryCode(VerifyPasswordRecoveryCodeRequest $request): JsonResponse
    {
        $email = $request->string('email')->toString();

        try {
            $verified = $this->passwordRecovery->verifyCode(
                $email,
                $request->string('code')->toString(),
            );
        } catch (ValidationException $exception) {
            $this->audit->record(AuditEventCategory::PasswordResetVerificationFailed, metadata: [
                'attempted_email' => $email,
            ]);

            throw $exception;
        }

        $this->audit->record(AuditEventCategory::PasswordResetCodeVerified, $verified['user']);

        return response()->json(['data' => [
            'email' => $verified['user']->email,
            'reset_token' => $verified['token'],
            'expires_in' => $verified['expires_in'],
        ]]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->safe()->only(['email', 'password', 'password_confirmation', 'token']),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();
                $user->tokens()->delete();
                PasswordResetCode::query()->where('email', $user->email)->delete();
                $this->audit->record(
                    AuditEventCategory::PasswordReset,
                    $user,
                    newValues: ['password' => '[updated]'],
                );
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'token' => 'Token de redefinição inválido ou expirado.',
            ]);
        }

        return response()->json(['message' => 'Senha redefinida com sucesso.']);
    }

    private function recoveryCodeRequestedResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Se o e-mail estiver cadastrado, enviaremos um código de recuperação.',
        ]);
    }
}
