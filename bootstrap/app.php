<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpFoundation\Request;



return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {

        // --- Authentication Exception ---
        $exceptions->render(function (AuthenticationException $e, Request $request): ?JsonResponse {
            if ($request->is('api/*') || $request->wantsJson()) {
                return response()->json(['success'=>false, 'message' => 'Unauthenticated.'], 401);
            }
            return null;
        });

        // --- Authorization Exception (Policy Denied) ---
        $exceptions->render(function (AuthorizationException $e, Request $request): ?JsonResponse { // <-- ADD THIS BLOCK
            if ($request->is('api/*') || $request->wantsJson()) {
                // Use the message from the exception if it's specific, or a default
                $message = $e->getMessage() && $e->getMessage() !== 'This action is unauthorized.'
                    ? $e->getMessage()
                    : 'You do not have permission to perform this action.';
                return response()->json(['success' => false, 'message' => $message], 403); // 403 Forbidden
            }
            return null;
        });

        // --- Model Not Found Exception ---
        $exceptions->render(function (ModelNotFoundException $e, Request $request): ?JsonResponse {
            if ($request->is('api/*') || $request->wantsJson()) {
                return response()->json(['success'=> false,'message' => 'Resource not found.'], 404);
            }
            return null;
        });

        // --- General Not Found HTTP Exception ---
        $exceptions->render(function (NotFoundHttpException $e, Request $request): ?JsonResponse {
            if ($request->is('api/*') || $request->wantsJson()) {
                $message = $e->getMessage() && !str_contains(strtolower($e->getMessage()), 'no query results for model')
                    ? $e->getMessage()
                    : 'The requested API endpoint or resource was not found.';
                return response()->json(['success'=>false,'message' => $message], 404);
            }
            return null;
        });


    })->create();
