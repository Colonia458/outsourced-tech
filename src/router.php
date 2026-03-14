<?php
// src/router.php - Simple routing system

class Router {
    private $routes = [];
    private $base_path;
    
    public function __construct($base_path = '') {
        $this->base_path = $base_path;
    }
    
    /**
     * Add a GET route
     */
    public function get($path, $handler) {
        $this->routes['GET'][$path] = $handler;
    }
    
    /**
     * Add a POST route
     */
    public function post($path, $handler) {
        $this->routes['POST'][$path] = $handler;
    }
    
    /**
     * Add routes for both GET and POST
     */
    public function any($path, $handler) {
        $this->get($path, $handler);
        $this->post($path, $handler);
    }
    
    /**
     * Dispatch the request to the appropriate handler
     */
    public function dispatch() {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Remove base path
        if ($this->base_path) {
            $uri = str_replace($this->base_path, '', $uri);
        }
        
        // Remove trailing slash (except for root)
        if ($uri !== '/' && str_ends_with($uri, '/')) {
            $uri = rtrim($uri, '/');
        }
        
        // Find matching route
        if (isset($this->routes[$method])) {
            foreach ($this->routes[$method] as $path => $handler) {
                $params = $this->match($path, $uri);
                if ($params !== false) {
                    return $this->execute($handler, $params);
                }
            }
        }
        
        // No route found - 404
        http_response_code(404);
        echo json_encode(['error' => 'Route not found', 'path' => $uri]);
        exit;
    }
    
    /**
     * Match a route pattern against URI
     */
    private function match($pattern, $uri) {
        // Convert route pattern to regex
        $regex = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';
        
        if (preg_match($regex, $uri, $matches)) {
            // Filter to named captures only
            return array_filter($matches, function($key) {
                return !is_numeric($key);
            }, ARRAY_FILTER_USE_KEY);
        }
        
        return false;
    }
    
    /**
     * Execute the handler
     */
    private function execute($handler, $params) {
        // If handler is a callable
        if (is_callable($handler)) {
            return call_user_func_array($handler, $params);
        }
        
        // If handler is a string "Controller@method"
        if (is_string($handler)) {
            [$controller, $method] = explode('@', $handler);
            
            // Load controller file
            $controller_file = __DIR__ . '/../controllers/' . $controller . '.php';
            
            if (file_exists($controller_file)) {
                require_once $controller_file;
                
                if (class_exists($controller)) {
                    $controller_instance = new $controller();
                    
                    if (method_exists($controller_instance, $method)) {
                        return call_user_func_array([$controller_instance, $method], $params);
                    }
                }
            }
        }
        
        // Handler not found
        http_response_code(500);
        echo json_encode(['error' => 'Handler not found']);
        exit;
    }
}

/**
 * Helper function to create router instance
 */
function create_router($base_path = '') {
    return new Router($base_path);
}
