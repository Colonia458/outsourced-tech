<?php
// Enable full error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Try loading dotenv directly with try-catch
$dotenv_loaded = false;
$dotenv_error = '';

try {
    // Check if vendor autoload exists
    $vendor_autoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($vendor_autoload)) {
        require_once $vendor_autoload;
        
        // Try to load .env using vlucas/phpdotenv
        $dotenv_path = __DIR__ . '/..';
        if (file_exists($dotenv_path . '/.env')) {
            $dotenv = \Dotenv\Dotenv::createImmutable($dotenv_path);
            $dotenv->load();
            $dotenv_loaded = true;
        } else {
            $dotenv_error = '.env file not found at: ' . $dotenv_path;
        }
    } else {
        $dotenv_error = 'vendor/autoload.php not found';
    }
} catch (Exception $e) {
    $dotenv_error = $e->getMessage();
} catch (Error $e) {
    $dotenv_error = 'Error: ' . $e->getMessage();
}

// Also try using $_ENV superglobal as backup (set by PHP when variables_order includes E)
// Or manually parse .env file
$env_backup = [];
$env_file = __DIR__ . '/../.env';
if (file_exists($env_file)) {
    $env_lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($env_lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // Skip comments
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Remove quotes if present
            if (preg_match('/^["\'](.*)["\']\s*$/', $value, $matches)) {
                $value = $matches[1];
            }
            $_ENV[$key] = $value;
            $env_backup[$key] = $value;
        }
    }
}

require_once __DIR__ . '/../src/config.php';
// public/test-forgot.php - Simplified forgot password test page
// This is a debug version with no redirects, no session complexity - just processes and shows result

$page_title = 'Test Forgot Password';

