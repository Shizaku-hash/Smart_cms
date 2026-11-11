<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

final class AuthMiddleware
{
    public function handle(Request $request): void
    {
        if (str_starts_with($request->path(), '/api/')) {
            if (!Auth::authenticateApiToken($request)) {
                Response::json(['error' => 'Unauthorized. Valid session or API token required.'], 401);
            }
            return;
        }

        if (!Auth::check()) {
            if ($request->wantsJson()) {
                Response::json(['error' => 'Authentication required'], 401);
            }
            Response::redirect('/login');
        }
    }
}
