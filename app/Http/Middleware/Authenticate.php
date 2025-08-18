<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Override redirectTo to avoid redirect in APIs.
     */
    protected function redirectTo($request): ?string
    {
        if (! $request->expectsJson()) {
            abort(response()->json([
                'message' => 'No autenticado',
            ], 401));
        }

        return null;
    }
}
