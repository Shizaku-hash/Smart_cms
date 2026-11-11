<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Native PHP view renderer with layout support and XSS-safe helpers.
 */
final class View
{
    public static function render(string $template, array $data = [], ?string $layout = 'layouts/app'): string
    {
        $viewsDir = dirname(__DIR__, 2) . '/resources/views/';
        $templateFile = $viewsDir . str_replace('.', '/', $template) . '.php';

        if (!is_file($templateFile)) {
            throw new RuntimeException("View not found: {$templateFile}");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $templateFile;
        $content = (string) ob_get_clean();

        if ($layout === null) {
            return $content;
        }

        $layoutFile = $viewsDir . str_replace('.', '/', $layout) . '.php';
        if (!is_file($layoutFile)) {
            throw new RuntimeException("Layout not found: {$layoutFile}");
        }

        ob_start();
        require $layoutFile;
        return (string) ob_get_clean();
    }

    public static function partial(string $partial, array $data = []): string
    {
        return self::render($partial, $data, null);
    }
}
