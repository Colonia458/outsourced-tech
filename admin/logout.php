<?php
// admin/logout.php

// Clear all admin session data
session_start();
unset($_SESSION['admin_id']);
unset($_SESSION['admin_user']);
unset($_SESSION['admin_role']);
unset($_SESSION['admin_name']);

// Destroy session completely
session_destroy();

// Redirect to login
header("Location: login.php");
exit();
