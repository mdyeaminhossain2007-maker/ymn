<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\Setting;

final class SettingsController extends AdminController
{
    private const GENERAL_KEYS = [
        'site_title', 'site_tagline', 'logo', 'favicon', 'meta_description',
        'footer_text', 'analytics_code', 'enable_register', 'autoplay',
        'startup_channel', 'anti_hotlink',
    ];

    private const THEME_KEYS = [
        'theme_mode', 'primary_color', 'accent_color', 'background_color', 'glassmorphism',
    ];

    public function index(): void
    {
        $this->requirePermission('settings');
        $this->adminView('admin.settings', [
            'title'    => 'Settings',
            'settings' => Setting::all(),
        ], 'settings');
    }

    public function save(): void
    {
        $this->requirePermission('settings');
        $this->requireCsrf();
        foreach (self::GENERAL_KEYS as $key) {
            $val = $this->request->raw($key);
            if ($val !== null) {
                Setting::put($key, is_string($val) ? $val : (string) $val);
            }
        }
        // checkboxes that may be absent when unchecked
        foreach (['enable_register', 'autoplay', 'anti_hotlink'] as $flag) {
            Setting::put($flag, $this->request->input($flag) ? '1' : '0');
        }
        $this->log('update', 'settings', null, 'Updated general settings');
        $this->json(['ok' => true]);
    }

    public function themes(): void
    {
        $this->requirePermission('settings');
        $this->adminView('admin.themes', [
            'title'    => 'Themes',
            'settings' => Setting::all(),
        ], 'themes');
    }

    public function saveTheme(): void
    {
        $this->requirePermission('settings');
        $this->requireCsrf();
        foreach (self::THEME_KEYS as $key) {
            $val = $this->request->raw($key);
            if ($val !== null) {
                Setting::put($key, (string) $val);
            }
        }
        Setting::put('glassmorphism', $this->request->input('glassmorphism') ? '1' : '0');
        $this->log('update', 'theme', null, 'Updated theme');
        $this->json(['ok' => true]);
    }
}
