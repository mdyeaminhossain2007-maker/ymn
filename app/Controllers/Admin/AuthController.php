<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Session;
use App\Core\View;
use App\Models\Setting;

final class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (Auth::adminCheck()) {
            redirect('admin/dashboard');
        }
        echo View::render('admin.login', [
            'title'     => 'Admin Login',
            '_settings' => Setting::all(),
            '_csrf'     => \App\Core\Security::csrfToken(),
        ], 'blank');
    }

    public function login(): void
    {
        $token = $this->request->raw('_csrf');
        if (!\App\Core\Security::verifyCsrf(is_string($token) ? $token : null)) {
            Session::flash('error', 'Invalid security token.');
            redirect('admin/login');
        }
        $login    = (string) $this->request->input('login', '');
        $password = (string) $this->request->raw('password', '');

        if (Auth::attemptAdmin($login, $password)) {
            redirect('admin/dashboard');
        }
        Session::flash('error', 'Invalid administrator credentials.');
        redirect('admin/login');
    }

    public function logout(): void
    {
        Auth::logoutAdmin();
        redirect('admin/login');
    }
}
