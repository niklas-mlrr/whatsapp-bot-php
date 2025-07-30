<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyApiKey
{
    public function handle(Request $request, Closure $next, string $service)
    {
        $validKeys = [
            'whatsapp' => env('WHATSAPP_API_KEY')
        ];

        if ($request->header('X-API-KEY') !== ($validKeys[$service] ?? null)) {
            abort(403, 'Invalid API key');
        }

        return $next($request);
    }
}
