<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Models\Notification;
use App\Services\NotificationService;
use App\Services\PersonalizationService;

final class NotificationController extends BaseController
{
    public function index(Request $request): string
    {
        $notifications = (new Notification())->forUser(Auth::companyId(), (int) Auth::id());
        return $this->render('notifications/index', [
            'title' => __('notifications.title'),
            'notifications' => $notifications,
        ]);
    }

    public function markRead(Request $request, string $id): void
    {
        (new NotificationService())->markRead((int) $id, (int) Auth::id());
        if ($request->wantsJson()) {
            $this->json(['ok' => true]);
        }
        $this->redirect('/notifications', ['success' => __('messages.saved')]);
    }

    public function markAllRead(Request $request): void
    {
        (new NotificationService())->markAllRead(Auth::companyId(), (int) Auth::id());
        $this->redirect('/notifications', ['success' => __('messages.saved')]);
    }

    public function savePreferences(Request $request): void
    {
        $types = ['salary_upcoming', 'expense_pending', 'financial_alert', 'task_deadline', 'task_assigned', 'employee_update', 'system'];
        $prefs = [];
        foreach ($types as $t) {
            $prefs[$t] = !empty($request->input($t));
        }
        (new PersonalizationService())->updatePreferences((int) Auth::id(), [
            'notification_prefs_json' => $prefs,
        ]);
        $this->redirect('/notifications', ['success' => __('messages.saved')]);
    }
}
