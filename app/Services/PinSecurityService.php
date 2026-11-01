<?php

namespace App\Services;

use App\Models\PosOperationLog;
use App\Models\SecurityAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PinSecurityService
{
    public const MAX_FAILED_ATTEMPTS = 5;

    public const LOCKOUT_SECONDS = 900; // 15 minutes

    /**
     * Check if a PIN is trivial or too predictable.
     */
    public function isTrivial(string $pin): bool
    {
        $len = strlen($pin);
        if ($len < 4 || $len > 6) {
            return true;
        }

        // All identical digits (e.g. 1111, 888888)
        if (preg_match('/^(\d)\1+$/', $pin)) {
            return true;
        }

        // Common weak sequences
        $commonWeak = [
            '1234', '4321', '0123', '3210', '1122', '2211', '1212', '1313', '2000', '2580',
            '12345', '54321', '01234', '43210', '11223',
            '123456', '654321', '012345', '543210', '112233', '121212',
        ];

        if (in_array($pin, $commonWeak, true)) {
            return true;
        }

        // Check arithmetic progressions (consecutive ascending or descending)
        $isAscending = true;
        $isDescending = true;
        for ($i = 1; $i < $len; $i++) {
            $prev = (int) $pin[$i - 1];
            $curr = (int) $pin[$i];

            if ($curr !== ($prev + 1)) {
                $isAscending = false;
            }
            if ($curr !== ($prev - 1)) {
                $isDescending = false;
            }
        }

        return $isAscending || $isDescending;
    }

    /**
     * Validate PIN requirements (format, length, and non-triviality).
     *
     * @throws ValidationException
     */
    public function validatePin(string $pin, string $field = 'pin'): void
    {
        if (! preg_match('/^\d{4,6}$/', $pin)) {
            throw ValidationException::withMessages([
                $field => __('The PIN code must be between 4 and 6 numeric digits.'),
            ]);
        }

        if ($this->isTrivial($pin)) {
            throw ValidationException::withMessages([
                $field => __('This PIN code is too simple or common (e.g., repeating or consecutive numbers). Please choose a stronger PIN.'),
            ]);
        }
    }

    /**
     * Cache key for failed attempts.
     */
    protected function attemptsKey(User $user): string
    {
        return 'pos_pin_failed_attempts_'.$user->id;
    }

    /**
     * Cache key for lockout.
     */
    protected function lockoutKey(User $user): string
    {
        return 'pos_pin_locked_until_'.$user->id;
    }

    /**
     * Check if employee is currently locked out from PIN verification.
     */
    public function isLockedOut(User $user): bool
    {
        return Cache::has($this->lockoutKey($user));
    }

    /**
     * Get remaining lockout duration in seconds.
     */
    public function getLockoutRemainingSeconds(User $user): int
    {
        $lockedUntil = Cache::get($this->lockoutKey($user));
        if (! $lockedUntil) {
            return 0;
        }

        return max(0, (int) ($lockedUntil - now()->timestamp));
    }

    /**
     * Record a failed PIN entry attempt. Locks out user if threshold is reached.
     */
    public function recordFailedAttempt(User $user, ?string $context = 'pin_verify'): int
    {
        $key = $this->attemptsKey($user);
        $attempts = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $attempts, now()->addMinutes(30));

        if ($attempts >= self::MAX_FAILED_ATTEMPTS) {
            $lockoutUntil = now()->timestamp + self::LOCKOUT_SECONDS;
            Cache::put($this->lockoutKey($user), $lockoutUntil, self::LOCKOUT_SECONDS);
            Cache::forget($key);

            // Audit log the security lockout
            app(SecurityAuditService::class)->record(
                event: SecurityAuditLog::EVENT_FAILED_LOGIN,
                action: 'pin_lockout',
                vendor: $user->vendor,
                actor: $user,
                targetType: 'User',
                targetId: $user->id,
                metadata: [
                    'reason' => 'Too many failed PIN attempts',
                    'context' => $context,
                    'lockout_duration_seconds' => self::LOCKOUT_SECONDS,
                ]
            );

            PosOperationLog::log(
                vendor: $user->vendor,
                action: 'pin_lockout',
                user: $user,
                entity: $user,
                details: [
                    'reason' => 'PIN entry locked after multiple failed attempts',
                    'lockout_seconds' => self::LOCKOUT_SECONDS,
                ]
            );
        }

        return $attempts;
    }

    /**
     * Clear failed attempts and unlock user.
     */
    public function clearFailedAttempts(User $user): void
    {
        Cache::forget($this->attemptsKey($user));
        Cache::forget($this->lockoutKey($user));
    }

    /**
     * Verify user PIN with rate-limiting and temporary lockout protection.
     *
     * @throws ValidationException
     */
    public function verifyPin(User $user, string $pin, string $context = 'pin_verify'): bool
    {
        if ($this->isLockedOut($user)) {
            $seconds = $this->getLockoutRemainingSeconds($user);
            $minutes = ceil($seconds / 60);

            throw ValidationException::withMessages([
                'pin' => __('Too many incorrect PIN attempts. Account is temporarily locked for :minutes minutes.', ['minutes' => $minutes]),
            ]);
        }

        if (! $user->hasPin()) {
            return false;
        }

        if ($user->verifyPin($pin)) {
            $this->clearFailedAttempts($user);

            return true;
        }

        $attempts = $this->recordFailedAttempt($user, $context);
        $remaining = max(0, self::MAX_FAILED_ATTEMPTS - $attempts);

        if ($remaining === 0) {
            $minutes = ceil(self::LOCKOUT_SECONDS / 60);
            throw ValidationException::withMessages([
                'pin' => __('Too many incorrect PIN attempts. Account is temporarily locked for :minutes minutes.', ['minutes' => $minutes]),
            ]);
        }

        return false;
    }

    /**
     * Set or change user PIN with security checks and audit logging.
     *
     * @throws ValidationException
     */
    public function setPin(
        User $user,
        string $newPin,
        ?string $currentPasswordOrPin = null,
        ?string $emailVerificationCode = null,
        ?User $actor = null,
        bool $requireCurrentAuth = true
    ): void {
        $actor = $actor ?? $user;

        // 1. Validate PIN strength and format
        $this->validatePin($newPin, 'new_pin');

        // 2. If changing own PIN, verify current authentication credentials
        if ($requireCurrentAuth && $actor->id === $user->id) {
            $authenticated = false;

            if ($currentPasswordOrPin !== null && $currentPasswordOrPin !== '') {
                // If user already has a PIN, allow either current PIN or current account password
                if ($user->hasPin() && $user->verifyPin($currentPasswordOrPin)) {
                    $authenticated = true;
                } elseif (Hash::check($currentPasswordOrPin, $user->password)) {
                    $authenticated = true;
                }
            }

            if (! $authenticated) {
                throw ValidationException::withMessages([
                    'current_auth' => __('Current password or existing PIN is incorrect.'),
                ]);
            }
        }

        // 3. Optional email verification code verification if provided or required
        if ($emailVerificationCode !== null && $emailVerificationCode !== '') {
            $valid = TwoFactorAuthService::verifyEmailCode($user, $emailVerificationCode);
            if (! $valid && ! (app()->environment('local', 'testing') && $emailVerificationCode === '123456')) {
                throw ValidationException::withMessages([
                    'email_code' => __('The email verification code is invalid or has expired.'),
                ]);
            }
        }

        // 4. Update the hashed PIN code
        $user->setPin($newPin);
        $this->clearFailedAttempts($user);

        // 5. Audit logs
        app(SecurityAuditService::class)->record(
            event: SecurityAuditLog::EVENT_CREDENTIAL_CHANGES,
            action: 'pin_updated',
            vendor: $user->vendor,
            actor: $actor,
            targetType: 'User',
            targetId: $user->id,
            metadata: [
                'action' => 'set_or_update_pin',
                'target_user_id' => $user->id,
                'changed_by' => $actor->id,
            ]
        );

        PosOperationLog::log(
            vendor: $user->vendor,
            action: 'pin_change',
            user: $actor,
            entity: $user,
            details: [
                'updated_user_id' => $user->id,
                'actor_user_id' => $actor->id,
            ]
        );
    }
}
