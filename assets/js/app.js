const cart = [];
let currentUser = null;
let categories = [];
let products = [];
let services = [];
let loyaltyTiers = [];
let deliveryZones = [];

document.addEventListener('DOMContentLoaded', function() {
    initApp();
    setupEventListeners();
    checkAuth();
});

function initApp() {
    loadCategories();
    loadProducts();
    loadServices();
    loadLoyaltyTiers();
    loadDeliveryZones();
    loadCartFromStorage();
}

function setupEventListeners() {
    document.getElementById('btnLogin').addEventListener('click', () => openAuthModal('login'));
    document.getElementById('btnSignup').addEventListener('click', () => openAuthModal('signup'));
    document.getElementById('btnLogout').addEventListener('click', logout);
    document.getElementById('cartBtn').addEventListener('click', toggleCart);
    document.getElementById('cartClose').addEventListener('click', toggleCart);
    document.getElementById('authModalClose').addEventListener('click', closeAuthModal);
    document.getElementById('checkoutModalClose').addEventListener('click', closeCheckoutModal);
    document.getElementById('serviceModalClose').addEventListener('click', closeServiceModal);
    document.getElementById('btnCheckout').addEventListener('click', openCheckout);
    document.getElementById('btnPlaceOrder').addEventListener('click', placeOrder);
    document.getElementById('chatbotToggle').addEventListener('click', toggleChatbot);
    document.getElementById('chatbotMinimize').addEventListener('click', toggleChatbot);
    document.getElementById('chatbotSend').addEventListener('click', sendChatMessage);
    document.getElementById('chatbotInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') sendChatMessage();
    });

    document.querySelectorAll('.auth-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            switchAuthTab(this.dataset.tab);
        });
    });

    document.getElementById('loginFormElement').addEventListener('submit', handleLogin);
    document.getElementById('signupFormElement').addEventListener('submit', handleSignup);

    document.querySelectorAll('input[name="deliveryType"]').forEach(radio => {
        radio.addEventListener('change', updateDeliveryOptions);
    });

    document.getElementById('deliveryZone').addEventListener('change', updateDeliveryFee);

    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const target = this.getAttribute('href').substring(1);
            scrollToSection(target);
        });
    });
}

function scrollToSection(sectionId) {
    const section = document.getElementById(sectionId);
    if (section) {
        section.scrollIntoView({ behavior: 'smooth' });
    }
}

async function loadCategories() {
    try {
        const response = await fetch(`${CONFIG.API_BASE_URL}/categories.php`);
        categories = await response.json();
        displayCategories();
    } catch (error) {
        console.error('Error loading categories:', error);
    }
}

function displayCategories() {
    const grid = document.getElementById('categoriesGrid');
    grid.innerHTML = categories.map(category => `
        <div class="category-card" onclick="filterByCategory('${category.id}')">
            ${category.image_url ?
                `<img src="${category.image_url}" alt="${category.name}">` :
                `<div class="category-icon"><i class="fas fa-${getCategoryIcon(category.name)}"></i></div>`
            }
            <h3>${category.name}</h3>
            <p>${category.description || ''}</p>
        </div>
    `).join('');
}

function getCategoryIcon(name) {
    const icons = {
        'Networking': 'network-wired',
        'Computers': 'desktop',
        'Phones': 'mobile-alt',
        'Repairs': 'tools',
        'ISP Services': 'wifi',
        'Diagnostics': 'stethoscope'
    };
    return icons[name] || 'box';
}

async function loadProducts() {
    try {
        const response = await fetch(`${CONFIG.API_BASE_URL}/products.php`);
        products = await response.json();
        displayProducts();
        setupProductFilters();
    } catch (error) {
        console.error('Error loading products:', error);
    }
}

