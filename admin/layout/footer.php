<?php
// Admin Layout Footer
// This file closes the main content wrapper and includes necessary scripts
?>
                <!-- End of page-specific content -->
            </main>
            <!-- End admin-content -->
        </div>
        <!-- End admin-main -->
    </div>
    <!-- End admin-wrapper -->

    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom Admin JS -->
    <script src="<?php echo $base_url ?? ''; ?>/admin/assets/js/admin.js"></script>
    
    <!-- Page-specific scripts can be added here -->
    <?php if (isset($page_scripts)): ?>
        <?php echo $page_scripts; ?>
    <?php endif; ?>
</body>
</html>
