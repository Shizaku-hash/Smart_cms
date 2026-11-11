<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Application bootstrapper initializing configuration, sessions, locale, and exception handling.
 */
final class Application
{
    public function __construct(private readonly Router $router)
    {
    }

    public function run(): void
    {
        date_default_timezone_set((string) config('timezone', 'Asia/Tashkent'));
        Auth::startSession();

        // Sync translator locale from user preferences or session override
        $sessionLang = $_SESSION['app_locale'] ?? null;
        if (is_string($sessionLang)) {
            Translator::setLocale($sessionLang);
        } elseif (Auth::check()) {
            $prefs = Auth::preferences();
            Translator::setLocale((string) ($prefs['language'] ?? 'en'));
        }

        $request = new Request();

        try {
            $this->router->dispatch($request);
        } catch (Throwable $e) {
            $debug = (bool) config('debug', false);
            $logLine = sprintf("[%s] %s in %s:%d\n", date('Y-m-d H:i:s'), $e->getMessage(), $e->getFile(), $e->getLine());
            @file_put_contents(config('log_path') . '/app.log', $logLine, FILE_APPEND);

            if ($request->isApi()) {
                Response::json([
                    'error' => $debug ? $e->getMessage() : 'Internal server error',
                    'trace' => $debug ? $e->getTraceAsString() : null,
                ], 500);
            }

            $msg = $debug ? '<pre>' . e($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>' : 'An unexpected error occurred.';
            Response::html('<!doctype html><html><body style="font-family:sans-serif;padding:2rem;"><h2>Application Error</h2>' . $msg . '</body></html>', 500);
        }
    }
}
