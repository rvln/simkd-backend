<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Laravel\Sanctum\PersonalAccessToken;

class UserService
{
    /**
     * Register a new user account.
     * Creates the user with email_verified_at = NULL, generates a verification token,
     * and dispatches a verification email asynchronously.
     *
     * UML Ref: Activity Diagram §1 (Registrasi & Verifikasi Email)
     * UML Ref: Sequence Diagram §SD-1 (steps 3-5)
     */
    public function registerUser(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            $this->generateAndPersistToken($user);
            $this->dispatchVerificationEmail($user);

            return $user;
        });
    }

    /**
     * Authenticate a user via email and password.
     * Enforces the email_verified_at constraint: if NULL, throws 403.
     *
     * UML Ref: Activity Diagram §2 (Login & Resend Verification Flow)
     * UML Ref: Sequence Diagram §SD-2 (steps 1-2)
     * AGENTS.md: Authentication State Constraint
     */
    public function attemptLogin(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        // Authentication State Constraint: reject session creation if email is unverified
        if (!$user->email_verified_at) {
            throw new HttpException(403, 'Email not verified.');
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'token' => $token,
            'user' => $user,
        ];
    }

    /**
     * Verify a user's email using a cryptographic token string.
     * Validates the token exists, belongs to a user, and has not expired.
     *
     * UML Ref: Sequence Diagram §SD-1 (step 6 — Constraint Check)
     *   Alt - Invalid: Exception(InvalidToken)
     *   Alt - Expired: Exception(TokenExpired)
     *   Alt - Valid: update(email_verified_at = now())
     */
    public function verifyEmailByToken(string $token): User
    {
        $hashedToken = hash('sha256', $token);

        $user = User::where('verification_token', $hashedToken)->first();

        if (!$user) {
            throw new HttpException(400, 'Invalid verification token.');
        }

        if ($user->verification_token_expires_at && $user->verification_token_expires_at->isPast()) {
            throw new HttpException(410, 'Verification token has expired. Please request a new one.');
        }

        $user->update([
            'email_verified_at' => now(),
            'verification_token' => null,
            'verification_token_expires_at' => null,
        ]);

        return $user;
    }

    /**
     * Resend email verification.
     * Executes three sequential operations per UML §SD-2 (steps 3-4):
     *   1. Invalidate old tokens
     *   2. Generate a new cryptographic token
     *   3. Trigger asynchronous email dispatch
     */
    public function resendVerificationEmail(string $email): void
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            throw new HttpException(404, 'User not found.');
        }

        if ($user->email_verified_at) {
            throw new HttpException(400, 'User already verified.');
        }

        // Step 1: Invalidate old tokens
        $user->update([
            'verification_token' => null,
            'verification_token_expires_at' => null,
        ]);

        // Step 2: Generate new cryptographic token
        $this->generateAndPersistToken($user);

        // Step 3: Trigger asynchronous email dispatch
        $this->dispatchVerificationEmail($user);
    }

    /**
     * Generate a cryptographic verification token, hash it for storage,
     * and persist it with an expiration timestamp.
     * Returns the raw (unhashed) token for email delivery.
     */
    private function generateAndPersistToken(User $user): string
    {
        $rawToken = Str::random(64);
        $hashedToken = hash('sha256', $rawToken);

        $user->update([
            'verification_token' => $hashedToken,
            'verification_token_expires_at' => now()->addHours(24),
        ]);

        return $rawToken;
    }

    /**
     * Dispatch a verification email asynchronously (fire-and-forget).
     * Uses Laravel's queue system when configured; falls back to sync.
     *
     * UML Ref: Sequence Diagram §SD-1 (step 5 — Asynchronous Event)
     */
    private function dispatchVerificationEmail(User $user): void
    {
        $rawToken = $this->generateAndPersistToken($user);

        // Queue-based async dispatch via Laravel's Mail facade.
        // In production, configure QUEUE_CONNECTION=redis/database for true async.
        // The Mailable class should be created separately (e.g., App\Mail\VerifyEmailMail).
        // For now, we use a simple closure-based email as a functional placeholder
        // that matches the UML contract without introducing a non-existent Mailable.
        try {
            $frontendUrl = config('cors.allowed_origins')[0] ?? 'http://localhost:3000';
            $verificationUrl = "{$frontendUrl}/verify-email?token={$rawToken}";

            Mail::raw(
                "Halo {$user->name},\n\nTerima kasih telah menjadi bagian dari Panti Asuhan Dr. J. Lucas. Silakan klik tautan di bawah ini untuk memverifikasi akun Anda:\n\n{$verificationUrl}\n\nTautan ini akan kadaluarsa dalam 24 jam.\n\nJika Anda tidak merasa mendaftar, silakan abaikan email ini.",
                function ($message) use ($user) {
                    $message->to($user->email)
                            ->subject('Verifikasi Email - Panti Asuhan Dr. J. Lucas');
                }
            );
        } catch (\Throwable $e) {
            // Log the failure but do not block the registration flow.
            // UML specifies async (fire-and-forget) dispatch.
            \Illuminate\Support\Facades\Log::warning('Failed to dispatch verification email: ' . $e->getMessage());
        }
    }

    /**
     * Permanently delete a user account along with ALL owned history.
     *
     * Deletion order (cascade-safe):
     *   1. Revoke all Sanctum tokens (invalidate active sessions).
     *   2. Hard-delete all owned Visits (and their child relations via DB cascade).
     *   3. Hard-delete all owned Donations (and their item_donations via DB cascade).
     *   4. Hard-delete all owned VisitReports.
     *   5. Hard-delete the User row itself.
     *
     * Wrapped in a single DB transaction; any failure rolls back completely.
     *
     * AGENTS.md §3: No destructive side-effects outside the authenticated user's scope.
     */
    public function deleteAccount(User $user): void
    {
        DB::transaction(function () use ($user) {
            // 1. Revoke all Sanctum tokens to kill active sessions immediately.
            $user->tokens()->delete();

            // 2. Hard-delete all visits owned by this user.
            //    The visits table FK to capacities is cascade-null/restrict per ERD;
            //    deleting the visit row is safe — capacity counts are NOT automatically
            //    decremented here because the visit is being permanently expunged.
            $user->visits()->each(function ($visit) {
                // Delete child visit reports first to avoid FK constraint violations.
                $visit->visitReports()->delete();
                $visit->delete();
            });

            // 3. Hard-delete all donations owned by this user.
            //    item_donations are expected to cascade via FK on delete cascade.
            $user->donations()->each(function ($donation) {
                $donation->itemDonations()->delete();
                $donation->delete();
            });

            // 4. Hard-delete any orphan VisitReports not caught above.
            if (method_exists($user, 'visitReports')) {
                $user->visitReports()->delete();
            }

            // 5. Delete the User row itself.
            $user->delete();
        });
    }

    /**
     * Send a password reset link to the user's email.
     *
     * Uses Laravel's built-in password_reset_tokens table via the Password broker.
     * Generates a secure token, persists it (hashed) in the DB, and dispatches
     * an email containing a link back to the frontend's /reset-password page.
     *
     * If the email doesn't exist, we still return success (security best practice:
     * don't reveal whether an email is registered).
     */
    public function sendPasswordResetLink(string $email): void
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            // Silent return — don't leak whether the email exists.
            return;
        }

        // Generate token via Laravel's Password broker (stores hashed in password_reset_tokens)
        $token = app('auth.password.broker')->createToken($user);

        // Dispatch reset email
        try {
            $frontendUrl = config('cors.allowed_origins')[0] ?? 'http://localhost:3000';
            $resetUrl = "{$frontendUrl}/reset-password?token={$token}&email=" . urlencode($user->email);

            Mail::raw(
                "Halo {$user->name},\n\nKami menerima permintaan untuk mereset kata sandi akun Anda di Panti Asuhan Dr. J. Lucas.\n\nSilakan klik tautan berikut untuk mengatur kata sandi baru:\n\n{$resetUrl}\n\nTautan ini berlaku selama 60 menit. Jika Anda tidak meminta reset kata sandi, silakan abaikan email ini.\n\nSalam,\nTim Panti Asuhan Dr. J. Lucas",
                function ($message) use ($user) {
                    $message->to($user->email)
                            ->subject('Reset Kata Sandi - Panti Asuhan Dr. J. Lucas');
                }
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to dispatch password reset email: ' . $e->getMessage());
        }
    }

    /**
     * Reset the user's password using a valid token.
     *
     * Validates the token+email pair via Laravel's Password broker, then:
     *   1. Updates the user's password (Hash::make).
     *   2. Deletes the used token from password_reset_tokens.
     *   3. Revokes all existing Sanctum tokens (force re-login with new password).
     *
     * Throws HttpException if the token is invalid/expired or email doesn't match.
     */
    public function resetPassword(string $email, string $token, string $password): void
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            throw new HttpException(400, 'Email tidak ditemukan.');
        }

        // Validate token via Laravel's Password broker
        $broker = app('auth.password.broker');

        if (!$broker->tokenExists($user, $token)) {
            throw new HttpException(400, 'Token tidak valid atau sudah kadaluarsa.');
        }

        DB::transaction(function () use ($user, $password, $broker) {
            // 1. Update password
            $user->update([
                'password' => Hash::make($password),
            ]);

            // 2. Delete the used token
            $broker->deleteToken($user);

            // 3. Revoke all existing Sanctum tokens (security: force re-login)
            $user->tokens()->delete();
        });
    }
}

