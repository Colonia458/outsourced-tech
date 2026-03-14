<?php
// Admin Layout Main Template
// This is the main layout file that wraps all admin pages

// Get current page name for active state highlighting
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Default page title
$page_title = isset($page_title) ? $page_title : 'Admin Dashboard';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Outsourced Tech Admin Panel">
    
    <title><?php echo htmlspecialchars($page_title); ?> | Admin Panel</title>
    
    <!-- Google Fonts - Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6.4 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- Custom Admin CSS -->
    <link rel="stylesheet" href="<?php echo $base_url ?? ''; ?>/admin/assets/css/admin.css">
    
    <style>
        /* Additional inline styles if needed */
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <?php include_once __DIR__ . '/sidebar.php'; ?>
        
        <!-- Main Content Area -->
        <div class="admin-main" id="mainContent">
            <!-- Top Navbar -->
            <?php include_once __DIR__ . '/header.php'; ?>
            
            <!-- Page Content -->
            <main class="admin-content">
                <?php
                // Display flash messages if any
                if (isset($_SESSION['success'])) {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        ' . htmlspecialchars($_SESSION['success']) . '
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>';
                    unset($_SESSION['success']);
                }
                
                if (isset($_SESSION['error'])) {
                    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                        ' . htmlspecialchars($_SESSION['error']) . '
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>';
                    unset($_SESSION['error']);
                }
                ?>
                
                <!-- Page-specific content goes here -->
