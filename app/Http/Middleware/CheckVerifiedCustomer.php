<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckVerifiedCustomer
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'غير مسموح، سجل الدخول أولاً.'], 401);
        }

        if (!$user->phone_verified_at) {
            return response()->json(['message' => 'يجب تأكيد رقم الهاتف أولاً.'], 403);
        }

        if ($user->email && !$user->email_verified_at) {
            return response()->json(['message' => 'يجب تأكيد البريد الإلكتروني الذي أدخلته.'], 403);
        }

        return $next($request);
    }

}
