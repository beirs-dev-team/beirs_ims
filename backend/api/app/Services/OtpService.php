<?php

namespace App\Services;

use App\Models\Auth\OtpCode;
use App\Models\Users\User;
use App\Notifications\OtpCodeNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class OtpService
{
    private int $length;
    private int $ttlMinutes;
    private int $requestCooldown;
    private int $maxAttempts;

    public function __construct()
    {
        $config = config('services.otp');
        $this->length = (int) ($config['length'] ?? 6);
        $this->ttlMinutes = (int) ($config['ttl'] ?? 5);
        $this->requestCooldown = (int) ($config['request_cooldown'] ?? 60);
        $this->maxAttempts = (int) ($config['max_attempts'] ?? 5);
    }

    public function isEnabled(): bool
    {
        return (bool) config('services.otp.enabled', false);
    }

    /**
     * @return array{ok: bool, status: string, message: string, otp?: OtpCode, status_code?: int, show_otp?: string }
     */
    public function requestForUser(User $user): array
    {
        if (!$this->isEnabled()) {
            return [
                'ok' => false,
                'status' => 'disabled',
                'message' => 'OTP is not enabled.',
                'status_code' => 400,
            ];
        }

        if (!$this->requiresMobileVerification($user)) {
            return [
                'ok' => false,
                'status' => 'already_verified',
                'message' => 'Mobile number already verified; no OTP required.',
                'status_code' => 200,
            ];
        }

        $recipient = $user->profile?->routeNotificationForSms();
        if (!$recipient) {
            return [
                'ok' => false,
                'status' => 'unreachable',
                'message' => 'No valid mobile number found for OTP delivery.',
                'status_code' => 422,
            ];
        }

        $latest = $this->latestActiveOtp($user);
        if ($latest && !$latest->isExpired() && $latest->created_at->gt(CarbonImmutable::now()->subSeconds($this->requestCooldown))) {
            return [
                'ok' => false,
                'status' => 'throttled',
                'message' => 'Please wait before requesting another OTP.',
                'status_code' => 429,
            ];
        }

        [$otp, $code, $showCode] = $this->createOtp($user);

        $user->profile?->notify(new OtpCodeNotification($code, $this->ttlMinutes));

        return [
            'ok' => true,
            'status' => 'otp_required',
            'message' => 'OTP sent to your registered mobile number.',
            'otp' => $otp,
            'show_otp' => $showCode,
        ];
    }

    /**
     * @return array{ok: bool, status: string, message: string, otp?: OtpCode, status_code?: int, show_otp?: string}
     */
    public function verify(User $user, string $plainCode): array
    {
        if (!$this->isEnabled()) {
            return [
                'ok' => false,
                'status' => 'disabled',
                'message' => 'OTP is not enabled.',
                'status_code' => 400,
            ];
        }

        $otp = $this->latestActiveOtp($user);

        if (!$otp) {
            return [
                'ok' => false,
                'status' => 'missing',
                'message' => 'No OTP request found. Please request a new code.',
                'status_code' => 404,
            ];
        }

        if ($otp->attempts >= $this->maxAttempts) {
            return [
                'ok' => false,
                'status' => 'locked',
                'message' => 'Maximum OTP attempts exceeded. Please request a new code.',
                'status_code' => 429,
            ];
        }

        if ($otp->isExpired()) {
            $otp->markConsumed();

            return [
                'ok' => false,
                'status' => 'expired',
                'message' => 'OTP code has expired. Please request a new one.',
                'status_code' => 410,
            ];
        }

        $otp->incrementAttempts();

        if (!Hash::check($plainCode, $otp->code_hash)) {
            return [
                'ok' => false,
                'status' => 'invalid',
                'message' => 'Invalid OTP code.',
                'status_code' => 422,
            ];
        }

        $otp->markConsumed();

        return [
            'ok' => true,
            'status' => 'verified',
            'message' => 'OTP verified successfully.',
            'otp' => $otp,
        ];
    }

    public function requiresMobileVerification(User $user): bool
    {
        return $this->isEnabled()
            && $user->role === 'resident'
            && !$user->profile?->mobile_verified_at;
    }

    /**
     * @return array{0: OtpCode, 1: string}
     */
    private function createOtp(User $user): array
    {
        // Invalidate any existing codes
        OtpCode::where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => CarbonImmutable::now()]);

        $code = str_pad((string) random_int(0, (10 ** $this->length) - 1), $this->length, '0', STR_PAD_LEFT);
        $showCode = $code;
        $otp = OtpCode::create([
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'expires_at' => CarbonImmutable::now()->addMinutes($this->ttlMinutes),
            'attempts' => 0,
        ]);

        return [$otp, $code, $showCode];
    }

    private function latestActiveOtp(User $user): ?OtpCode
    {
        return OtpCode::where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->latest()
            ->first();
    }
}
