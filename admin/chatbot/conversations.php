<?php
session_start();

if (!isset($_SESSION['admin_user'])) {
    header('Location: ../login.php');
    exit();
}

require_once '../../src/config.php';
require_once '../../src/database.php';

$page_title = 'Chatbot Conversations';

// Get conversations
$where = "1=1";
$params = [];

if (!empty($_GET['search'])) {
    $where .= " AND (c.user_message LIKE ? OR c.bot_response LIKE ? OR u.email LIKE ? OR u.full_name LIKE ?)";
    $search = '%' . $_GET['search'] . '%';
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

$sql = "SELECT c.*, u.full_name, u.email 
        FROM chatbot_conversations c
        LEFT JOIN users u ON c.user_id = u.id
        WHERE $where
        ORDER BY c.created_at DESC
        LIMIT 100";

$conversations = fetchAll($sql, $params);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f5f6fa; }
        .sidebar { position: fixed; width: 250px; height: 100vh; background: #212529; padding: 20px; overflow-y: auto; }
        .sidebar a { color: #adb5bd; text-decoration: none; padding: 12px 15px; display: block; border-radius: 5px; margin-bottom: 5px; }
        .sidebar a:hover, .sidebar a.active { background: #343a40; color: white; }
        .main-content { margin-left: 250px; padding: 20px; }
        .chat-message { padding: 10px; margin: 5px 0; border-radius: 8px; }
        .user-msg { background: #d1e7dd; margin-left: 20%; }
        .bot-msg { background: #e2e3e5; margin-right: 20%; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h4 class="text-white mb-4"><i class="fas fa-microchip"></i> <?= APP_NAME ?></h4>
        <a href="../dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="../products/list.php"><i class="fas fa-box"></i> Products</a>
        <a href="../services/list.php"><i class="fas fa-tools"></i> Services</a>
        <a href="../orders/list.php"><i class="fas fa-shopping-cart"></i> Orders</a>
        <a href="../users/list.php"><i class="fas fa-users"></i> Users</a>
        <a href="../service-bookings/list.php"><i class="fas fa-calendar"></i> Bookings</a>
        <a href="conversations.php" class="active"><i class="fas fa-comments"></i> Chatbot</a>
        <a href="../coupons/manage.php"><i class="fas fa-ticket"></i> Coupons</a>
        <a href="../reviews/manage.php"><i class="fas fa-star"></i> Reviews</a>
        <a href="../delivery-zones/manage.php"><i class="fas fa-truck"></i> Delivery</a>
        <a href="../loyalty-tiers/manage.php"><i class="fas fa-award"></i> Loyalty</a>
        <a href="../system-status.php"><i class="fas fa-server"></i> System</a>
        <a href="../logs.php"><i class="fas fa-file-lines"></i> Logs</a>
        <a href="../delivery-map.php"><i class="fas fa-map"></i> Map</a>
        <a href="../logout.php" style="margin-top: 20px;"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="main-content">
        <h2 class="mb-4">Chatbot Conversations</h2>

        <!-- Search -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-10">
                        <input type="text" name="search" class="form-control" placeholder="Search messages, user email, name..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-secondary w-100">Search</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Conversations -->
        <?php if (empty($conversations)): ?>
            <div class="alert alert-info">No conversations found</div>
        <?php else: ?>
            <?php foreach ($conversations as $c): ?>
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>
                            <?php if ($c['user_id']): ?>
                                <i class="fas fa-user"></i> <?= htmlspecialchars($c['full_name'] ?? $c['email']) ?>
                            <?php else: ?>
                                <i class="fas fa-user"></i> Guest (Session: <?= htmlspecialchars(substr($c['session_id'] ?? '', 0, 10)) ?>)
                            <?php endif; ?>
                        </span>
                        <small class="text-muted"><?= date('M d, Y h:i A', strtotime($c['created_at'])) ?></small>
                    </div>
                    <div class="card-body">
                        <div class="chat-message user-msg">
                            <strong><i class="fas fa-user"></i> User:</strong><br>
                            <?= htmlspecialchars($c['user_message']) ?>
                        </div>
                        <div class="chat-message bot-msg">
                            <strong><i class="fas fa-robot"></i> Bot:</strong><br>
                            <?= nl2br(htmlspecialchars($c['bot_response'])) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="mt-3 text-muted">
            Showing <?= count($conversations) ?> recent conversations
        </div>
    </div>
</body>
</html>
