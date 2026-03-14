<?php
// public/register.php

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/auth.php';

$page_title = 'Register';

if (is_logged_in()) {
    header("Location: profile.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $name     = trim($_POST['name'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');

    if (empty($username) || empty($email) || empty($password)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        // Check if email or username already exists
        $existing = fetchOne(
            "SELECT id FROM users WHERE email = ? OR username = ?",
            [$email, $username]
        );

        if ($existing) {
            $error = 'Email or username already taken.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            // Insert user – using table + data array style (most common)
            $id = db_insert('users', [
                'username'      => $username,
                'email'         => $email,
                'password_hash' => $hash,
                'full_name'     => $name,
                'phone'         => $phone ?: null,   // allow empty phone
                // 'created_at' => date('Y-m-d H:i:s'),  // only if column exists
            ]);

            if ($id !== false && $id > 0) {
                $_SESSION['user_id'] = $id;
                header("Location: profile.php?welcome=1");
                exit;
            } else {
                $error = 'Registration failed. Please try again later.';
            }
        }
    }
}
?>

<?php require_once __DIR__ . '/../templates/header.php'; ?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body p-5">
                <h2 class="text-center mb-4">Create Account</h2>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="post">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone (07xx xxx xxx)</label>
                        <input type="tel" name="phone" class="form-control" pattern="07[0-9]{8}">
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required minlength="6">
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2">Register</button>
                </form>

                <p class="text-center mt-4">
                    Already have an account? <a href="login.php">Login here</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>