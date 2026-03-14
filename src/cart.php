<?php
// src/cart.php – session-based cart

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

/**
 * Get current cart (array of items)
 */
function get_cart(): array {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    return $_SESSION['cart'];
}

/**
 * Add product to cart
 */
function cart_add(int $product_id, int $quantity = 1): bool {
    $product = fetchOne("SELECT id, name, price FROM products WHERE id = ? AND visible = 1", [$product_id]);
    if (!$product) return false;

    $cart = get_cart(); // already ensured exists

    foreach ($cart as &$item) {
        if ($item['product_id'] === $product_id) {
            $item['quantity'] += $quantity;
            $_SESSION['cart'] = $cart; // save back
            return true;
        }
    }

    $cart[] = [
        'product_id' => $product_id,
        'name'       => $product['name'],
        'price'      => $product['price'],
        'quantity'   => $quantity
    ];

    $_SESSION['cart'] = $cart;
    return true;
}

/**
 * Update quantity
 */
function cart_update(int $product_id, int $quantity): bool {
    if ($quantity < 1) return cart_remove($product_id);

    $cart = get_cart();

    foreach ($cart as &$item) {
        if ($item['product_id'] === $product_id) {
            $item['quantity'] = $quantity;
            $_SESSION['cart'] = $cart;
            return true;
        }
    }
    return false;
}

/**
 * Remove item
 */
function cart_remove(int $product_id): bool {
    $cart = get_cart();

    foreach ($cart as $key => $item) {
        if ($item['product_id'] === $product_id) {
            unset($cart[$key]);
            $cart = array_values($cart);
            $_SESSION['cart'] = $cart;
            return true;
        }
    }
    return false;
}

/**
 * Clear entire cart
 */
function cart_clear(): void {
    unset($_SESSION['cart']);
}

/**
 * Get cart total
 */
function cart_total(): float {
    $total = 0;
    foreach (get_cart() as $item) {
        $total += $item['price'] * $item['quantity'];
    }
    return round($total, 2);
}

/**
 * Get item count
 */
function cart_count(): int {
    $count = 0;
    foreach (get_cart() as $item) {
        $count += $item['quantity'];
    }
    return $count;
}