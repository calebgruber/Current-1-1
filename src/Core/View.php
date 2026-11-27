<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    public function __construct(private readonly string $path = BASE_PATH . '/views')
    {
    }

    /**
     * Render a template inside a layout. Templates are plain PHP files that use e() for output.
     */
    public function render(string $template, array $data = [], ?string $layout = 'layouts/app'): string
    {
        $content = $this->partial($template, $data);
        if ($layout === null) {
            return $content;
        }

        return $this->partial($layout, ['content' => $content] + $data);
    }

    public function partial(string $template, array $data = []): string
    {
        $file = $this->path . '/' . $template . '.php';
        if (! preg_match('#^[a-z0-9/_-]+$#', $template) || ! is_file($file)) {
            throw new \RuntimeException('View not found: ' . $template);
        }

        $render = static function (string $__file, array $__data): string {
            extract($__data, EXTR_SKIP);
            ob_start();
            try {
                include $__file;
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }

            return (string) ob_get_clean();
        };

        return $render($file, $data);
    }
}
