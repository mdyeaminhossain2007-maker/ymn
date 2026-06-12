<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Application kernel: registers routes and dispatches the request.
 *
 * Routes support {param} placeholders which are passed to the controller
 * action as ordered arguments.
 */
final class App
{
    /** @var array<int, array{method:string, pattern:string, handler:array}> */
    private array $routes = [];

    public function __construct()
    {
        Session::start();
        Security::enforceIpBlock();
        $this->registerRoutes();
    }

    private function add(string $method, string $pattern, string $controller, string $action): void
    {
        $this->routes[] = [
            'method'  => strtoupper($method),
            'pattern' => $pattern,
            'handler' => [$controller, $action],
        ];
    }

    private function registerRoutes(): void
    {
        // ---- Frontend ----
        $this->add('GET', '/', 'HomeController', 'index');
        $this->add('GET', '/watch', 'HomeController', 'watch');
        $this->add('GET', '/channel/{number}', 'ChannelController', 'show');
        $this->add('GET', '/category/{slug}', 'HomeController', 'category');
        $this->add('GET', '/search', 'HomeController', 'search');
        $this->add('GET', '/page/{slug}', 'PageController', 'show');

        // ---- User auth ----
        $this->add('GET', '/login', 'AuthController', 'showLogin');
        $this->add('POST', '/login', 'AuthController', 'login');
        $this->add('GET', '/register', 'AuthController', 'showRegister');
        $this->add('POST', '/register', 'AuthController', 'register');
        $this->add('GET', '/logout', 'AuthController', 'logout');
        $this->add('GET', '/forgot-password', 'AuthController', 'showForgot');
        $this->add('POST', '/forgot-password', 'AuthController', 'forgot');
        $this->add('GET', '/reset-password', 'AuthController', 'showReset');
        $this->add('POST', '/reset-password', 'AuthController', 'reset');

        // ---- User area ----
        $this->add('GET', '/account', 'UserController', 'profile');
        $this->add('POST', '/account', 'UserController', 'updateProfile');
        $this->add('GET', '/account/favorites', 'UserController', 'favorites');
        $this->add('GET', '/account/history', 'UserController', 'history');
        $this->add('GET', '/account/devices', 'UserController', 'devices');
        $this->add('POST', '/account/devices/revoke', 'UserController', 'revokeDevice');

        // ---- Public API (AJAX) ----
        $this->add('GET', '/api/channels', 'ApiController', 'channels');
        $this->add('GET', '/api/channel/{number}', 'ApiController', 'channel');
        $this->add('GET', '/api/search', 'ApiController', 'search');
        $this->add('GET', '/api/categories', 'ApiController', 'categories');
        $this->add('GET', '/api/stream/{id}', 'ApiController', 'streamToken');
        $this->add('POST', '/api/favorite', 'ApiController', 'toggleFavorite');
        $this->add('POST', '/api/history', 'ApiController', 'recordHistory');
        $this->add('GET', '/api/me', 'ApiController', 'me');
        $this->add('GET', '/stream/{id}', 'StreamController', 'play');

        // ---- SEO ----
        $this->add('GET', '/sitemap.xml', 'SitemapController', 'sitemap');
        $this->add('GET', '/robots.txt', 'SitemapController', 'robots');

        // ---- Admin auth ----
        $this->add('GET', '/admin/login', 'Admin\\AuthController', 'showLogin');
        $this->add('POST', '/admin/login', 'Admin\\AuthController', 'login');
        $this->add('GET', '/admin/logout', 'Admin\\AuthController', 'logout');

        // ---- Admin dashboard ----
        $this->add('GET', '/admin', 'Admin\\DashboardController', 'index');
        $this->add('GET', '/admin/dashboard', 'Admin\\DashboardController', 'index');
        $this->add('GET', '/admin/dashboard/stats', 'Admin\\DashboardController', 'stats');

        // ---- Admin: channels ----
        $this->add('GET', '/admin/channels', 'Admin\\ChannelsController', 'index');
        $this->add('GET', '/admin/channels/list', 'Admin\\ChannelsController', 'list');
        $this->add('POST', '/admin/channels/save', 'Admin\\ChannelsController', 'save');
        $this->add('POST', '/admin/channels/delete', 'Admin\\ChannelsController', 'delete');
        $this->add('POST', '/admin/channels/clone', 'Admin\\ChannelsController', 'duplicate');
        $this->add('POST', '/admin/channels/reorder', 'Admin\\ChannelsController', 'reorder');
        $this->add('POST', '/admin/channels/import', 'Admin\\ChannelsController', 'import');
        $this->add('GET', '/admin/channels/export', 'Admin\\ChannelsController', 'export');

        // ---- Admin: categories ----
        $this->add('GET', '/admin/categories', 'Admin\\CategoriesController', 'index');
        $this->add('GET', '/admin/categories/list', 'Admin\\CategoriesController', 'list');
        $this->add('POST', '/admin/categories/save', 'Admin\\CategoriesController', 'save');
        $this->add('POST', '/admin/categories/delete', 'Admin\\CategoriesController', 'delete');

        // ---- Admin: users ----
        $this->add('GET', '/admin/users', 'Admin\\UsersController', 'index');
        $this->add('GET', '/admin/users/list', 'Admin\\UsersController', 'list');
        $this->add('POST', '/admin/users/save', 'Admin\\UsersController', 'save');
        $this->add('POST', '/admin/users/delete', 'Admin\\UsersController', 'delete');

        // ---- Admin: ads ----
        $this->add('GET', '/admin/ads', 'Admin\\AdsController', 'index');
        $this->add('GET', '/admin/ads/list', 'Admin\\AdsController', 'list');
        $this->add('POST', '/admin/ads/save', 'Admin\\AdsController', 'save');
        $this->add('POST', '/admin/ads/delete', 'Admin\\AdsController', 'delete');

        // ---- Admin: pages & menus ----
        $this->add('GET', '/admin/pages', 'Admin\\PagesController', 'index');
        $this->add('GET', '/admin/pages/list', 'Admin\\PagesController', 'list');
        $this->add('POST', '/admin/pages/save', 'Admin\\PagesController', 'save');
        $this->add('POST', '/admin/pages/delete', 'Admin\\PagesController', 'delete');
        $this->add('GET', '/admin/menus', 'Admin\\MenusController', 'index');
        $this->add('GET', '/admin/menus/list', 'Admin\\MenusController', 'list');
        $this->add('POST', '/admin/menus/save', 'Admin\\MenusController', 'save');
        $this->add('POST', '/admin/menus/delete', 'Admin\\MenusController', 'delete');

        // ---- Admin: settings / themes ----
        $this->add('GET', '/admin/settings', 'Admin\\SettingsController', 'index');
        $this->add('POST', '/admin/settings/save', 'Admin\\SettingsController', 'save');
        $this->add('GET', '/admin/themes', 'Admin\\SettingsController', 'themes');
        $this->add('POST', '/admin/themes/save', 'Admin\\SettingsController', 'saveTheme');

        // ---- Admin: stream monitor ----
        $this->add('GET', '/admin/stream-monitor', 'Admin\\StreamMonitorController', 'index');
        $this->add('GET', '/admin/stream-monitor/check', 'Admin\\StreamMonitorController', 'check');
        $this->add('GET', '/admin/stream-monitor/list', 'Admin\\StreamMonitorController', 'list');

        // ---- Admin: roles & administrators ----
        $this->add('GET', '/admin/roles', 'Admin\\RolesController', 'index');
        $this->add('GET', '/admin/roles/list', 'Admin\\RolesController', 'list');
        $this->add('POST', '/admin/roles/save', 'Admin\\RolesController', 'save');
        $this->add('POST', '/admin/roles/delete', 'Admin\\RolesController', 'delete');
        $this->add('GET', '/admin/administrators', 'Admin\\AdministratorsController', 'index');
        $this->add('GET', '/admin/administrators/list', 'Admin\\AdministratorsController', 'list');
        $this->add('POST', '/admin/administrators/save', 'Admin\\AdministratorsController', 'save');
        $this->add('POST', '/admin/administrators/delete', 'Admin\\AdministratorsController', 'delete');

        // ---- Admin: analytics & logs ----
        $this->add('GET', '/admin/analytics', 'Admin\\AnalyticsController', 'index');
        $this->add('GET', '/admin/analytics/data', 'Admin\\AnalyticsController', 'data');
        $this->add('GET', '/admin/logs', 'Admin\\LogsController', 'index');
        $this->add('GET', '/admin/logs/list', 'Admin\\LogsController', 'list');
        $this->add('POST', '/admin/logs/clear', 'Admin\\LogsController', 'clear');
        $this->add('GET', '/admin/search', 'Admin\\DashboardController', 'globalSearch');
    }

