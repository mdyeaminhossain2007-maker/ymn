<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal PHP template renderer with layout support.
 */
final class View
{
    /**
     * Render a view file with the given data, optionally wrapped in a layout.
     *
     * @param array<string, mixed> $data
     */
    public static function render(string $view, array $data = [], ?string $layout = null): string
    {
        $file = APP_PATH . '/Views/' . str_replace('.', '/', $view) . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View not found: {$view}");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        $content = (string) ob_get_clean();

        if ($layout !== null) {
            $layoutFile = APP_PATH . '/Views/layouts/' . $layout . '.php';
            if (is_file($layoutFile)) {
                extract($data, EXTR_SKIP);
                ob_start();
                require $layoutFile;
                $content = (string) ob_get_clean();
            }
        }

        return $content;
    }
}
