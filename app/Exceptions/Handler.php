<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Throwable;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class Handler extends ExceptionHandler
{
 protected function unauthenticated($request, AuthenticationException $exception): Response
{
    if ($request->expectsJson() || $request->is('api/*')) {
        return response()->json(['message' => 'No autenticado'], 401);
    }

    // Si no tienes login web, regresa 401 simple:
    return response()->json(['message' => 'No autenticado'], 401);
    // (o redirect()->guest('/login') si sí tienes pantalla web)
}
}
