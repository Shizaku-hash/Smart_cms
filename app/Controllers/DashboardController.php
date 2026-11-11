<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Services\DashboardService;
use App\Services\PersonalizationService;

final class DashboardController extends BaseController
{
    public function index(Request $request): string
    {
        $dateFrom = $request->query('date_from') ? (string) $request->query('date_from') : null;
        $dateTo = $request->query('date_to') ? (string) $request->query('date_to') : null;
        $data = (new DashboardService())->getDashboardData(Auth::companyId(), $dateFrom, $dateTo);

        return $this->render('dashboard/index', [
            'title' => __('dashboard.title'),
            'dashboard' => $data,
            'dateFrom' => $dateFrom ?? '',
            'dateTo' => $dateTo ?? '',
        ]);
    }

    public function saveWidgets(Request $request): void
    {
        $widgets = $request->input('widgets');
        if (is_array($widgets)) {
            (new PersonalizationService())->updatePreferences((int) Auth::id(), [
                'dashboard_widgets_json' => $widgets,
            ]);
            $this->json(['ok' => true]);
        }
        $this->json(['ok' => false, 'error' => 'Invalid widgets payload'], 422);
    }
}
