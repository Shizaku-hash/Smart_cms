<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Translator;
use App\Core\Validator;
use App\Services\ActivityLogService;
use App\Services\PersonalizationService;

final class AuthController extends BaseController
{
    public function showLogin(Request $request): string
    {
        if (Auth::check()) {
            $landing = (string) (Auth::preferences()['default_landing_page'] ?? '/dashboard');
            $this->redirect($landing);
        }

        return $this->render('auth/login', [
            'title' => __('auth.login_title'),
        ], 'layouts/auth');
    }

    public function login(Request $request): void
    {
        $identifier = trim((string) $request->input('identifier', ''));
        $password = (string) $request->input('password', '');

        $v = Validator::make(['identifier' => $identifier, 'password' => $password], [
            'identifier' => 'required',
            'password' => 'required',
        ]);
        if ($v->fails()) {
            $this->redirect('/login', ['error' => $v->firstError()]);
        }

        $user = Auth::attempt($identifier, $password);
        if ($user === null) {
            (new ActivityLogService())->log('login_failed', 'auth', null, "Failed login attempt for '{$identifier}'", null, null, 1, null);
            $this->redirect('/login', ['error' => __('auth.invalid_credentials')]);
        }

        $prefs = Auth::preferences();
        Translator::setLocale((string) ($prefs['language'] ?? 'en'));
        $_SESSION['app_locale'] = $prefs['language'] ?? 'en';

        (new ActivityLogService())->log('login', 'auth', (int) $user['id'], "User {$user['username']} signed in", null, null, (int) $user['company_id'], (int) $user['id']);

        $landing = (string) ($prefs['default_landing_page'] ?? '/dashboard');
        if ($landing === '' || $landing === '/') {
            $landing = '/dashboard';
        }
        $this->redirect($landing);
    }

    public function logout(Request $request): void
    {
        $uid = Auth::id();
        $cid = Auth::companyId();
        if ($uid) {
            (new ActivityLogService())->log('logout', 'auth', $uid, 'User signed out', null, null, $cid, $uid);
        }
        Auth::logout();
        $this->redirect('/login');
    }

    public function switchLanguage(Request $request): void
    {
        $lang = (string) $request->input('language', 'en');
        if (in_array($lang, ['en', 'uz', 'ja'], true)) {
            $_SESSION['app_locale'] = $lang;
            if (Auth::check()) {
                (new PersonalizationService())->updatePreferences((int) Auth::id(), ['language' => $lang]);
            }
        }
        $this->redirect($_SERVER['HTTP_REFERER'] ?? '/dashboard');
    }
}
