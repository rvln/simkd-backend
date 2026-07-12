<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\SocialAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class SocialiteController extends Controller
{
    protected SocialAuthService $socialAuthService;

    public function __construct(SocialAuthService $socialAuthService)
    {
        $this->socialAuthService = $socialAuthService;
    }

    /**
     * Redirect to Google authentication page.
     */
    public function redirectToGoogle(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle callback from Google.
     */
    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        try {
            $user = $this->socialAuthService->handleGoogleCallback();
            
            // Buat token Sanctum untuk user tersebut
            $token = $user->createToken('auth_token')->plainTextToken;
            
            // Ambil URL Frontend dari config/env
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
            
            // Redirect ke halaman khusus di frontend untuk menyimpan token
            $encodedToken = urlencode($token);
            return redirect()->to("{$frontendUrl}/auth/callback#token={$encodedToken}");
        } catch (\Exception $e) {
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
            return redirect()->to("{$frontendUrl}/login?error=social_auth_failed");
        }
    }
}