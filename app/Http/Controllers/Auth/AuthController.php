<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\FirmSetting;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\FirmConfigService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->intended($this->redirectTarget(Auth::user()->role));
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = strtolower(trim((string) $request->input('email')));
        $password = (string) $request->input('password');
        $remember = $request->boolean('remember');

        $loggedIn = Auth::attempt(['email' => $email, 'password' => $password], $remember);

        if (! $loggedIn) {
            // Support both @uptoware.com and legacy @auditflow.in
            if (str_ends_with($email, '@uptoware.com')) {
                $alias = str_replace('@uptoware.com', '@auditflow.in', $email);
                $loggedIn = Auth::attempt(['email' => $alias, 'password' => $password], $remember);
            } elseif (str_ends_with($email, '@auditflow.in')) {
                $alias = str_replace('@auditflow.in', '@uptoware.com', $email);
                $loggedIn = Auth::attempt(['email' => $alias, 'password' => $password], $remember);
            }
        }

        if (! $loggedIn) {
            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => 'Invalid email address or password.']);
        }

        $user = Auth::user();
        if (! $user->is_active) {
            Auth::logout();

            return back()->withErrors(['email' => 'Your account is currently deactivated. Contact your administrator.']);
        }

        if (FirmSetting::getBool('enforce_2fa') || $user->two_factor_enabled) {
            $this->issueTwoFactorOtp($user);
            Auth::logout();
            $request->session()->put('2fa_user_id', $user->id);
            $request->session()->put('2fa_remember', $remember);

            return redirect()->route('login.2fa')
                ->with('success', 'A 6-digit verification code has been sent to your email.');
        }

        $request->session()->regenerate();
        $this->logger->log('auth', 'login', "{$user->name} ({$user->roleLabel()}) signed in.");

        return redirect()->intended($this->redirectTarget($user->role));
    }

    public function showTwoFactor(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('2fa_user_id')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor');
    }

    public function verifyTwoFactor(Request $request): RedirectResponse
    {
        $request->validate([
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $userId = $request->session()->get('2fa_user_id');
        if (! $userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);
        if (! $user || ! $user->two_factor_code || ! $user->two_factor_expires_at || $user->two_factor_expires_at->isPast()) {
            return back()->withErrors(['otp' => 'Verification code has expired. Please request a new code.']);
        }

        if (! Hash::check(trim((string) $request->input('otp')), $user->two_factor_code)) {
            return back()->withErrors(['otp' => 'Invalid 6-digit verification code.']);
        }

        $remember = (bool) $request->session()->pull('2fa_remember', false);
        $request->session()->forget('2fa_user_id');

        $user->update([
            'two_factor_code' => null,
            'two_factor_expires_at' => null,
        ]);

        Auth::login($user, $remember);
        $request->session()->regenerate();
        $this->logger->log('auth', 'login_2fa', "{$user->name} ({$user->roleLabel()}) verified Email-OTP 2FA.");

        return redirect()->intended($this->redirectTarget($user->role));
    }

    public function resendTwoFactor(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('2fa_user_id');
        if (! $userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);
        if ($user) {
            $this->issueTwoFactorOtp($user);
        }

        return back()->with('success', 'A fresh 6-digit verification code has been sent to your email.');
    }

    public function logout(Request $request): RedirectResponse
    {
        if (Auth::check()) {
            $this->logger->log('auth', 'logout', Auth::user()->name.' signed out.');
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'You have been securely logged out.');
    }

    private function issueTwoFactorOtp(User $user): void
    {
        $otp = (string) random_int(100000, 999999);
        $user->update([
            'two_factor_code' => Hash::make($otp),
            'two_factor_expires_at' => now()->addMinutes(10),
        ]);

        try {
            FirmConfigService::applyMailConfig();
            Mail::raw(
                "Your Uptoware Audit login verification code is: {$otp}\n\nThis code is valid for 10 minutes.",
                function ($message) use ($user) {
                    $message->to($user->email)
                        ->subject('Uptoware Audit — Your 6-Digit Login OTP');
                }
            );
        } catch (\Throwable $e) {
            // Logged silently; user can retry or admin can inspect mail settings
            report($e);
        }
    }

    private function redirectTarget(string $role): string
    {
        return $role === 'client' ? route('portal.dashboard') : route('dashboard');
    }
}
