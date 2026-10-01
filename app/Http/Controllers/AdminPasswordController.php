<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAdminPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminPasswordController extends Controller
{
    public function update(UpdateAdminPasswordRequest $request): RedirectResponse
    {
        $user = $request->user();
        DB::transaction(function () use ($request, $user): void {
            $user->password = $request->validated('password');
            $user->remember_token = Str::random(60);
            $user->save();
            DB::table('sessions')->where('user_id', $user->getKey())->delete();
        });

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Password updated. Sign in again with your authenticator.');
    }
}
