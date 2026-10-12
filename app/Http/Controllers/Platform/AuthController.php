<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function form()
    {
        return Auth::guard('platform')->check() ? redirect()->route('platform.dashboard') : view('platform.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::guard('platform')->attempt($credentials)) {
            return back()->withInput($request->only('email'))->withErrors(['email' => 'That email and password don’t match a platform admin.']);
        }
        $request->session()->regenerate();

        return redirect()->intended(route('platform.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::guard('platform')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('platform.login');
    }
}
