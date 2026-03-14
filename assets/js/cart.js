// assets/js/cart.js
document.addEventListener('DOMContentLoaded', function () {
    // Quantity change
    document.querySelectorAll('.qty-input').forEach(input => {
        input.addEventListener('change', function () {
            const itemId = this.dataset.id;
            const qty = parseInt(this.value);
            if (qty < 1) this.value = 1;
            updateCartItem(itemId, qty);
        });
    });

    // Remove item buttons
    document.querySelectorAll('.btn-remove').forEach(btn => {
        btn.addEventListener('click', function () {
            if (confirm('Remove this item?')) {
                const itemId = this.dataset.id;
                removeCartItem(itemId);
            }
        });
    });
});

function updateCartItem(itemId, quantity) {
    fetch(BASE_URL + '../api/v1/cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ item_id: itemId, quantity: quantity, action: 'update' }),
        credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload(); // simple refresh – or update DOM dynamically
        } else {
            alert(data.message || 'Update failed');
        }
    });
}

function removeCartItem(itemId) {
    fetch(BASE_URL + '../api/v1/cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ item_id: itemId, action: 'remove' }),
        credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Could not remove item');
        }
    });
}