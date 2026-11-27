<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<int, array{method:string, pattern:string, regex:string, handler:array, options:array}> */
    private array $routes = [];

    /**
     * @param array{0: class-string, 1: string} $handler
     * @param array{auth?: bool, can?: string, guest?: bool, csrf?: bool} $options
     */
    public function add(string $method, string $pattern, array $handler, array $options = []): void
    {
        $regex = '#^' . preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[0-9]+)', $pattern) . '$#';
        $regex = str_replace('(?P<token>[0-9]+)', '(?P<token>[A-Za-z0-9]+)', $regex);
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'regex' => $regex,
            'handler' => $handler,
            'options' => $options + ['auth' => true],
        ];
    }

    public function get(string $pattern, array $handler, array $options = []): void
    {
        $this->add('GET', $pattern, $handler, $options);
    }

    public function post(string $pattern, array $handler, array $options = []): void
    {
        $this->add('POST', $pattern, $handler, $options);
    }

    /** @return array{route: array, params: array<string, string>}|null */
    public function match(string $method, string $path): ?array
    {
        $pathMatched = false;
        foreach ($this->routes as $route) {
            if (! preg_match($route['regex'], $path, $m)) {
                continue;
            }
            $pathMatched = true;
            if ($route['method'] !== $method && ! ($method === 'HEAD' && $route['method'] === 'GET')) {
                continue;
            }
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);

            return ['route' => $route, 'params' => $params];
        }
        if ($pathMatched) {
            throw new HttpException(405, 'That action is not allowed here.');
        }

        return null;
    }

    public function routes(): array
    {
        return $this->routes;
    }
}