function displayProducts(filter = 'all') {
    const grid = document.getElementById('productsGrid');
    let filteredProducts = products;

    if (filter !== 'all') {
        filteredProducts = products.filter(p => p.category_id === filter);
    }

    grid.innerHTML = filteredProducts.map(product => `
        <div class="product-card">
            ${product.image_url ?
                `<img src="${product.image_url}" alt="${product.name}">` :
                `<div class="category-icon"><i class="fas fa-box"></i></div>`
            }
            <h3>${product.name}</h3>
            <p>${product.description || ''}</p>
            <div class="product-price">
                <span class="price">KES ${parseFloat(product.price).toLocaleString()}</span>
                <span class="stock-badge ${getStockClass(product.stock_quantity)}">
                    ${getStockLabel(product.stock_quantity)}
                </span>
            </div>
            <button class="btn-add-cart" onclick="addToCart('${product.id}', 'product')"
                ${product.stock_quantity === 0 ? 'disabled' : ''}>
                ${product.stock_quantity === 0 ? 'Out of Stock' : 'Add to Cart'}
            </button>
        </div>
    `).join('');
}

function setupProductFilters() {
    const filtersContainer = document.getElementById('productFilters');
    const productCategories = [...new Set(products.map(p => p.category_id))];

    const categoryButtons = productCategories.map(catId => {
        const category = categories.find(c => c.id === catId);
        return category ? `<button class="filter-tab" data-filter="${catId}">${category.name}</button>` : '';
    }).join('');

    filtersContainer.innerHTML = `
        <button class="filter-tab active" data-filter="all">All Products</button>
        ${categoryButtons}
    `;

    document.querySelectorAll('.filter-tab').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.filter-tab').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            displayProducts(this.dataset.filter);
        });
    });
}

function getStockClass(quantity) {
    if (quantity === 0) return 'out-of-stock';
    if (quantity < 5) return 'low-stock';
    return 'in-stock';
}

function getStockLabel(quantity) {
    if (quantity === 0) return 'Out of Stock';
    if (quantity < 5) return 'Low Stock';
    return 'In Stock';
}

async function loadServices() {
    try {
        const response = await fetch(`${CONFIG.API_BASE_URL}/services.php`);
        services = await response.json();
        displayServices();
    } catch (error) {
        console.error('Error loading services:', error);
    }
}

function displayServices() {
    const grid = document.getElementById('servicesGrid');
    grid.innerHTML = services.map(service => `
        <div class="service-card">
            ${service.image_url ?
                `<img src="${service.image_url}" alt="${service.name}">` :
                `<div class="service-icon"><i class="fas fa-tools"></i></div>`
            }
            <h3>${service.name}</h3>
            <p>${service.description || ''}</p>
            <div class="product-price">
                <span class="price">KES ${parseFloat(service.price).toLocaleString()}</span>
            </div>
            ${service.duration_minutes ?
                `<p><i class="fas fa-clock"></i> ${service.duration_minutes} minutes</p>` :
                ''
            }
            <button class="btn-book-service" onclick="openServiceBooking('${service.id}')">
                Book Service
            </button>
        </div>
    `).join('');
}

async function loadLoyaltyTiers() {
    try {
        const response = await fetch(`${CONFIG.API_BASE_URL}/loyalty.php`);
        loyaltyTiers = await response.json();
        displayLoyaltyTiers();
    } catch (error) {
        console.error('Error loading loyalty tiers:', error);
    }
}

function displayLoyaltyTiers() {
    const container = document.getElementById('loyaltyTiers');
    container.innerHTML = loyaltyTiers.map(tier => `
        <div class="loyalty-tier">
            <div class="tier-icon" style="background: ${tier.color};">
                <i class="fas fa-award"></i>
            </div>
            <div class="tier-name">${tier.badge_name}</div>
            <div class="tier-points">${tier.points_required}+ points</div>
            <div class="tier-discount">${tier.discount_percentage}% discount</div>
        </div>
    `).join('');
}

async function loadDeliveryZones() {
    try {
        const response = await fetch(`${CONFIG.API_BASE_URL}/delivery-zones.php`);
        deliveryZones = await response.json();
    } catch (error) {
        console.error('Error loading delivery zones:', error);
    }
}

function addToCart(itemId, type) {
    const item = type === 'product'
        ? products.find(p => p.id === itemId)
        : services.find(s => s.id === itemId);

    if (!item) return;

    const existingItem = cart.find(i => i.id === itemId && i.type === type);

    if (existingItem) {
        existingItem.quantity += 1;
    } else {
        cart.push({
            id: itemId,
            type: type,
            name: item.name,
            price: parseFloat(item.price),
            quantity: 1,
            image_url: item.image_url
        });
    }

    updateCart();
    saveCartToStorage();
    showNotification('Item added to cart!');
}

