<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Security;
use App\Core\Session;
use App\Models\User;
use App\Models\Favorite;
use App\Models\History;

final class UserController extends Controller
{
    private function guard(): array
    {
        $user = Auth::user();
        if (!$user) {
            redirect('login');
        }
        return $user;
    }

    public function profile(): void
    {
        $user = $this->guard();
        $this->view('user.profile', [
            'title'   => 'My Account',
            'user'    => $user,
            'history' => (new User())->loginHistory((int) $user['id'], 10),
        ], 'frontend');
    }

    public function updateProfile(): void
    {
        $this->requireCsrf();
        $user = $this->guard();
        $userModel = new User();

        $data = [
            'name'  => (string) $this->request->input('name', $user['name']),
            'email' => (string) $this->request->input('email', $user['email']),
        ];
        $password = (string) $this->request->raw('password', '');
        if ($password !== '') {
            if (strlen($password) < 6) {
                Session::flash('error', 'Password must be at least 6 characters.');
                redirect('account');
            }
            $data['password'] = Security::hashPassword($password);
        }
        $userModel->updateById((int) $user['id'], $data);
        Session::flash('success', 'Profile updated.');
        redirect('account');
    }

    public function favorites(): void
    {
        $user = $this->guard();
        $this->view('user.favorites', [
            'title'    => 'My Favorites',
            'channels' => (new Favorite())->forUser((int) $user['id']),
        ], 'frontend');
    }

    public function history(): void
    {
        $user = $this->guard();
        $this->view('user.history', [
            'title'    => 'Watch History',
            'channels' => (new History())->recent((int) $user['id'], session_id(), 50),
        ], 'frontend');
    }

    public function devices(): void
    {
        $user = $this->guard();
        $this->view('user.devices', [
            'title'   => 'Devices & Security',
            'history' => (new User())->loginHistory((int) $user['id'], 30),
        ], 'frontend');
    }

    public function revokeDevice(): void
    {
        $this->requireCsrf();
        $this->guard();
        Auth::logout();
        redirect('login');
    }
}
