<?php
// src/Core/Router.php

namespace App\Core;

class Router
{
    protected array $routes = [];
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    // Các phương thức get, post, put, delete không thay đổi
    public function get(string $path, ...$args): void
    {
        $this->addRoute('get', $path, $args);
    }

    public function post(string $path, ...$args): void
    {
        $this->addRoute('post', $path, $args);
    }

    public function put(string $path, ...$args): void
    {
        $this->addRoute('put', $path, $args);
    }

    public function delete(string $path, ...$args): void
    {
        $this->addRoute('delete', $path, $args);
    }

    private function addRoute(string $method, string $path, array $callbackAndMiddleware): void
    {
        $this->routes[$method][$path] = $callbackAndMiddleware;
    }

    /**
     * Hàm dispatch được thiết kế lại hoàn toàn: mạnh mẽ và đơn giản hơn.
     */
    public function dispatch(): void
    {
        $requestPath = $this->request->getPath();
        $requestMethod = $this->request->getMethod();

        $routeCallback = null;
        $routeParams = [];

        // Tìm route phù hợp
        if (isset($this->routes[$requestMethod])) {
            foreach ($this->routes[$requestMethod] as $path => $callback) {
                // Chuyển đổi path của route thành regex (ví dụ: /categories/{id} -> /categories/(\d+))
                $pattern = preg_replace('/\{([a-z0-9_]+)\}/', '([a-zA-Z0-9_-]+)', $path);
                $pattern = "#^" . $pattern . "$#";

                if (preg_match($pattern, $requestPath, $matches)) {
                    array_shift($matches); // Loại bỏ phần full match
                    $routeCallback = $callback;
                    $routeParams = $matches;
                    break;
                }
            }
        }

        // Nếu không tìm thấy route
        if ($routeCallback === null) {
            (new Response(['error' => 'Endpoint Not Found'], 404))->send();
            return;
        }

        // Tách middleware và action
        $action = array_pop($routeCallback);
        $middlewares = $routeCallback;

        // Xây dựng chuỗi thực thi (chain of responsibility)
        $runner = function (Request $request) use ($action, $routeParams) {
            // Nếu action là một mảng [Controller, 'method']
            if (is_array($action)) {
                $controller = $action[0];
                $method = $action[1];
                // Gọi phương thức của controller với request và các tham số từ URL
                return $controller->{$method}($request, ...$routeParams);
            }
            // Nếu action là một Closure
            return call_user_func($action, $request, ...$routeParams);
        };

        // Bắt đầu thực thi chuỗi middleware từ ngoài vào trong
        if (!empty($middlewares)) {
            $runner = array_reduce(
                array_reverse($middlewares),
                function ($next, $middleware) {
                    return function (Request $request) use ($next, $middleware) {
                        return $middleware->handle($request, $next);
                    };
                },
                $runner
            );
        }

        // Chạy và gửi response
        try {
            $response = $runner($this->request);
            if ($response instanceof Response) {
                $response->send();
            } else {
                (new Response(['error' => 'Invalid response from controller'], 500))->send();
            }
        } catch (\Exception $e) {
            $code = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : ($e->getCode() ?: 500);
            (new Response(['error' => 'An unexpected error occurred', 'message' => $e->getMessage()], $code))->send();
        }
    }
}