function updateCart() {
    const cartItemsContainer = document.getElementById('cartItems');
    const cartCount = document.getElementById('cartCount');
    const cartSubtotal = document.getElementById('cartSubtotal');

    cartCount.textContent = cart.reduce((sum, item) => sum + item.quantity, 0);

    if (cart.length === 0) {
        cartItemsContainer.innerHTML = '<p style="text-align: center; color: var(--text-secondary);">Your cart is empty</p>';
        cartSubtotal.textContent = 'KES 0';
        return;
    }

    cartItemsContainer.innerHTML = cart.map((item, index) => `
        <div class="cart-item">
            ${item.image_url ?
                `<img src="${item.image_url}" alt="${item.name}" class="cart-item-image">` :
                '<div class="cart-item-image" style="background: var(--dark-bg); display: flex; align-items: center; justify-content: center;"><i class="fas fa-box"></i></div>'
            }
            <div class="cart-item-details">
                <div class="cart-item-name">${item.name}</div>
                <div class="cart-item-price">KES ${item.price.toLocaleString()}</div>
                <div class="cart-item-quantity">
                    <button class="qty-btn" onclick="updateCartQuantity(${index}, -1)">-</button>
                    <span>${item.quantity}</span>
                    <button class="qty-btn" onclick="updateCartQuantity(${index}, 1)">+</button>
                    <button class="cart-item-remove" onclick="removeFromCart(${index})">Remove</button>
                </div>
            </div>
        </div>
    `).join('');

    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    cartSubtotal.textContent = `KES ${subtotal.toLocaleString()}`;
}

function updateCartQuantity(index, change) {
    cart[index].quantity += change;
    if (cart[index].quantity <= 0) {
        cart.splice(index, 1);
    }
    updateCart();
    saveCartToStorage();
}

function removeFromCart(index) {
    cart.splice(index, 1);
    updateCart();
    saveCartToStorage();
}

function toggleCart() {
    document.getElementById('cartSidebar').classList.toggle('active');
}

function saveCartToStorage() {
    localStorage.setItem('cart', JSON.stringify(cart));
}

function loadCartFromStorage() {
    const savedCart = localStorage.getItem('cart');
    if (savedCart) {
        cart.push(...JSON.parse(savedCart));
        updateCart();
    }
}

function openAuthModal(tab) {
    document.getElementById('authModal').classList.add('active');
    switchAuthTab(tab);
}

function closeAuthModal() {
    document.getElementById('authModal').classList.remove('active');
}

function switchAuthTab(tab) {
    document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
    document.querySelector(`[data-tab="${tab}"]`).classList.add('active');

    document.getElementById('loginForm').style.display = tab === 'login' ? 'block' : 'none';
    document.getElementById('signupForm').style.display = tab === 'signup' ? 'block' : 'none';
}

async function handleLogin(e) {
    e.preventDefault();

    const identifier = document.getElementById('loginIdentifier').value;
    const password = document.getElementById('loginPassword').value;

    try {
        const response = await fetch(`${CONFIG.API_BASE_URL}/auth.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'login', identifier, password })
        });

        const data = await response.json();

        if (data.success) {
            currentUser = data.user;
            localStorage.setItem('user', JSON.stringify(currentUser));
            updateUserUI();
            closeAuthModal();
            showNotification('Login successful!');
        } else {
            showNotification(data.message || 'Login failed', 'error');
        }
    } catch (error) {
        showNotification('Login error', 'error');
        console.error('Login error:', error);
    }
}

async function handleSignup(e) {
    e.preventDefault();

    const userData = {
        action: 'signup',
        full_name: document.getElementById('signupName').value,
        email: document.getElementById('signupEmail').value,
        phone: document.getElementById('signupPhone').value,
        password: document.getElementById('signupPassword').value,
        address: document.getElementById('signupAddress').value
    };

    try {
        const response = await fetch(`${CONFIG.API_BASE_URL}/auth.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(userData)
        });

        const data = await response.json();

        if (data.success) {
            currentUser = data.user;
            localStorage.setItem('user', JSON.stringify(currentUser));
            updateUserUI();
            closeAuthModal();
            showNotification('Account created successfully!');
        } else {
            showNotification(data.message || 'Signup failed', 'error');
        }
    } catch (error) {
        showNotification('Signup error', 'error');
        console.error('Signup error:', error);
    }
}

