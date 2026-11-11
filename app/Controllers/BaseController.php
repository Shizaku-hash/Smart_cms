<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\Company;
use App\Models\Notification;

abstract class BaseController
{
    protected function render(string $view, array $data = [], ?string $layout = 'layouts/app'): string
    {
        $shared = $this->sharedData();
        return View::render($view, array_merge($shared, $data), $layout);
    }

    protected function sharedData(): array
    {
        $user = Auth::user();
        $companyId = Auth::companyId();
        $prefs = Auth::preferences();
        $company = (new Company())->find($companyId) ?? ['name' => 'SmartCMS', 'default_currency_code' => 'USD'];
        $unreadCount = 0;
        $recentNotifications = [];

        if ($user !== null) {
            $notifModel = new Notification();
            $unreadCount = $notifModel->unreadCount($companyId, (int) $user['id']);
            $recentNotifications = array_slice($notifModel->forUser($companyId, (int) $user['id']), 0, 5);
        }

        return [
            'currentUser' => $user,
            'company' => $company,
            'prefs' => $prefs,
            'unreadCount' => $unreadCount,
            'recentNotifications' => $recentNotifications,
            'currentPath' => (new Request())->path(),
            'flashSuccess' => flash('success'),
            'flashError' => flash('error'),
        ];
    }

    protected function json(array $payload, int $status = 200): never
    {
        Response::json($payload, $status);
    }

    protected function redirect(string $url, array $flash = []): never
    {
        Response::redirect($url, $flash);
    }
}
