<?php

declare(strict_types=1);

namespace App\Core;

final class App
{
    private static ?self $instance = null;

    private ?Database $db = null;
    private Auth $auth;
    private Settings $settings;
    private View $view;
    private Router $router;
    private ?Request $request = null;
    private ?bool $installed = null;

    public function __construct(
        private array $config,
        private readonly Session $session,
        private readonly string $basePath = '',
    ) {
        self::$instance = $this;
        $this->view = new View();
        $this->router = new Router();
        (require BASE_PATH . '/src/routes.php')($this->router);
        $this->boot();
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            throw new \LogicException('The app has not been created yet.');
        }

        return self::$instance;
    }

    /** Read storage/config.php (written by the setup wizard). */
    public static function loadConfig(): array
    {
        $file = BASE_PATH . '/storage/config.php';

        return is_file($file) ? (array) require $file : [];
    }

    private function boot(): void
    {
        $this->db = null;
        $this->installed = null;
        if (isset($this->config['db'])) {
            try {
                $this->db = new Database($this->config['db']);
            } catch (\PDOException $e) {
                $this->db = null;
            }
        }
        $this->auth = new Auth($this->session, $this->db);
        $this->settings = new Settings($this->db);
    }

    /** Swap the configuration (used by the setup wizard once it has written config.php). */
    public function reconfigure(array $config): void
    {
        $this->config = $config;
        $this->boot();
    }

    public function config(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->config : ($this->config[$key] ?? $default);
    }

    public function db(): Database
    {
        if ($this->db === null) {
            throw new HttpException(503, 'The database is not configured yet.');
        }

        return $this->db;
    }

    public function hasDatabase(): bool
    {
        return $this->db !== null;
    }

    public function session(): Session
    {
        return $this->session;
    }

    public function auth(): Auth
    {
        return $this->auth;
    }

    public function settings(): Settings
    {
        return $this->settings;
    }

    public function view(): View
    {
        return $this->view;
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function request(): ?Request
    {
        return $this->request;
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    /** The app is installed once the database is reachable, migrated and has a user. */
    public function installed(): bool
    {
        if ($this->installed === null) {
            try {
                $this->installed = $this->db !== null
                    && $this->db->tableExists('users')
                    && (int) $this->db->value('SELECT COUNT(*) FROM users') > 0;
            } catch (\PDOException) {
                $this->installed = false;
            }
        }

        return $this->installed;
    }

    public function handle(Request $request): Response
    {
        $this->request = $request;
        $this->session->ageFlash();

        try {
            return $this->dispatch($request);
        } catch (ValidationException $e) {
            $this->session->flash('_errors', $e->errors);
            $this->session->flash('_old_input', array_filter(
                $request->post,
                static fn ($v, $k) => is_scalar($v) && ! in_array($k, ['_token', 'password', 'password_confirmation'], true),
                ARRAY_FILTER_USE_BOTH,
            ));
            $this->session->flash('error', 'Please fix the highlighted fields.');
            if ($request->wantsJson()) {
                return Response::json(['message' => 'Please fix the highlighted fields.', 'errors' => $e->errors], 422);
            }

            return Response::redirect($this->backUrl($request));
        } catch (HttpException $e) {
            return $this->errorResponse($request, $e->status, $e->getMessage());
        } catch (\Throwable $e) {
            $this->log($e);
            $message = ($this->config['debug'] ?? false)
                ? $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()
                : 'Something went wrong on our side. The error has been logged.';

            return $this->errorResponse($request, 500, $message);
        }
    }

    private function dispatch(Request $request): Response
    {
        $match = $this->router->match($request->method, $request->path);
        if ($match === null) {
            throw new HttpException(404);
        }
        $options = $match['route']['options'];
        $isSetupRoute = ! empty($options['setup']);

        if (! $isSetupRoute && empty($options['public']) && ! $this->installed()) {
            return Response::redirect(url('/setup'));
        }

        if ($request->method === 'POST' && ($options['csrf'] ?? true)) {
            $token = (string) ($request->post['_token'] ?? ($request->server['HTTP_X_CSRF_TOKEN'] ?? ''));
            if (! hash_equals($this->session->csrfToken(), $token)) {
                throw new HttpException(419);
            }
        }

        if (! empty($options['guest']) && $this->auth->check()) {
            return Response::redirect(url('/'));
        }

        if ($options['auth'] && ! $this->auth->check()) {
            if ($request->wantsJson()) {
                return Response::json(['message' => 'Please sign in.'], 401);
            }
            if ($request->method === 'GET') {
                $this->session->put('intended', $request->path . ($request->query ? '?' . http_build_query($request->query) : ''));
            }

            return Response::redirect(url('/login'));
        }

        if (! empty($options['can']) && ! $this->auth->can($options['can'])) {
            throw new HttpException(403);
        }

        [$class, $method] = $match['route']['handler'];
        $controller = new $class($this, $request);
        $params = array_values($match['params']);

        return $controller->{$method}(...$params);
    }

    private function backUrl(Request $request): string
    {
        $referer = $request->referer();
        $host = $request->server['HTTP_HOST'] ?? '';
        if ($referer && ($parts = parse_url($referer)) && ($parts['host'] ?? '') === $host && isset($parts['path'])) {
            return $parts['path'] . (isset($parts['query']) ? '?' . $parts['query'] : '');
        }

        return url($request->path);
    }

    private function errorResponse(Request $request, int $status, string $message): Response
    {
        if ($request->wantsJson() || str_starts_with($request->path, '/api/')) {
            return Response::json(['message' => $message], $status);
        }
        $layout = $this->auth->check() && $this->installed() ? 'layouts/app' : 'layouts/guest';
        try {
            $html = $this->view->render('errors/error', [
                'title' => $status === 404 ? 'Not found' : 'Error ' . $status,
                'status' => $status,
                'message' => $message,
            ], $layout);
        } catch (\Throwable $e) {
            $this->log($e);
            $html = '<h1>Error ' . $status . '</h1><p>' . e($message) . '</p>';
        }

        return Response::html($html, $status);
    }

    private function log(\Throwable $e): void
    {
        $dir = BASE_PATH . '/storage/logs';
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents(
            $dir . '/app-' . date('Y-m-d') . '.log',
            '[' . date('c') . '] ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString() . "\n\n",
            FILE_APPEND,
        );
    }
}
