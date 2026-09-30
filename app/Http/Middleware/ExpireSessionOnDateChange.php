<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ExpireSessionOnDateChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $today = now()->toDateString();
        $authDate = $request->session()->get('auth_date');

        if ($user->session_date?->toDateString() !== $today || ($authDate !== null && $authDate !== $today)) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors(['login' => 'Sesi berakhir karena tanggal sudah berganti. Silakan masuk lagi.']);
        }

        return $next($request);
    }
}
