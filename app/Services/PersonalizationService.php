<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Models\UserPreference;

final class PersonalizationService
{
    private UserPreference $prefModel;

    public function __construct()
    {
        $this->prefModel = new UserPreference();
    }

    public function updatePreferences(int $userId, array $input): void
    {
        $allowed = [];
        foreach (['language', 'theme', 'date_format', 'number_format', 'currency_format', 'default_landing_page'] as $k) {
            if (isset($input[$k]) && is_string($input[$k])) {
                $allowed[$k] = trim($input[$k]);
            }
        }
        if (isset($input['sidebar_collapsed'])) {
            $allowed['sidebar_collapsed'] = !empty($input['sidebar_collapsed']) ? 1 : 0;
        }
        foreach (['dashboard_widgets_json', 'hidden_sections_json', 'pinned_functions_json', 'default_filters_json', 'table_columns_json', 'shortcuts_json', 'notification_prefs_json'] as $jsonKey) {
            if (array_key_exists($jsonKey, $input)) {
                $allowed[$jsonKey] = is_string($input[$jsonKey]) ? $input[$jsonKey] : json_encode($input[$jsonKey], JSON_UNESCAPED_UNICODE);
            }
        }

        if ($allowed !== []) {
            $this->prefModel->saveForUser($userId, $allowed);
            if (isset($allowed['language']) && session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['app_locale'] = $allowed['language'];
            }
            Auth::clearCache();
        }
    }
}