    public function run(): void
    {
        $request = new Request();
        $method  = $request->method();
        $path    = $request->path();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            $regex = $this->compile($route['pattern']);
            if (preg_match($regex, $path, $matches)) {
                array_shift($matches);
                $this->dispatch($route['handler'], array_values($matches));
                return;
            }
        }

        $this->notFound($request);
    }

    private function compile(string $pattern): string
    {
        $regex = preg_replace('#\{([a-zA-Z_]+)\}#', '([^/]+)', $pattern);
        return '#^' . $regex . '$#';
    }

    private function dispatch(array $handler, array $params): void
    {
        [$controller, $action] = $handler;
        $class = 'App\\Controllers\\' . $controller;

        if (!class_exists($class)) {
            $this->serverError("Controller not found: {$class}");
            return;
        }

        $instance = new $class();
        if (!method_exists($instance, $action)) {
            $this->serverError("Action not found: {$class}::{$action}");
            return;
        }

        try {
            $instance->$action(...$params);
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    private function notFound(Request $request): void
    {
        http_response_code(404);
        if ($request->isAjax()) {
            json_response(['ok' => false, 'error' => 'Not found'], 404);
        }
        echo View::render('errors.error', ['status' => 404, 'message' => 'Page not found'], 'frontend');
    }

    private function serverError(string $message): void
    {
        http_response_code(500);
        if (config('APP_DEBUG', false)) {
            echo '<pre>' . e($message) . '</pre>';
            return;
        }
        echo View::render('errors.error', ['status' => 500, 'message' => 'Server error'], 'frontend');
    }

    private function handleException(\Throwable $e): void
    {
        try {
            (new \App\Models\Log())->error($e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        } catch (\Throwable $ignored) {
        }

        http_response_code(500);
        if (config('APP_DEBUG', false)) {
            echo '<h1>Exception</h1><pre>' . e($e->getMessage()) . "\n" . e($e->getTraceAsString()) . '</pre>';
            return;
        }
        echo View::render('errors.error', ['status' => 500, 'message' => 'Something went wrong'], 'frontend');
    }
}
