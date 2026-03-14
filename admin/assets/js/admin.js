/**
 * Admin Panel JavaScript
 * Handles all client-side functionality for the admin dashboard
 */

(function() {
    'use strict';
    
    // Initialize on DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        initializeComponents();
    });
    
    /**
     * Initialize all components
     */
    function initializeComponents() {
        initSidebar();
        initDropdowns();
        initDataTables();
        initCharts();
        initTooltips();
        initConfirmDialogs();
    }
    
    /**
     * Sidebar functionality
     */
    function initSidebar() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const navbar = document.getElementById('navbar');
        const toggleBtn = document.getElementById('sidebarToggle');
        
        if (!sidebar || !toggleBtn) return;
        
        // Check for saved state
        const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (sidebarCollapsed) {
            sidebar.classList.add('collapsed');
            if (mainContent) mainContent.classList.add('sidebar-collapsed');
            if (navbar) navbar.classList.add('sidebar-collapsed');
            updateToggleIcon(toggleBtn, true);
        }
        
        // Toggle button click handler
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            const isCollapsed = sidebar.classList.contains('collapsed');
            
            if (mainContent) {
                mainContent.classList.toggle('sidebar-collapsed', isCollapsed);
            }
            
            if (navbar) {
                navbar.classList.toggle('sidebar-collapsed', isCollapsed);
            }
            
            updateToggleIcon(toggleBtn, isCollapsed);
            
            // Save state
            localStorage.setItem('sidebarCollapsed', isCollapsed);
        });
    }
    
    /**
     * Update toggle button icon
     */
    function updateToggleIcon(btn, isCollapsed) {
        const icon = btn.querySelector('i');
        if (icon) {
            if (isCollapsed) {
                icon.classList.remove('fa-chevron-left');
                icon.classList.add('fa-chevron-right');
            } else {
                icon.classList.remove('fa-chevron-right');
                icon.classList.add('fa-chevron-left');
            }
        }
    }
    
    /**
     * Dropdown functionality
     */
    function initDropdowns() {
        const dropdowns = document.querySelectorAll('.navbar-dropdown');
        
        dropdowns.forEach(dropdown => {
            const btn = dropdown.querySelector('button');
            const menu = dropdown.querySelector('.dropdown-menu');
            
            if (btn && menu) {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    
                    // Close other dropdowns
                    dropdowns.forEach(d => {
                        if (d !== dropdown) {
                            const m = d.querySelector('.dropdown-menu');
                            if (m) m.classList.remove('show');
                        }
                    });
                    
                    menu.classList.toggle('show');
                });
            }
        });
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            dropdowns.forEach(dropdown => {
                const menu = dropdown.querySelector('.dropdown-menu');
                if (menu && !dropdown.contains(e.target)) {
                    menu.classList.remove('show');
                }
            });
        });
    }
    
    /**
     * Data Tables functionality
     */
    function initDataTables() {
        // Check if data tables exist
        const tables = document.querySelectorAll('.data-table');
        if (tables.length === 0) return;
        
        // Add sorting functionality
        tables.forEach(table => {
            const headers = table.querySelectorAll('th.sortable');
            headers.forEach(header => {
                header.addEventListener('click', function() {
                    const column = this.cellIndex;
                    const isNumeric = this.dataset.type === 'numeric';
                    sortTable(table, column, isNumeric);
                });
            });
        });
        
        // Search functionality
        const searchInputs = document.querySelectorAll('.table-search input');
        searchInputs.forEach(input => {
            input.addEventListener('input', function() {
                const query = this.value.toLowerCase();
                const table = this.closest('.data-table-wrapper').querySelector('.data-table');
                filterTable(table, query);
            });
        });
    }
    
    /**
     * Sort table by column
     */
    function sortTable(table, column, isNumeric) {
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        
        const direction = table.dataset.sortDirection === 'asc' ? 'desc' : 'asc';
        table.dataset.sortDirection = direction;
        
        rows.sort((a, b) => {
            const aVal = a.cells[column].textContent.trim();
            const bVal = b.cells[column].textContent.trim();
            
            if (isNumeric) {
                return direction === 'asc' 
                    ? parseFloat(aVal) - parseFloat(bVal)
                    : parseFloat(bVal) - parseFloat(aVal);
            }
            
            return direction === 'asc'
                ? aVal.localeCompare(bVal)
                : bVal.localeCompare(aVal);
        });
        
        rows.forEach(row => tbody.appendChild(row));
    }
    
    /**
     * Filter table rows
     */
    function filterTable(table, query) {
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(query) ? '' : 'none';
        });
    }
    
    /**
     * Initialize charts (placeholder for chart.js integration)
     */
    function initCharts() {
        // This is a placeholder for chart initialization
        // Charts would typically be initialized with Chart.js or similar
        
        // Example sparkline bars
        const sparklines = document.querySelectorAll('.kpi-sparkline');
        sparklines.forEach(sparkline => {
            const bars = sparkline.querySelectorAll('.sparkline-bar');
            bars.forEach(bar => {
                // Random heights for demo purposes
                const height = Math.floor(Math.random() * 80) + 20;
                bar.style.height = height + '%';
            });
        });
    }
    
    /**
     * Initialize tooltips
     */
    function initTooltips() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
    
    /**
     * Confirmation dialogs
     */
    function initConfirmDialogs() {
        const confirmButtons = document.querySelectorAll('[data-confirm]');
        confirmButtons.forEach(btn => {
            btn.addEventListener('click', function(e) {
                const message = this.dataset.confirm || 'Are you sure?';
                if (!confirm(message)) {
                    e.preventDefault();
                    return false;
                }
            });
        });
    }
    
    /**
     * Utility: Show notification
     */
    window.adminNotify = function(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible fade show`;
        notification.setAttribute('role', 'alert');
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        const container = document.querySelector('.admin-content');
        if (container) {
            container.insertBefore(notification, container.firstChild);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                notification.remove();
            }, 5000);
        }
    };
    
    /**
     * Utility: Toggle theme
     */
    window.toggleTheme = function() {
        const html = document.documentElement;
        const currentTheme = html.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        
        html.setAttribute('data-theme', newTheme);
        localStorage.setItem('adminTheme', newTheme);
    };
    
    /**
     * Utility: Format currency
     */
    window.formatCurrency = function(amount, currency = 'KES') {
        return new Intl.NumberFormat('en-KE', {
            style: 'currency',
            currency: currency
        }).format(amount);
    };
    
    /**
     * Utility: Format date
     */
    window.formatDate = function(date, format = 'short') {
        const d = new Date(date);
        
        if (format === 'short') {
            return d.toLocaleDateString('en-KE', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }
        
        if (format === 'long') {
            return d.toLocaleDateString('en-KE', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
        return d.toISOString().split('T')[0];
    };
    
})();
