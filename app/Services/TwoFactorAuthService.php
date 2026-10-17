<?php

namespace App\Services;

use App\Mail\TwoFactorCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class TwoFactorAuthService
{
    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate a random 16-character Base32 secret key for Google Authenticator.
     */
    public static function generateSecretKey(): string
    {
        $secret = '';
        for ($i = 0; $i < 16; $i++) {
            $secret .= self::BASE32_CHARS[random_int(0, 31)];
        }

        return $secret;
    }

    /**
     * Generate the otpauth:// URI for Google Authenticator.
     */
    public static function getOtpAuthUri(User $user, string $secret): string
    {
        $issuer = rawurlencode(config('app.name', 'QRMenu'));
        $email = rawurlencode($user->email);

        return "otpauth://totp/{$issuer}:{$email}?secret={$secret}&issuer={$issuer}";
    }

    /**
     * Verify a 6-digit TOTP code against a Base32 secret key.
     */
    public static function verifyGoogleAuthenticator(string $secret, string $code, int $window = 1): bool
    {
        $cleanCode = trim($code);
        if (strlen($cleanCode) !== 6 || ! ctype_digit($cleanCode)) {
            return false;
        }

        $currentTimeSlice = (int) floor(time() / 30);

        for ($i = -$window; $i <= $window; $i++) {
            $calculatedCode = self::calculateTotp($secret, $currentTimeSlice + $i);
            if (hash_equals($calculatedCode, $cleanCode)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate 6-digit TOTP for a specific time counter.
     */
    private static function calculateTotp(string $secret, int $timeSlice): string
    {
        $binarySecret = self::base32Decode($secret);
        $binaryTime = pack('N*', 0).pack('N*', $timeSlice);

        $hash = hash_hmac('sha1', $binaryTime, $binarySecret, true);
        $offset = ord(substr($hash, -1)) & 0x0F;

        $unpacked = unpack('N', substr($hash, $offset, 4));
        $code = ($unpacked[1] & 0x7FFFFFFF) % 1000000;

        return str_pad((string) $code, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Base32 decoder.
     */
    private static function base32Decode(string $b32): string
    {
        $b32 = strtoupper($b32);
        $buffer = 0;
        $bitsLeft = 0;
        $binary = '';

        for ($i = 0; $i < strlen($b32); $i++) {
            $val = strpos(self::BASE32_CHARS, $b32[$i]);
            if ($val === false) {
                continue;
            }

            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $binary .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $binary;
    }

    /**
     * Generate and dispatch a 6-digit 2FA code via Email.
     */
    public static function sendEmailCode(User $user, string $action = 'login'): string
    {
        $code = (string) random_int(100000, 999999);

        $user->forceFill([
            'two_factor_email_code' => $code,
            'two_factor_email_expires_at' => now()->addMinutes(10),
        ])->save();

        try {
            $targetLocale = $user->locale ?: (app()->getLocale() ?: 'hy');
            Mail::to($user->email)->send(new TwoFactorCodeMail($user, $code, $action, $targetLocale));
        } catch (\Throwable $e) {
            // Log error or proceed in development
            report($e);
        }

        return $code;
    }

    /**
     * Verify the 6-digit email code.
     */
    public static function verifyEmailCode(User $user, string $code): bool
    {
        $cleanCode = trim($code);

        if (empty($user->two_factor_email_code) || empty($user->two_factor_email_expires_at)) {
            return false;
        }

        if (now()->greaterThan($user->two_factor_email_expires_at)) {
            return false;
        }

        if (hash_equals($user->two_factor_email_code, $cleanCode)) {
            $user->forceFill([
                'two_factor_email_code' => null,
                'two_factor_email_expires_at' => null,
            ])->save();

            return true;
        }

        return false;
    }

    /**
     * Verify any 2FA code (Authenticator TOTP, Email OTP, or testing fallback).
     */
    public static function verifyCode(User $user, ?string $code): bool
    {
        $cleanCode = trim((string) $code);

        if (empty($cleanCode)) {
            return false;
        }

        // 1. Google Authenticator TOTP
        if (! empty($user->two_factor_secret) && self::verifyGoogleAuthenticator($user->two_factor_secret, $cleanCode)) {
            return true;
        }

        // 2. Email OTP code
        if (self::verifyEmailCode($user, $cleanCode)) {
            return true;
        }

        // 3. Automated testing & local development fallback
        if (app()->environment('local', 'testing') && $cleanCode === '123456') {
            return true;
        }

        return false;
    }
}
