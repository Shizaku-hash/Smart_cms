<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;

final class CsrfMiddleware
{
    public function handle(Request $request): void
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        // Skip CSRF check for stateless Bearer / X-API-Token REST calls
        if (str_starts_with($request->path(), '/api/') && ($request->header('Authorization') || $request->header('X-API-Token'))) {
            return;
        }

        $token = $request->input('_csrf_token') ?? $request->header('X-CSRF-TOKEN');
        if (!Csrf::verify(is_string($token) ? $token : null)) {
            if ($request->isApi()) {
                Response::json(['error' => 'Invalid or expired CSRF token.'], 419);
            }
            Response::redirect($_SERVER['HTTP_REFERER'] ?? '/dashboard', [
                'error' => __('messages.csrf_invalid'),
            ]);
        }
    }
}
