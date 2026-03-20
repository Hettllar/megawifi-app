<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * Show the login form
     */
    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * Handle a login request
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            // Check if user account is active
            if (!Auth::user()->is_active) {
                Auth::logout();
                throw ValidationException::withMessages([
                    'email' => 'تم تعطيل حسابك. تواصل مع المدير.',
                ]);
            }

            // Check if user account has expired
            if (Auth::user()->expires_at && \Carbon\Carbon::parse(Auth::user()->expires_at)->isPast()) {
                Auth::user()->update(['is_active' => false]);
                Auth::logout();
                throw ValidationException::withMessages([
                    'email' => 'انتهت صلاحية حسابك. تواصل مع المدير.',
                ]);
            }
            $request->session()->regenerate();

            ActivityLog::log('user.login', 'تسجيل دخول');

            return redirect()->intended('dashboard');
        }

        throw ValidationException::withMessages([
            'email' => __('البريد الإلكتروني أو كلمة المرور غير صحيحة'),
        ]);
    }

    /**
     * Log the user out
     */
    public function logout(Request $request)
    {
        ActivityLog::log('user.logout', 'تسجيل خروج');

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
