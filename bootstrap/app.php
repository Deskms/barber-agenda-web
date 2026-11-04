<?php

use App\Exceptions\CitaNoModificableException;
use App\Exceptions\HorarioNoDisponibleException;
use App\Http\Middleware\EnsureRol;
use App\Http\Middleware\EnsureUsuarioActivo;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'rol' => EnsureRol::class,
            'activo' => EnsureUsuarioActivo::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Respuestas en español y con forma estable para los frontends.
        $exceptions->render(fn (AuthenticationException $e, Request $request) => $request->is('api/*')
            ? response()->json(['message' => 'No autenticado.'], 401) : null);

        $exceptions->render(fn (AccessDeniedHttpException $e, Request $request) => $request->is('api/*')
            ? response()->json(['message' => 'No tienes permiso para esta acción.'], 403) : null);

        $exceptions->render(fn (NotFoundHttpException $e, Request $request) => $request->is('api/*')
            ? response()->json(['message' => $e->getMessage() !== '' && ! str_contains($e->getMessage(), 'No query results')
                ? $e->getMessage() : 'Recurso no encontrado.'], 404) : null);

        $exceptions->render(fn (HorarioNoDisponibleException $e) => response()->json([
            'message' => $e->getMessage(), 'motivo' => $e->motivo,
        ], 409));

        $exceptions->render(fn (CitaNoModificableException $e) => response()->json([
            'message' => $e->getMessage(),
        ], 422));
    })->create();
