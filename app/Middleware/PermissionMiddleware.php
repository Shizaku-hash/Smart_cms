<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

final class PermissionMiddleware
{
    public function __construct(private readonly string $permission)
    {
    }

    public function handle(Request $request): void
    {
        if (!Auth::can($this->permission)) {
            if ($request->isApi()) {
                Response::json(['error' => 'Forbidden: missing permission ' . $this->permission], 403);
            }
            Response::html(View::render('errors/403', [
                'title' => '403 Forbidden',
                'requiredPermission' => $this->permission,
            ]), 403);
        }
    }
}
