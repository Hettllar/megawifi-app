<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class PersistentLogin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  \
     */
    public function handle(Request \, Closure \): Response
    {
        // إذا كان المستخدم مسجل دخول ولديه remember token
        if (Auth::check() && \->session()->has('auth.remember')) {
            // تمديد مدة الجلسة تلقائياً
            \->session()->migrate(true);
            
            // إعادة تعيين وقت انتهاء الجلسة
            config(['session.lifetime' => config('auth.remember_me_lifetime', 525600)]);
        }
        
        return \(\);
    }
}
