<?php
// src/ratelimit.php - API Rate Limiting Middleware

require_once __DIR__ . '/security.php';

/**
 * Rate limiter using Redis or file-based storage
 * For production, use Redis. For simple deployments, use file-based.
 */

class RateLimiter {
    private $max_requests;
    private $time_window;
    private $storage_path;
    
    /**
     * Create a new rate limiter
     * 
     * @param int $max_requests Maximum requests allowed in time window
     * @param int $time_window Time window in seconds
     */
    public function __construct($max_requests = 60, $time_window = 60) {
        $this->max_requests = $max_requests;
        $this->time_window = $time_window;
        $this->storage_path = __DIR__ . '/../logs/rate_limits';
        
        if (!is_dir($this->storage_path)) {
            mkdir($this->storage_path, 0755, true);
        }
    }
    
    /**
     * Check if request is allowed
     * 
     * @param string $identifier Unique identifier (IP, user ID, or API key)
     * @return array ['allowed' => bool, 'remaining' => int, 'reset_time' => int]
     */
    public function check($identifier) {
        $file_path = $this->storage_path . '/' . md5($identifier) . '.json';
        $now = time();
        
        // Load existing data
        $data = $this->load_data($file_path);
        
        // Reset if time window expired
        if ($data['window_start'] + $this->time_window < $now) {
            $data = [
                'window_start' => $now,
                'requests' => 0
            ];
        }
        
        // Check limit
        $remaining = $this->max_requests - $data['requests'];
        $reset_time = $data['window_start'] + $this->time_window;
        
        if ($data['requests'] >= $this->max_requests) {
            return [
                'allowed' => false,
                'remaining' => 0,
                'reset_time' => $reset_time,
                'retry_after' => $reset_time - $now
            ];
        }
        
        // Increment counter
        $data['requests']++;
        $this->save_data($file_path, $data);
        
        return [
            'allowed' => true,
            'remaining' => $remaining - 1,
            'reset_time' => $reset_time
        ];
    }
    
    /**
     * Get current usage without incrementing
     */
    public function get_usage($identifier) {
        $file_path = $this->storage_path . '/' . md5($identifier) . '.json';
        $data = $this->load_data($file_path);
        
        return [
            'requests' => $data['requests'],
            'limit' => $this->max_requests,
            'remaining' => max(0, $this->max_requests - $data['requests'])
        ];
    }
    
    /**
     * Reset limit for an identifier
     */
    public function reset($identifier) {
        $file_path = $this->storage_path . '/' . md5($identifier) . '.json';
        @unlink($file_path);
    }
    
    /**
     * Load data from file
     */
    private function load_data($file_path) {
        if (file_exists($file_path)) {
            $content = file_get_contents($file_path);
            $data = json_decode($content, true);
            
            if ($data && isset($data['window_start']) && isset($data['requests'])) {
                return $data;
            }
        }
        
        return [
            'window_start' => time(),
            'requests' => 0
        ];
    }
    
    /**
     * Save data to file
     */
    private function save_data($file_path, $data) {
        file_put_contents($file_path, json_encode($data), LOCK_EX);
    }
    
    /**
     * Clean up old rate limit files
     */
    public function cleanup() {
        $files = glob($this->storage_path . '/*.json');
        $now = time();
        
        foreach ($files as $file) {
            // Delete files older than 1 hour
            if (filemtime($file) < $now - 3600) {
                @unlink($file);
            }
        }
    }
}

/**
 * Apply rate limiting to API request
 * 
 * @param string $identifier Optional custom identifier
 * @param int $max_requests Max requests per window
 * @param int $time_window Window in seconds
 * @return bool True if allowed, sends 429 response if not
 */
function apply_rate_limit($identifier = null, $max_requests = 60, $time_window = 60) {
    // Get identifier from request
    if ($identifier === null) {
        $identifier = get_client_ip();
    }
    
    $limiter = new RateLimiter($max_requests, $time_window);
    $result = $limiter->check($identifier);
    
    // Set rate limit headers
    header('X-RateLimit-Limit: ' . $max_requests);
    header('X-RateLimit-Remaining: ' . $result['remaining']);
    header('X-RateLimit-Reset: ' . $result['reset_time']);
    
    if (!$result['allowed']) {
        http_response_code(429);
        header('Retry-After: ' . $result['retry_after']);
        
        echo json_encode([
            'success' => false,
            'error' => 'Rate limit exceeded',
            'retry_after' => $result['retry_after']
        ]);
        
        exit;
    }
    
    return true;
}

/**
 * Rate limit by IP address (public endpoints)
 */
function rate_limit_ip($max_requests = 30, $time_window = 60) {
    return apply_rate_limit(get_client_ip(), $max_requests, $time_window);
}

/**
 * Rate limit by user ID (authenticated endpoints)
 */
function rate_limit_user($user_id, $max_requests = 120, $time_window = 60) {
    return apply_rate_limit('user_' . $user_id, $max_requests, $time_window);
}

/**
 * Strict rate limit for auth endpoints
 */
function rate_limit_auth($identifier = null) {
    if ($identifier === null) {
        $identifier = get_client_ip();
    }
    
    return apply_rate_limit('auth_' . $identifier, 5, 300); // 5 attempts per 5 minutes
}

/**
 * Payment endpoint rate limit (strict)
 */
function rate_limit_payment() {
    return apply_rate_limit(get_client_ip(), 10, 3600); // 10 payment attempts per hour
}
