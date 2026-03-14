// assets/js/recommendations.js - Product Recommendations Widget

class ProductRecommendations {
    constructor() {
        this.apiEndpoint = '/outsourced/api/v1/recommendations.php';
        this.token = localStorage.getItem('auth_token');
    }

    async getRecommendations(type = 'personalized', limit = 10, productId = null) {
        try {
            let url = `${this.apiEndpoint}?type=${type}&limit=${limit}`;
            if (productId) {
                url += `&product_id=${productId}`;
            }

            const response = await fetch(url, {
                headers: {
                    'Authorization': this.token ? `Bearer ${this.token}` : ''
                }
            });

            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Failed to get recommendations:', error);
            return { success: false, data: [] };
        }
    }

    async logInteraction(productId, interactionType, additionalData = {}) {
        try {
            const response = await fetch(this.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': this.token ? `Bearer ${this.token}` : ''
                },
                body: JSON.stringify({
                    product_id: productId,
                    interaction_type: interactionType,
                    ...additionalData
                })
            });

            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Failed to log interaction:', error);
            return { success: false };
        }
    }

    async trackProductView(productId, price) {
        return this.logInteraction(productId, 'view', { price });
    }

    async trackAddToCart(productId, price) {
        return this.logInteraction(productId, 'add_to_cart', { price });
    }

    async trackPurchase(productId, price) {
        return this.logInteraction(productId, 'purchase', { price });
    }

    async trackWishlist(productId) {
        return this.logInteraction(productId, 'wishlist');
    }

    async trackCompare(productId) {
        return this.logInteraction(productId, 'compare');
    }

    async trackReview(productId, rating) {
        return this.logInteraction(productId, 'review', { rating });
    }

    renderRecommendations(containerId, recommendations, options = {}) {
        const container = document.getElementById(containerId);
        if (!container) return;

        if (!recommendations || recommendations.length === 0) {
            container.innerHTML = options.emptyMessage || '<p>No recommendations available</p>';
            return;
        }

        const {
            title = 'Recommended for You',
            showPrice = true,
            showAddToCart = true,
            columns = 4
        } = options;

        let html = `
            <div class="recommendations-section">
                <h3>${title}</h3>
                <div class="recommendations-grid" style="display: grid; grid-template-columns: repeat(${columns}, 1fr); gap: 20px;">
        `;

        recommendations.forEach(product => {
            const productUrl = `/outsourced/public/product.php?id=${product.id}`;
            const imageUrl = product.image || '/outsourced/assets/images/products/placeholder.jpg';
            const price = typeof product.price === 'number' ? product.price.toFixed(2) : parseFloat(product.price).toFixed(2);

            html += `
                <div class="recommendation-item" data-product-id="${product.id}">
                    <a href="${productUrl}">
                        <img src="${imageUrl}" alt="${product.name}" style="width: 100%; height: 200px; object-fit: cover;">
                    </a>
                    <h4><a href="${productUrl}">${product.name}</a></h4>
            `;

            if (showPrice) {
                html += `<p class="price">KES ${price}</p>`;
            }

            if (showAddToCart) {
                html += `
                    <button class="add-to-cart-btn" onclick="addToCart(${product.id})">
                        Add to Cart
                    </button>
                `;
            }

            html += `</div>`;
        });

        html += `</div></div>`;
        container.innerHTML = html;
    }

    async loadAndRenderRecommendations(containerId, options = {}) {
        const data = await this.getRecommendations(
            options.type || 'personalized',
            options.limit || 10,
            options.productId || null
        );

        if (data.success) {
            this.renderRecommendations(containerId, data.data, options);
        }
    }
}

// Global instance
const productRecommendations = new ProductRecommendations();

// Auto-track product views
document.addEventListener('DOMContentLoaded', () => {
    // Check if we're on a product page
    const productId = document.querySelector('[data-product-id]')?.dataset.productId 
        || new URLSearchParams(window.location.search).get('id');

    if (productId && window.location.pathname.includes('product.php')) {
        // Get product price
        const priceElement = document.querySelector('.product-price, [data-price]');
        const price = priceElement?.dataset?.price || priceElement?.textContent?.replace(/[KES,]/g, '') || 0;

        productRecommendations.trackProductView(productId, price);
    }
});

// Export for use in other scripts
window.ProductRecommendations = ProductRecommendations;
window.productRecommendations = productRecommendations;
