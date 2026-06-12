<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Security;
use App\Core\Session;
use App\Models\User;
use App\Models\Setting;

final class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            redirect('account');
        }
        $this->view('auth.login', ['title' => 'Sign In'], 'auth');
    }

    public function login(): void
    {
        $this->requireCsrf();
        $login    = (string) $this->request->input('login', '');
        $password = (string) $this->request->raw('password', '');
        $remember = (bool) $this->request->input('remember', false);

        if (Auth::attempt($login, $password, $remember)) {
            redirect('account');
        }
        Session::flash('error', 'Invalid credentials or inactive account.');
        $this->view('auth.login', ['title' => 'Sign In', 'login' => $login], 'auth');
    }

    public function showRegister(): void
    {
        if (Setting::get('enable_register', '1') !== '1') {
            $this->abort(403, 'Registration is disabled.');
            return;
        }
        $this->view('auth.register', ['title' => 'Create Account'], 'auth');
    }

    public function register(): void
    {
        $this->requireCsrf();
        if (Setting::get('enable_register', '1') !== '1') {
            $this->abort(403, 'Registration is disabled.');
            return;
        }
        $data = $this->request->only(['name', 'email', 'username']);
        $password = (string) $this->request->raw('password', '');
        $userModel = new User();

        $errors = [];
        if (strlen($data['name']) < 2)                            $errors[] = 'Name is required.';
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL))   $errors[] = 'Valid email required.';
        if (strlen($data['username']) < 3)                        $errors[] = 'Username too short.';
        if (strlen($password) < 6)                                $errors[] = 'Password must be 6+ characters.';
        if (!$errors && $userModel->findBy('email', $data['email'])) $errors[] = 'Email already registered.';
        if (!$errors && $userModel->findBy('username', $data['username'])) $errors[] = 'Username taken.';

        if ($errors) {
            Session::flash('error', implode(' ', $errors));
            $this->view('auth.register', ['title' => 'Create Account', 'old' => $data], 'auth');
            return;
        }

        $id = $userModel->create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'username' => $data['username'],
            'password' => Security::hashPassword($password),
            'status'   => 1,
        ]);
        Session::set('user_id', $id);
        redirect('account');
    }

    public function logout(): void
    {
        Auth::logout();
        redirect('/');
    }

    public function showForgot(): void
    {
        $this->view('auth.forgot', ['title' => 'Forgot Password'], 'auth');
    }

    public function forgot(): void
    {
        $this->requireCsrf();
        $email = (string) $this->request->input('email', '');
        $user = (new User())->findBy('email', $email);
        if ($user) {
            $token = bin2hex(random_bytes(24));
            (new User())->setResetToken((int) $user['id'], $token);
            // In production wire this to your mailer. We surface the link for
            // shared-hosting environments without SMTP configured.
            Session::flash('reset_link', base_url('reset-password?token=' . $token));
        }
        Session::flash('success', 'If the email exists, a reset link has been generated.');
        $this->view('auth.forgot', ['title' => 'Forgot Password'], 'auth');
    }

    public function showReset(): void
    {
        $token = (string) $this->request->input('token', '');
        $this->view('auth.reset', ['title' => 'Reset Password', 'token' => $token], 'auth');
    }

    public function reset(): void
    {
        $this->requireCsrf();
        $token = (string) $this->request->input('token', '');
        $password = (string) $this->request->raw('password', '');
        $user = (new User())->findByResetToken($token);
        if (!$user || strlen($password) < 6) {
            Session::flash('error', 'Invalid token or password too short.');
            $this->view('auth.reset', ['title' => 'Reset Password', 'token' => $token], 'auth');
            return;
        }
        (new User())->updateById((int) $user['id'], [
            'password'      => Security::hashPassword($password),
            'reset_token'   => null,
            'reset_expires' => null,
        ]);
        Session::flash('success', 'Password updated. You can now sign in.');
        redirect('login');
    }
}
