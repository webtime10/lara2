<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Проверяем, залогинен ли пользователь
        if (!Auth::check()) {
            return redirect('/login');
        }

        // Проверяем роль администратора
        $user = Auth::user();
        if (! $user->isAdmin()) {
            return redirect()
                ->route('admin.index')
                ->with('error', 'Доступ к пользователям и ролям только у admin.');
        }

        return $next($request);
    }
}