function checkAuth() {
    const savedUser = localStorage.getItem('user');
    if (savedUser) {
        currentUser = JSON.parse(savedUser);
        updateUserUI();
    }
}

function updateUserUI() {
    if (currentUser) {
        document.getElementById('navAccount').style.display = 'none';
        document.getElementById('navUser').style.display = 'flex';
        document.getElementById('userName').textContent = currentUser.full_name;

        const loyaltyBadge = document.getElementById('loyaltyBadge');
        const tier = loyaltyTiers.find(t => t.badge_name.toLowerCase() === currentUser.loyalty_badge.toLowerCase());
        if (tier) {
            loyaltyBadge.textContent = currentUser.loyalty_badge;
            loyaltyBadge.style.background = tier.color;
        }
    } else {
        document.getElementById('navAccount').style.display = 'flex';
        document.getElementById('navUser').style.display = 'none';
    }
}

function logout() {
    currentUser = null;
    localStorage.removeItem('user');
    updateUserUI();
    showNotification('Logged out successfully');
}

function openCheckout() {
    if (!currentUser) {
        showNotification('Please login to checkout', 'error');
        openAuthModal('login');
        return;
    }

    if (cart.length === 0) {
        showNotification('Your cart is empty', 'error');
        return;
    }

    document.getElementById('checkoutModal').classList.add('active');
    displayCheckoutItems();
    populateDeliveryZones();
    updateCheckoutTotal();

    if (currentUser.address) {
        document.getElementById('deliveryAddress').value = currentUser.address;
    }
    if (currentUser.phone) {
        document.getElementById('mpesaPhone').value = currentUser.phone;
    }
}

function closeCheckoutModal() {
    document.getElementById('checkoutModal').classList.remove('active');
}

function displayCheckoutItems() {
    const container = document.getElementById('checkoutItems');
    container.innerHTML = cart.map(item => `
        <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--border-color);">
            <span>${item.name} x ${item.quantity}</span>
            <span>KES ${(item.price * item.quantity).toLocaleString()}</span>
        </div>
    `).join('');
}

function populateDeliveryZones() {
    const select = document.getElementById('deliveryZone');
    select.innerHTML = '<option value="">Select zone...</option>' +
        deliveryZones.map(zone => `
            <option value="${zone.id}" data-fee="${zone.delivery_fee}">
                ${zone.name} - KES ${parseFloat(zone.delivery_fee).toLocaleString()}
            </option>
        `).join('');
}

function updateDeliveryOptions() {
    const deliveryType = document.querySelector('input[name="deliveryType"]:checked').value;
    const addressSection = document.getElementById('deliveryAddressSection');

    if (deliveryType === 'pickup') {
        addressSection.style.display = 'none';
        updateCheckoutTotal();
    } else {
        addressSection.style.display = 'block';
        updateDeliveryFee();
    }
}

function updateDeliveryFee() {
    const deliveryType = document.querySelector('input[name="deliveryType"]:checked').value;
    const select = document.getElementById('deliveryZone');
    const selectedOption = select.options[select.selectedIndex];

    let deliveryFee = 0;
    if (deliveryType === 'delivery' && selectedOption && selectedOption.dataset.fee) {
        deliveryFee = parseFloat(selectedOption.dataset.fee);
    }

    const feeInfo = document.getElementById('deliveryFeeInfo');
    if (deliveryFee === 0 && deliveryType === 'delivery') {
        feeInfo.innerHTML = '<p style="color: var(--success-color); margin-top: 8px;"><i class="fas fa-check-circle"></i> Free Delivery!</p>';
    } else {
        feeInfo.innerHTML = '';
    }

    updateCheckoutTotal();
}

