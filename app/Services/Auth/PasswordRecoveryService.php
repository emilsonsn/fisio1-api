<?php

namespace App\Services\Auth;

use App\Models\PasswordResetCode;
use App\Models\User;
use App\Notifications\PasswordRecoveryCodeNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class PasswordRecoveryService
{
    public function sendCode(string $email): ?User
    {
        $user = $this->activeUser($email);
        if (! $user) {
            return null;
        }

        $expiresInMinutes = max(1, (int) config('password_recovery.code_expire_minutes'));
        $code = $this->generateCode();

        Password::broker()->deleteToken($user);

        PasswordResetCode::query()->updateOrCreate(
            ['email' => $user->email],
            [
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'expires_at' => now()->addMinutes($expiresInMinutes),
                'consumed_at' => null,
            ],
        );

        $user->notify(new PasswordRecoveryCodeNotification($code, $expiresInMinutes));

        return $user;
    }

    /** @return array{user: User, token: string, expires_in: int} */
    public function verifyCode(string $email, string $code): array
    {
        $user = $this->activeUser($email);

        if (! $user) {
            $this->invalidCode();
        }

        $verified = DB::transaction(function () use ($user, $code): bool {
            $resetCode = PasswordResetCode::query()
                ->where('email', $user->email)
                ->lockForUpdate()
                ->first();

            if (
                ! $resetCode
                || $resetCode->consumed_at
                || $resetCode->expires_at->isPast()
                || $resetCode->attempts >= $this->maxAttempts()
            ) {
                return false;
            }

            if (! Hash::check($code, $resetCode->code_hash)) {
                $resetCode->increment('attempts');

                return false;
            }

            $resetCode->forceFill(['consumed_at' => now()])->save();

            return true;
        });

        if (! $verified) {
            $this->invalidCode();
        }

        return [
            'user' => $user,
            'token' => Password::broker()->createToken($user),
            'expires_in' => (int) config('auth.passwords.users.expire', 60) * 60,
        ];
    }

    private function activeUser(string $email): ?User
    {
        return User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
            ->where('is_active', true)
            ->first();
    }

    private function generateCode(): string
    {
        $length = max(4, min(9, (int) config('password_recovery.code_length')));
        $maximum = (10 ** $length) - 1;

        return str_pad((string) random_int(0, $maximum), $length, '0', STR_PAD_LEFT);
    }

    private function maxAttempts(): int
    {
        return max(1, (int) config('password_recovery.max_attempts'));
    }

    private function invalidCode(): never
    {
        throw ValidationException::withMessages([
            'code' => 'Código inválido, expirado ou com tentativas excedidas.',
        ]);
    }
}
