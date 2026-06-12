<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Setting;

/**
 * Base controller providing view rendering and request access.
 */
abstract class Controller
{
    protected Request $request;

    public function __construct()
    {
        $this->request = new Request();
    }

    /**
     * Render a view inside a layout and echo it.
     *
     * @param array<string, mixed> $data
     */
    protected function view(string $view, array $data = [], string $layout = 'frontend'): void
    {
        $data['_settings'] = Setting::all();
        $data['_csrf']     = Security::csrfToken();
        $data['_user']     = Auth::user();
        echo View::render($view, $data, $layout);
    }

    protected function json($data, int $status = 200): void
    {
        json_response($data, $status);
    }

    protected function requireCsrf(): void
    {
        $token = $this->request->raw('_csrf') ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!Security::verifyCsrf(is_string($token) ? $token : null)) {
            $this->json(['ok' => false, 'error' => 'Invalid security token.'], 419);
        }
    }

    protected function abort(int $status, string $message = ''): void
    {
        http_response_code($status);
        echo View::render('errors.error', ['status' => $status, 'message' => $message], 'frontend');
        exit;
    }
}
