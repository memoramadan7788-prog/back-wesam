<?php

namespace App\Services;

use App\Mail\EmailVerificationCodeMail;
use App\Models\EmailVerificationCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class EmailVerificationService
{
    public const CODE_TTL_MINUTES = 10;
    public const RESEND_LIMIT = 3;
    public const RESEND_DECAY_SECONDS = 60;

    public function rateLimitKey(User $user): string
    {
        return 'email-verification-resend:' . $user->id;
    }

    public function canResend(User $user): bool
    {
        return ! RateLimiter::tooManyAttempts(
            $this->rateLimitKey($user),
            self::RESEND_LIMIT
        );
    }

    public function retryAfter(User $user): int
    {
        return RateLimiter::availableIn($this->rateLimitKey($user));
    }

    public function issueAndSend(User $user, bool $enforceRateLimit = true): void
    {
        if ($enforceRateLimit && ! $this->canResend($user)) {
            return;
        }

        $code = (string) random_int(100000, 999999);

        EmailVerificationCode::where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->delete();

        EmailVerificationCode::create([
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
        ]);

        Mail::to($user->email)->send(new EmailVerificationCodeMail($code));

        if ($enforceRateLimit) {
            RateLimiter::hit($this->rateLimitKey($user), self::RESEND_DECAY_SECONDS);
        }
    }
}
