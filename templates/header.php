<?php
// templates/header.php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/loyalty.php';

// Get user tier if logged in
$header_user_tier = null;
if (isset($_SESSION['user_id'])) {
    $header_user_tier = get_user_tier($_SESSION['user_id']);
}

$tier_badges = [
    'Bronze' => ['icon' => '🥉', 'color' => '#cd7f32'],
    'Silver' => ['icon' => '🥈', 'color' => '#c0c0c0'],
    'Gold' => ['icon' => '🥇', 'color' => '#ffd700'],
    'Platinum' => ['icon' => '💎', 'color' => '#e5e4e2'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> <?= isset($page_title) ? "– " . $page_title : "" ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/main.css">
</head>
<body>

<?php require_once __DIR__ . '/navbar.php'; ?>

<main class="container my-4">
