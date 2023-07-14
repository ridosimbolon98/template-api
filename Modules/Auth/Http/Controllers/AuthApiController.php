<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthApiController extends Controller
{
    //
    public function login(Request $request)
    {
        $this->ensureLoginAttemptsAreNotExceeded($request);

        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            $this->incrementLoginAttempts($request);

            throw ValidationException::withMessages([
                'email' => ['Email atau password tidak sesuai!'],
            ]);
        }

        $this->clearLoginAttempts($request);

        $user = Auth::user();

        $token = $user->createToken('login-token')->plainTextToken;

        $cookie = cookie('api_token', $token, config('sanctum.lifetime'), null, null, false, true);

        return response()->json([
            'message' => 'Berhasil login',
            'tkn' => $token,
        ])->withCookie($cookie);
    }

    protected function ensureLoginAttemptsAreNotExceeded(Request $request)
    {
        if (RateLimiter::tooManyAttempts($this->throttleKey($request), $this->maxAttempts())) {
            throw ValidationException::withMessages([
                'email' => ['Terlalu banyak login. Silakan coba lagi nanti!'],
            ])->status(429);
        }
    }

    protected function incrementLoginAttempts(Request $request)
    {
        RateLimiter::hit($this->throttleKey($request));
    }

    protected function clearLoginAttempts(Request $request)
    {
        RateLimiter::clear($this->throttleKey($request));
    }

    protected function throttleKey(Request $request)
    {
        return Str::lower($request->input('email')) . '|' . $request->ip();
    }

    protected function maxAttempts()
    {
        return config('auth.throttle.max_attempts');
    }

    public function logout(Request $request)
    {
        // Revoke the token that was used to authenticate the current request...
        $request->user()->currentAccessToken()->delete();
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $request->user()]);
    }
}