<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Security;
use App\Models\Setting;
use App\Models\Log;
use App\Core\View;

/**
 * Base admin controller. Enforces authentication and renders admin views
 * inside the admin layout.
 */
abstract class AdminController extends Controller
{
    protected array $admin;

    public function __construct()
    {
        parent::__construct();
        $admin = Auth::admin();
        if (!$admin) {
            if ($this->request->isAjax()) {
                $this->json(['ok' => false, 'error' => 'unauthorized'], 401);
            }
            redirect('admin/login');
        }
        $this->admin = $admin;
    }

    protected function adminView(string $view, array $data = [], string $active = ''): void
    {
        $data['_settings'] = Setting::all();
        $data['_admin']    = $this->admin;
        $data['_csrf']     = Security::csrfToken();
        $data['_active']   = $active;
        echo View::render($view, $data, 'admin');
    }

    protected function log(string $action, string $entity = '', ?int $entityId = null, string $message = ''): void
    {
        (new Log())->audit((int) $this->admin['id'], $action, $entity, $entityId, $message);
    }

    protected function requirePermission(string $permission): void
    {
        if (!Auth::can($permission)) {
            if ($this->request->isAjax()) {
                $this->json(['ok' => false, 'error' => 'forbidden'], 403);
            }
            $this->abort(403, 'You do not have permission to access this module.');
        }
    }
}
