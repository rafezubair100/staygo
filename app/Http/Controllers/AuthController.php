<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function registerForm() { return view('auth.register'); }
    public function loginForm() { return view('auth.login'); }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users'],
            'password' => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()],
            'phone' => ['nullable', 'string', 'max:40'],
            'country' => ['nullable', 'string', 'max:100'],
        ]);
        $user = User::create($data + ['role' => 'customer']);
        Auth::login($user);
        $request->session()->regenerate();
        return redirect()->route('account')->with('success', 'Welcome to Staygo.');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);
        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'The supplied login details are incorrect.'])->onlyInput('email');
        }
        $request->session()->regenerate();
        return redirect()->intended(route('account'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('home');
    }
}
