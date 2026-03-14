// assets/js/main.js
document.addEventListener('DOMContentLoaded', function () {
    // Update cart count in navbar
    updateCartCount();

    // Add to cart buttons (products)
    document.querySelectorAll('.add-to-cart').forEach(btn => {
        btn.addEventListener('click', function () {
            const productId = this.dataset.id;
            addToCart(productId);
        });
    });

    // Book service buttons
    document.querySelectorAll('.book-service').forEach(btn => {
        btn.addEventListener('click', function () {
            const serviceId = this.dataset.id;
            const serviceName = this.dataset.name;
            const servicePrice = this.dataset.price;
            bookService(serviceId, serviceName, servicePrice);
        });
    });
});

function updateCartCount() {
    fetch('../api/v1/cart.php?action=count', {
        method: 'GET',
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        const countEl = document.getElementById('cart-count');
        if (countEl) {
            countEl.textContent = `(${data.count || 0})`;
        }
    })
    .catch(err => console.error('Cart count failed:', err));
}

function addToCart(productId, quantity = 1) {
    fetch('../api/v1/cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ product_id: productId, quantity: quantity, action: 'add' }),
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Added to cart!');
            updateCartCount();
        } else {
            alert(data.message || 'Failed to add to cart');
        }
    })
    .catch(err => {
        console.error(err);
        alert('Error adding to cart');
    });
}

function bookService(serviceId, serviceName, servicePrice) {
    // For services, we redirect to a booking page or show a booking modal
    // For now, let's add it to cart as a service booking
    const date = prompt('Enter booking date (YYYY-MM-DD):');
    if (!date) return;
    
    const time = prompt('Enter preferred time (HH:MM):') || '10:00';
    const phone = prompt('Enter your phone number:');
    
    if (!phone) {
        alert('Phone number is required for booking');
        return;
    }
    
    // Submit booking via API
    fetch('../api/v1/service-bookings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            service_id: serviceId, 
            booking_date: date,
            booking_time: time,
            phone: phone,
            action: 'create'
        }),
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Service booked successfully! We will contact you to confirm.');
        } else {
            alert(data.message || 'Failed to book service');
        }
    })
    .catch(err => {
        console.error(err);
        alert('Error booking service');
    });
}

// Toast notification helper
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type} border-0 position-fixed bottom-0 end-0 m-3`;
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>`;
    document.body.appendChild(toast);
    new bootstrap.Toast(toast).show();
    setTimeout(() => toast.remove(), 5000);
}

// Search autocomplete
let searchTimeout = null;
const searchInput = document.getElementById('search-input');
const searchResults = document.getElementById('search-results');
const apiUrl = typeof API_URL !== 'undefined' ? API_URL : '../api/v1/';

if (searchInput && searchResults) {
    searchInput.addEventListener('input', function() {
        const query = this.value.trim();
        
        if (query.length < 2) {
            searchResults.style.display = 'none';
            return;
        }
        
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            fetch(`${apiUrl}search.php?q=${encodeURIComponent(query)}`, {
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(products => {
                if (products.length === 0) {
                    searchResults.innerHTML = '<div class="p-3 text-muted">No products found</div>';
                } else {
                    searchResults.innerHTML = products.map(p => `
                        <a href="product.php?id=${p.id}" class="d-flex align-items-center p-2 text-decoration-none text-dark border-bottom">
                            ${p.image_url ? `<img src="${p.image_url.startsWith('http') ? p.image_url : '../' + p.image_url}" alt="${p.name}" style="width: 40px; height: 40px; object-fit: cover;" class="me-2 rounded">` : ''}
                            <div>
                                <div class="fw-medium">${p.name}</div>
                                <small class="text-success">KSh ${parseFloat(p.price).toLocaleString()}</small>
                            </div>
                        </a>
                    `).join('');
                }
                searchResults.style.display = 'block';
            })
            .catch(err => console.error('Search failed:', err));
        }, 300);
    });
    
    // Hide results when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.style.display = 'none';
        }
    });
    
    // Handle click on search results
    searchResults.addEventListener('click', function(e) {
        const link = e.target.closest('a');
        if (link) {
            e.preventDefault();
            window.location.href = link.href;
        }
    });
    
    // Show results on focus if there's a query
    searchInput.addEventListener('focus', function() {
        if (this.value.trim().length >= 2) {
            searchResults.style.display = 'block';
        }
    });
}