function updateCheckoutTotal() {
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    const deliveryType = document.querySelector('input[name="deliveryType"]:checked').value;

    let deliveryFee = 0;
    if (deliveryType === 'delivery') {
        const select = document.getElementById('deliveryZone');
        const selectedOption = select.options[select.selectedIndex];
        if (selectedOption && selectedOption.dataset.fee) {
            deliveryFee = parseFloat(selectedOption.dataset.fee);
        }
    }

    const total = subtotal + deliveryFee;

    document.getElementById('checkoutSubtotal').textContent = `KES ${subtotal.toLocaleString()}`;
    document.getElementById('checkoutDeliveryFee').textContent = `KES ${deliveryFee.toLocaleString()}`;
    document.getElementById('checkoutTotal').textContent = `KES ${total.toLocaleString()}`;
}

async function placeOrder() {
    if (!currentUser) {
        showNotification('Please login to place order', 'error');
        return;
    }

    const deliveryType = document.querySelector('input[name="deliveryType"]:checked').value;
    const deliveryAddress = document.getElementById('deliveryAddress').value;
    const deliveryZoneSelect = document.getElementById('deliveryZone');
    const mpesaPhone = document.getElementById('mpesaPhone').value;

    if (deliveryType === 'delivery' && !deliveryZoneSelect.value) {
        showNotification('Please select a delivery zone', 'error');
        return;
    }

    if (!mpesaPhone) {
        showNotification('Please enter M-Pesa phone number', 'error');
        return;
    }

    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    let deliveryFee = 0;
    let deliveryZoneId = null;

    if (deliveryType === 'delivery') {
        const selectedOption = deliveryZoneSelect.options[deliveryZoneSelect.selectedIndex];
        deliveryFee = parseFloat(selectedOption.dataset.fee);
        deliveryZoneId = deliveryZoneSelect.value;
    }

    const orderData = {
        action: 'create_order',
        user_id: currentUser.id,
        items: cart,
        delivery_type: deliveryType,
        delivery_address: deliveryAddress,
        delivery_zone_id: deliveryZoneId,
        delivery_fee: deliveryFee,
        total_amount: subtotal + deliveryFee,
        payment_method: 'mpesa',
        mpesa_phone: mpesaPhone
    };

    try {
        const response = await fetch(`${CONFIG.API_BASE_URL}/orders.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(orderData)
        });

        const data = await response.json();

        if (data.success) {
            showNotification('Order placed successfully! Please complete M-Pesa payment.');
            cart.length = 0;
            updateCart();
            saveCartToStorage();
            closeCheckoutModal();
        } else {
            showNotification(data.message || 'Order failed', 'error');
        }
    } catch (error) {
        showNotification('Order error', 'error');
        console.error('Order error:', error);
    }
}

function openServiceBooking(serviceId) {
    if (!currentUser) {
        showNotification('Please login to book a service', 'error');
        openAuthModal('login');
        return;
    }

    const service = services.find(s => s.id === serviceId);
    if (!service) return;

    const modal = document.getElementById('serviceModal');
    const content = document.getElementById('serviceModalContent');

    content.innerHTML = `
        <h4>${service.name}</h4>
        <p>${service.description}</p>
        <p><strong>Price:</strong> KES ${parseFloat(service.price).toLocaleString()}</p>
        ${service.duration_minutes ? `<p><strong>Duration:</strong> ${service.duration_minutes} minutes</p>` : ''}

        <form id="serviceBookingForm">
            <div class="form-group">
                <label>Select Date</label>
                <input type="date" id="bookingDate" required min="${new Date().toISOString().split('T')[0]}">
            </div>
            <div class="form-group">
                <label>Select Time</label>
                <input type="time" id="bookingTime" required>
            </div>
            <div class="form-group">
                <label>Additional Notes</label>
                <textarea id="bookingNotes" rows="3"></textarea>
            </div>
            <button type="submit" class="btn-primary btn-full">Confirm Booking</button>
        </form>
    `;

    modal.classList.add('active');

    document.getElementById('serviceBookingForm').addEventListener('submit', async (e) => {
        e.preventDefault();

        const bookingData = {
            action: 'book_service',
            user_id: currentUser.id,
            service_id: serviceId,
            booking_date: document.getElementById('bookingDate').value,
            booking_time: document.getElementById('bookingTime').value,
            notes: document.getElementById('bookingNotes').value
        };

        try {
            const response = await fetch(`${CONFIG.API_BASE_URL}/services.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(bookingData)
            });

            const data = await response.json();

            if (data.success) {
                showNotification('Service booked successfully!');
                closeServiceModal();
            } else {
                showNotification(data.message || 'Booking failed', 'error');
            }
        } catch (error) {
            showNotification('Booking error', 'error');
            console.error('Booking error:', error);
        }
    });
}

