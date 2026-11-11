<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Clean REST & Web Router with named parameters and middleware support.
 */
final class Router
{
    private array $routes = [];

    public function get(string $pattern, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $pattern, $handler, $middleware);
    }

    public function post(string $pattern, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $pattern, $handler, $middleware);
    }

    public function put(string $pattern, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $pattern, $handler, $middleware);
    }

    public function delete(string $pattern, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $pattern, $handler, $middleware);
    }

    private function addRoute(string $method, string $pattern, callable|array $handler, array $middleware): void
    {
        $normalized = '/' . trim($pattern, '/');
        if ($normalized !== '/') {
            $normalized = rtrim($normalized, '/');
        }
        $regex = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $normalized);
        $regex = '#^' . $regex . '$#';

        $this->routes[$method][] = [
            'pattern' => $normalized,
            'regex' => $regex,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path = $request->path();

        foreach ($this->routes[$method] ?? [] as $route) {
            if (preg_match($route['regex'], $path, $matches)) {
                $params = [];
                foreach ($matches as $key => $val) {
                    if (is_string($key)) {
                        $params[$key] = $val;
                    }
                }

                foreach ($route['middleware'] as $mw) {
                    $this->runMiddleware($mw, $request);
                }

                $handler = $route['handler'];
                if (is_array($handler)) {
                    [$class, $action] = $handler;
                    $controller = new $class();
                    $result = $controller->$action($request, ...array_values($params));
                } else {
                    $result = $handler($request, ...array_values($params));
                }

                if (is_string($result)) {
                    Response::html($result);
                }
                return;
            }
        }

        if ($request->isApi()) {
            Response::json(['error' => 'Endpoint not found', 'path' => $path], 404);
        }

        Response::html(View::render('errors/404', ['title' => '404 Not Found'], null), 404);
    }

    private function runMiddleware(mixed $mw, Request $request): void
    {
        if (is_callable($mw)) {
            $mw($request);
            return;
        }
        if (is_string($mw) && str_contains($mw, ':')) {
            [$class, $arg] = explode(':', $mw, 2);
            (new $class($arg))->handle($request);
            return;
        }
        if (is_string($mw)) {
            (new $mw())->handle($request);
        }
    }
}
