// assets/js/checkout.js

// Handle checkout form submission with AJAX
document.addEventListener('DOMContentLoaded', function() {
    const checkoutForm = document.getElementById('checkoutForm');
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', handleCheckoutSubmit);
    }
});

async function handleCheckoutSubmit(e) {
    e.preventDefault();
    
    const payButton = document.getElementById('payButton');
    const originalText = payButton ? payButton.innerHTML : 'Processing...';
    
    // Disable button and show loading
    if (payButton) {
        payButton.disabled = true;
        payButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
    }
    
    try {
        // Get form data
        const formData = new FormData();
        formData.append('action', 'create_order');
        formData.append('delivery_type', document.getElementById('delivery_type')?.value || 'pickup');
        formData.append('address', document.getElementById('address')?.value || '');
        formData.append('phone', document.getElementById('phone')?.value || '');
        formData.append('delivery_fee', document.getElementById('delivery_fee')?.value || 0);
        formData.append('coupon_code', document.getElementById('coupon_code')?.value || '');
        formData.append('total', document.getElementById('total')?.value || document.getElementById('total_amount')?.value);
        
        // Submit order via AJAX
        const response = await fetch(BASE_URL + '../api/v1/orders.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        
        const data = await response.json();
        
        if (data.success && data.order_id) {
            // Order created - now initiate M-Pesa payment
            console.log('Order created:', data.order_id);
            
            // Store order_id in hidden field for initiateMpesa
            let orderIdField = document.getElementById('order_id');
            if (!orderIdField) {
                orderIdField = document.createElement('input');
                orderIdField.type = 'hidden';
                orderIdField.id = 'order_id';
                document.getElementById('checkoutForm').appendChild(orderIdField);
            }
            orderIdField.value = data.order_id;
            
            // Set the total amount for M-Pesa
            const totalField = document.getElementById('total_amount');
            if (totalField) totalField.value = data.total;
            
            // Now initiate M-Pesa STK Push
            await initiateMpesaWithOrder(data.order_id, data.total);
        } else {
            alert(data.message || 'Failed to create order');
            if (payButton) {
                payButton.disabled = false;
                payButton.innerHTML = originalText;
            }
        }
    } catch (err) {
        console.error('Checkout error:', err);
        alert('Error processing order. Please try again.');
        if (payButton) {
            payButton.disabled = false;
            payButton.innerHTML = originalText;
        }
    }
}

// Modified M-Pesa function that takes order_id and amount
async function initiateMpesaWithOrder(orderId, total) {
    const phone = document.getElementById('phone')?.value.trim();
    if (!phone || !phone.startsWith('0') || phone.length !== 10) {
        alert('Please enter a valid Kenyan phone number (07xx xxx xxx)');
        return;
    }

    const amount = total || document.getElementById('total_amount')?.value;
    if (!amount || parseFloat(amount) <= 0) {
        alert('Invalid order total');
        return;
    }

    const payButton = document.getElementById('payButton');
    if (payButton) {
        payButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending M-Pesa Request...';
    }

    try {
        const response = await fetch(BASE_URL + '../api/mpesa/stk-push.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                phone: '254' + phone.substring(1), // convert 07xx to 2547xx
                amount: amount,
                order_id: orderId
            }),
            credentials: 'same-origin'
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Please check your phone for M-Pesa prompt. Enter PIN to complete payment.');
            // Redirect to confirmation after delay
            setTimeout(() => {
                window.location.href = BASE_URL + 'order-confirmation.php?order=' + orderId;
            }, 5000);
        } else {
            alert(data.message || 'Payment initiation failed. Your order has been saved.');
            // Still redirect to order page even if payment fails
            setTimeout(() => {
                window.location.href = BASE_URL + 'order-confirmation.php?order=' + orderId;
            }, 2000);
        }
    } catch (err) {
        console.error('M-Pesa error:', err);
        alert('Payment service error. Your order has been saved.');
        // Redirect to order page
        setTimeout(() => {
            window.location.href = BASE_URL + 'order-confirmation.php?order=' + orderId;
        }, 2000);
    }
}

// Called when delivery address changes or delivery type is selected
function calculateDeliveryFee() {
    const addressInput = document.getElementById('address');
    const typeSelect = document.getElementById('delivery_type');
    const feeDisplay = document.getElementById('delivery_fee_display');
    const feeHidden = document.getElementById('delivery_fee');

    if (!addressInput || !typeSelect) return;

    const type = typeSelect.value;

    if (type === 'pickup') {
        feeHidden.value = '0';
        feeDisplay.textContent = 'KSh 0 (Free Pickup)';
        updateTotal();
        return;
    }

    const address = addressInput.value.trim();
    if (!address) {
        feeDisplay.textContent = 'Enter address to calculate';
        return;
    }

    // Very basic client-side approximation – in real app, use Google Maps API or backend calculation
    // Here we just simulate based on keywords (replace with real API call)
    let fee = 0;
    const lowerAddr = address.toLowerCase();

    if (lowerAddr.includes('mlolongo') || lowerAddr.includes('syokimau')) {
        fee = 0; // free in close zone
    } else if (lowerAddr.includes('nairobi') || lowerAddr.includes('athiriver')) {
        fee = 150;
    } else {
        fee = 400; // default far
    }

    feeHidden.value = fee;
    feeDisplay.textContent = `KSh ${fee.toLocaleString()}`;
    updateTotal();
}

function updateTotal() {
    const subtotal = parseFloat(document.getElementById('subtotal')?.value || 0);
    const fee = parseFloat(document.getElementById('delivery_fee')?.value || 0);
    const totalEl = document.getElementById('grand_total_display');
    const totalHidden = document.getElementById('total_amount');

    const total = subtotal + fee;
    if (totalEl) totalEl.textContent = `KSh ${total.toLocaleString()}`;
    if (totalHidden) totalHidden.value = total;
}

// Initiate M-Pesa STK Push
function initiateMpesa() {
    const phone = document.getElementById('phone')?.value.trim();
    if (!phone || !phone.startsWith('0') || phone.length !== 10) {
        alert('Please enter a valid Kenyan phone number (07xx xxx xxx)');
        return;
    }

    const total = document.getElementById('total_amount')?.value;
    if (!total || parseFloat(total) <= 0) {
        alert('Invalid order total');
        return;
    }

    fetch(BASE_URL + '../api/mpesa/stk-push.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            phone: '254' + phone.substring(1), // convert 07xx to 2547xx
            amount: total,
            order_id: document.getElementById('order_id')?.value || 'temp'
        }),
        credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Please check your phone for M-Pesa prompt. Enter PIN to complete.');
            // Optionally poll for status or redirect to waiting page
            setTimeout(() => {
                window.location.href = BASE_URL + 'order-confirmation.php?order=' + data.order_id;
            }, 8000);
        } else {
            alert(data.message || 'Payment initiation failed');
        }
    })
    .catch(err => {
        console.error(err);
        alert('Error connecting to payment service');
    });
}

// Attach listeners
document.addEventListener('DOMContentLoaded', () => {
    const addressInput = document.getElementById('delivery_address');
    const typeSelect = document.getElementById('delivery_type');

    if (addressInput) addressInput.addEventListener('input', calculateDeliveryFee);
    if (typeSelect) typeSelect.addEventListener('change', calculateDeliveryFee);

    const payBtn = document.getElementById('pay-mpesa');
    if (payBtn) payBtn.addEventListener('click', initiateMpesa);
});