function closeServiceModal() {
    document.getElementById('serviceModal').classList.remove('active');
}

function toggleChatbot() {
    document.getElementById('chatbotWindow').classList.toggle('active');
}

async function sendChatMessage() {
    const input = document.getElementById('chatbotInput');
    const message = input.value.trim();

    if (!message) return;

    addChatMessage(message, true);
    input.value = '';

    const response = await getChatbotResponse(message);
    setTimeout(() => {
        addChatMessage(response, false);
    }, 500);
}

function addChatMessage(message, isUser) {
    const messagesContainer = document.getElementById('chatbotMessages');
    const messageDiv = document.createElement('div');
    messageDiv.className = `chatbot-message ${isUser ? 'user-message' : 'bot-message'}`;
    messageDiv.innerHTML = `<p>${message}</p>`;
    messagesContainer.appendChild(messageDiv);
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

async function getChatbotResponse(message) {
    const lowerMessage = message.toLowerCase();

    if (lowerMessage.includes('hello') || lowerMessage.includes('hi')) {
        return 'Hello! How can I help you today? You can ask about our products, services, delivery, or anything else!';
    }

    if (lowerMessage.includes('product') || lowerMessage.includes('buy')) {
        return 'We offer a wide range of tech products including networking equipment (hubs, switches, routers), computers, and phones. Browse our Products section to see everything!';
    }

    if (lowerMessage.includes('service') || lowerMessage.includes('repair')) {
        return 'We provide professional services including device diagnostics, ISP services, laptop repairs, and phone repairs. Check out our Services section to book an appointment!';
    }

    if (lowerMessage.includes('delivery') || lowerMessage.includes('shipping')) {
        return 'We offer free delivery within 5km from our store! For extended areas, we have affordable delivery rates. You can also choose to pick up your order directly from our shop.';
    }

    if (lowerMessage.includes('payment') || lowerMessage.includes('mpesa')) {
        return 'We accept M-Pesa payments for your convenience. Simply enter your M-Pesa number during checkout and complete the payment prompt on your phone.';
    }

    if (lowerMessage.includes('loyalty') || lowerMessage.includes('points')) {
        return 'Join our loyalty rewards program! Earn points with every purchase and unlock exclusive benefits. Progress through Bronze, Silver, Gold, Platinum, and Diamond tiers to get up to 20% discounts!';
    }

    if (lowerMessage.includes('price') || lowerMessage.includes('cost')) {
        return 'All our products and services have clearly displayed prices. Feel free to browse our catalog or ask about specific items!';
    }

    return 'Thank you for your message! For specific inquiries, please contact us at info@techhubpro.com or call +254 XXX XXX XXX. Our team is here to help!';
}

function filterByCategory(categoryId) {
    const productsSection = document.getElementById('products');
    productsSection.scrollIntoView({ behavior: 'smooth' });

    setTimeout(() => {
        document.querySelectorAll('.filter-tab').forEach(btn => {
            btn.classList.remove('active');
            if (btn.dataset.filter === categoryId) {
                btn.classList.add('active');
            }
        });
        displayProducts(categoryId);
    }, 500);
}

function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 100px;
        right: 24px;
        background: ${type === 'success' ? 'var(--success-color)' : 'var(--error-color)'};
        color: white;
        padding: 16px 24px;
        border-radius: 8px;
        z-index: 9999;
        animation: slideIn 0.3s ease;
    `;
    notification.textContent = message;
    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}
