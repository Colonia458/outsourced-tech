<?php
session_start();

if (!isset($_SESSION['admin_user'])) {
    header('Location: ../login.php');
    exit();
}

require_once '../../src/config.php';
require_once '../../src/database.php';

$id = (int)($_GET['id'] ?? 0);

if ($id === 0) {
    header('Location: list.php');
    exit();
}

// Delete product
query("DELETE FROM products WHERE id = ?", [$id]);

header('Location: list.php?msg=deleted');
exit();