// Debug: Show APP_ENV value
$app_env = getenv('APP_ENV');
$app_env_display = $app_env !== false ? $app_env : '(not set)';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f5f5f5; padding-top: 50px; }
        .test-container { max-width: 600px; margin: 0 auto; }
        .debug-panel { background: #1e1e1e; color: #00ff00; padding: 15px; border-radius: 5px; font-family: monospace; margin-top: 20px; }
        .debug-panel h6 { color: #00ff00; border-bottom: 1px solid #333; padding-bottom: 10px; margin-bottom: 10px; }
        .debug-line { margin: 5px 0; }
        .debug-label { color: #ff00ff; }
        .debug-value { color: #00ffff; }
    </style>
</head>
<body>
    <div class="container test-container">
        <!-- APP_ENV Debug Display -->
        <div class="debug-panel">
            <h6>🔍 APP_ENV Debug</h6>
            <div class="debug-line">
                <span class="debug-label">getenv('APP_ENV'):</span>
                <span class="debug-value"><?= htmlspecialchars($app_env_display) ?></span>
            </div>
        </div>
        
        <!-- Dotenv Loading Debug -->
        <div class="debug-panel">
            <h6>📦 Dotenv Loading Debug</h6>
            <div class="debug-line">
                <span class="debug-label">Dotenv Loaded:</span>
                <span class="debug-value"><?= $dotenv_loaded ? '✅ YES' : '❌ NO' ?></span>
            </div>
            <?php if (!empty($dotenv_error)): ?>
            <div class="debug-line">
                <span class="debug-label">Error:</span>
                <span class="debug-value" style="color: red"><?= htmlspecialchars($dotenv_error) ?></span>
            </div>
            <?php endif; ?>
            <div class="debug-line">
                <span class="debug-label">$_ENV backup loaded:</span>
                <span class="debug-value"><?= !empty($env_backup) ? '✅ YES (' . count($env_backup) . ' vars)' : '❌ NO' ?></span>
            </div>
            <?php if (!empty($env_backup)): ?>
            <div class="debug-line">
                <span class="debug-label">Sample vars:</span>
                <span class="debug-value"><?= htmlspecialchars(implode(', ', array_keys(array_slice($env_backup, 0, 5)))) ?></span>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="card shadow">
            <div class="card-body p-5">
                <div class="text-center mb-4">
                    <h4 class="card-title">🧪 Test Forgot Password</h4>
                    <p class="text-muted">Simple test version - no redirects, no session complexity</p>
                </div>
                
                <?php
                // Check if form was submitted
                $form_submitted = $_SERVER['REQUEST_METHOD'] === 'POST';
                $email_or_phone = $_POST['email_or_phone'] ?? '';
                ?>
                
                <?php if ($form_submitted): ?>
                    <div class="alert alert-info">
                        <strong>✅ Form was submitted!</strong> Processing now...
                    </div>
                    
                    <div class="debug-panel">
                        <h6>📋 Form Submission Debug</h6>
                        <div class="debug-line">
                            <span class="debug-label">REQUEST_METHOD:</span>
                            <span class="debug-value"><?= $_SERVER['REQUEST_METHOD'] ?></span>
                        </div>
                        <div class="debug-line">
                            <span class="debug-label">Email/Phone Submitted:</span>
                            <span class="debug-value">"<?= htmlspecialchars($email_or_phone) ?>"</span>
                        </div>
                        <div class="debug-line">
                            <span class="debug-label">Empty Check:</span>
                            <span class="debug-value"><?= empty($email_or_phone) ? 'EMPTY' : 'NOT EMPTY' ?></span>
                        </div>
                    </div>
                    
                    <?php if (!empty($email_or_phone)): ?>
                        <?php
                        // Try to process the password reset
                        try {
                            require_once __DIR__ . '/../src/config.php';
                            
                            echo '<div class="debug-panel">';
                            echo '<h6>⚙️ Processing Password Reset</h6>';
                            
                            // Check if password.php exists and has the function
                            $password_file = __DIR__ . '/../src/password.php';
                            if (file_exists($password_file)) {
                                echo '<div class="debug-line"><span class="debug-label">password.php:</span> <span class="debug-value">EXISTS</span></div>';
                                
                                require_once $password_file;
                                
                                if (function_exists('request_password_reset_otp')) {
                                    echo '<div class="debug-line"><span class="debug-label">Function:</span> <span class="debug-value">request_password_reset_otp EXISTS</span></div>';
                                    
                                    // Call the function
                                    $result = request_password_reset_otp($email_or_phone);
                                    
                                    echo '<div class="debug-line"><span class="debug-label">Result:</span> <span class="debug-value">' . print_r($result, true) . '</span></div>';
                                    
                                    if ($result['success']) {
                                        echo '<div class="alert alert-success mt-3">';
                                        echo '<strong>✅ SUCCESS:</strong> ' . htmlspecialchars($result['message']);
                                        if (isset($result['debug_otp'])) {
                                            echo '<br><strong>OTP:</strong> ' . htmlspecialchars($result['debug_otp']);
                                        }
                                        echo '</div>';
                                    } else {
                                        echo '<div class="alert alert-danger mt-3">';
                                        echo '<strong>❌ FAILED:</strong> ' . htmlspecialchars($result['message']);
                                        echo '</div>';
                                    }
                                } else {
                                    echo '<div class="debug-line"><span class="debug-label">Function:</span> <span class="debug-value" style="color:red">request_password_reset_otp NOT FOUND</span></div>';
                                }
                            } else {
                                echo '<div class="debug-line"><span class="debug-label">password.php:</span> <span class="debug-value" style="color:red">FILE NOT FOUND</span></div>';
                            }
                            
                            echo '</div>';
                        } catch (Exception $e) {
                            echo '<div class="alert alert-danger mt-3">';
                            echo '<strong>Exception:</strong> ' . htmlspecialchars($e->getMessage());
                            echo '</div>';
                        }
                        ?>
                    <?php else: ?>
                        <div class="alert alert-warning mt-3">
                            <strong>⚠️ No email/phone entered!</strong> Please enter an email address.
                        </div>
                    <?php endif; ?>
                    
                    <hr>
                    <div class="text-center">
                        <a href="test-forgot.php" class="btn btn-secondary">Reset Test</a>
                    </div>
                <?php else: ?>
                    <div class="alert alert-primary">
                        <strong>📝 Form not submitted yet.</strong> Enter an email to test.
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" class="mt-4">
                    <div class="mb-3">
                        <label for="email_or_phone" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email_or_phone" name="email_or_phone" 
                               placeholder="Enter your email address" 
                               value="<?= htmlspecialchars($email_or_phone) ?>">
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">Test Submit</button>
                    </div>
                </form>
                
                <div class="text-center mt-4">
                    <a href="forgot-password.php">← Back to real forgot password</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
