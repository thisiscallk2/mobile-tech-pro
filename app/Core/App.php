<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal front-controller router.
 * Register routes with get()/post(); run() dispatches the current request
 * to a [Controller::class, 'method'] handler. Supports static paths and
 * simple {param} placeholders (e.g. /admin/users/edit/{id}) which are
 * passed to the controller method as string arguments in order.
 * Paths are matched relative to the front controller so the app also works
 * from a sub-directory.
 */
class App
{
    /** @var array<string, array<int, array{segments:array<int,string>, handler:array{0:class-string,1:string}}>> */
    private array $routes = [
        'GET' => [],
        'POST' => [],
    ];

    /**
     * @param array{0:class-string,1:string} $handler
     */
    public function get(string $path, array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    /**
     * @param array{0:class-string,1:string} $handler
     */
    public function post(string $path, array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    /**
     * @param array{0:class-string,1:string} $handler
     */
    private function add(string $method, string $path, array $handler): void
    {
        $this->routes[$method][] = [
            'segments' => $this->segments($path),
            'handler' => $handler,
        ];
    }

    public function run(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method === 'HEAD') {
            $method = 'GET';
        }

        $requestSegments = $this->segments($this->currentPath());

        foreach ($this->routes[$method] ?? [] as $route) {
            $params = $this->match($route['segments'], $requestSegments);
            if ($params === null) {
                continue;
            }
            [$class, $action] = $route['handler'];
            $controller = new $class();
            $controller->$action(...$params);
            return;
        }

        $this->notFound();
    }

    /**
     * Match route segments against request segments.
     *
     * @param array<int, string> $routeSegments
     * @param array<int, string> $requestSegments
     * @return array<int, string>|null  captured params, or null if no match
     */
    private function match(array $routeSegments, array $requestSegments): ?array
    {
        if (count($routeSegments) !== count($requestSegments)) {
            return null;
        }

        $params = [];
        foreach ($routeSegments as $i => $seg) {
            $len = strlen($seg);
            if ($len >= 2 && $seg[0] === '{' && $seg[$len - 1] === '}') {
                $params[] = $requestSegments[$i];
                continue;
            }
            if ($seg !== $requestSegments[$i]) {
                return null;
            }
        }

        return $params;
    }

    /**
     * @return array<int, string>
     */
    private function segments(string $path): array
    {
        $trimmed = trim($path, '/');
        return $trimmed === '' ? [] : explode('/', $trimmed);
    }

    private function currentPath(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $uri = is_string($uri) ? rawurldecode($uri) : '/';

        $base = defined('BASE_URL') ? BASE_URL : '';
        if ($base !== '' && strpos($uri, $base) === 0) {
            $uri = substr($uri, strlen($base));
        }

        return '/' . trim($uri, '/');
    }

    private function notFound(): void
    {
        http_response_code(404);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
            . '<title>404 Not Found</title></head><body>'
            . '<h1>404 Not Found</h1><p>The page you requested does not exist.</p>'
            . '</body></html>';
    }
}
