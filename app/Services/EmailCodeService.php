<?php

namespace App\Services;

use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Notifications\AuthenticationCodeNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class EmailCodeService
{
    public function send(User $user, string $purpose): void
    {
        $code = (string) random_int(100000, 999999);
        EmailVerificationCode::query()->updateOrCreate(['email' => $user->email, 'purpose' => $purpose], [
            'code_hash' => Hash::make($code), 'attempts' => 0, 'expires_at' => now()->addMinutes(10),
        ]);
        $user->notify(new AuthenticationCodeNotification($code, $purpose));
    }

    public function verify(string $email, string $purpose, string $code): void
    {
        $record = EmailVerificationCode::query()->where(compact('email', 'purpose'))->first();
        if (! $record || $record->expires_at->isPast() || $record->attempts >= 5 || ! Hash::check($code, $record->code_hash)) {
            $record?->increment('attempts');
            throw ValidationException::withMessages(['code' => 'کد تأیید اشتباه یا منقضی شده است.']);
        }
        $record->delete();
    }
}
