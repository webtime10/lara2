<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyPluginApiKey
{
    public function handle(Request $request, Closure $next, string $plugin = ''): Response
    {
        $expected = '';
        if ($plugin !== '') {
            $expected = trim((string) config("services.plugins.{$plugin}.api_key", ''));
        }
        if ($expected === '') {
            $expected = trim((string) config('services.plugins.default_api_key', ''));
        }

        if ($expected === '') {
            if (config('app.env') === 'local') {
                return $next($request);
            }

            return response()->json(['message' => 'Plugin API key is not configured on server.'], 500);
        }

        $provided = trim((string) (
            $request->header('X-Plugin-Api-Key')
            ?? $request->header('X-Laravel-Api-Key')
            ?? $request->query('api_key', '')
        ));

        if ($provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }
}
