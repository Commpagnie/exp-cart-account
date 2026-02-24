<?php
/**
 * Plugin Name: EXP Cart & Account
 * Description: Personnalisation des pages Panier, Checkout et Mon Compte selon le design system Express Échafaudage.
 * Version: 3.2.0
 * Author: Commpagnie
 * Author URI: https://commpagnie.fr
 * Text Domain: exp-cart-account
 * Requires PHP: 7.4
 * Requires at least: 5.8
 * WC requires at least: 7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('EXP_CA_VERSION', '3.2.0');
define('EXP_CA_PATH', plugin_dir_path(__FILE__));
define('EXP_CA_URL', plugin_dir_url(__FILE__));

/**
 * Enqueue styles on cart, checkout and my account pages
 */
function exp_ca_enqueue_styles() {
    if (is_cart() || is_checkout() || is_account_page()) {
        wp_enqueue_style(
            'exp-cart-account',
            EXP_CA_URL . 'assets/css/exp-cart-account.css',
            [],
            EXP_CA_VERSION
        );
    }
}
add_action('wp_enqueue_scripts', 'exp_ca_enqueue_styles', 99);

/**
 * Add body classes for targeted styling
 */
function exp_ca_body_classes($classes) {
    if (is_cart()) {
        $classes[] = 'exp-styled-cart';
    }
    if (is_checkout()) {
        $classes[] = 'exp-styled-checkout';
    }
    if (is_account_page()) {
        $classes[] = 'exp-styled-account';
    }
    return $classes;
}
add_filter('body_class', 'exp_ca_body_classes');

/**
 * Inject cross-sells into the WooCommerce cart page
 * Displays "Vous seriez peut-être intéressé par..." section
 */
function exp_ca_display_cross_sells() {
    if (!is_cart()) {
        return;
    }

    // Get cart items
    $cart = WC()->cart;
    if (!$cart || $cart->is_empty()) {
        return;
    }

    // Collect cross-sell IDs from cart items
    $cross_sell_ids = $cart->get_cross_sells();

    if (empty($cross_sell_ids)) {
        return;
    }

    // Limit to 4 products
    $cross_sell_ids = array_slice($cross_sell_ids, 0, 4);

    $products = array_filter(array_map('wc_get_product', $cross_sell_ids));

    if (empty($products)) {
        return;
    }

    ?>
    <div class="exp-cross-sells cross-sells">
        <h2><?php esc_html_e('Vous seriez peut-être intéressé par...', 'exp-cart-account'); ?></h2>
        <div class="exp-cross-sells-grid">
            <?php foreach ($products as $product) : ?>
                <div class="exp-cross-sell-item">
                    <a href="<?php echo esc_url($product->get_permalink()); ?>" class="exp-cross-sell-link">
                        <div class="exp-cross-sell-image">
                            <?php echo $product->get_image('woocommerce_thumbnail'); ?>
                            <?php if ($product->is_on_sale()) : ?>
                                <span class="exp-cross-sell-badge">Promo</span>
                            <?php endif; ?>
                        </div>
                        <div class="exp-cross-sell-info">
                            <h3 class="exp-cross-sell-title"><?php echo esc_html($product->get_name()); ?></h3>
                            <div class="exp-cross-sell-price">
                                <?php echo $product->get_price_html(); ?>
                            </div>
                        </div>
                    </a>
                    <?php if ($product->is_type('simple') && $product->is_purchasable() && $product->is_in_stock()) : ?>
                        <div class="exp-cross-sell-action">
                            <a href="<?php echo esc_url($product->add_to_cart_url()); ?>" 
                               class="exp-cross-sell-btn ajax_add_to_cart add_to_cart_button" 
                               data-product_id="<?php echo esc_attr($product->get_id()); ?>"
                               data-quantity="1">
                                Ajouter au panier
                            </a>
                        </div>
                    <?php else : ?>
                        <div class="exp-cross-sell-action">
                            <a href="<?php echo esc_url($product->get_permalink()); ?>" class="exp-cross-sell-btn">
                                Voir le produit
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}
add_action('woocommerce_after_cart', 'exp_ca_display_cross_sells', 10);
add_action('woocommerce_cart_contents', 'exp_ca_cross_sells_after_block_cart', 99);

/**
 * For block-based cart, inject cross-sells via footer script
 * since WooCommerce blocks don't use traditional hooks
 */
function exp_ca_inject_cross_sells_block_cart() {
    if (!is_cart()) {
        return;
    }

    $cart = WC()->cart;
    if (!$cart || $cart->is_empty()) {
        return;
    }

    $cross_sell_ids = $cart->get_cross_sells();
    if (empty($cross_sell_ids)) {
        return;
    }

    $cross_sell_ids = array_slice($cross_sell_ids, 0, 4);
    $products = array_filter(array_map('wc_get_product', $cross_sell_ids));

    if (empty($products)) {
        return;
    }

    $items_html = '';
    foreach ($products as $product) {
        $image = wp_get_attachment_image_url($product->get_image_id(), 'woocommerce_thumbnail');
        if (!$image) {
            $image = wc_placeholder_img_src('woocommerce_thumbnail');
        }
        $name = esc_html($product->get_name());
        $price = $product->get_price_html();
        $link = esc_url($product->get_permalink());
        $badge = $product->is_on_sale() ? '<span class="exp-cross-sell-badge">Promo</span>' : '';

        if ($product->is_type('simple') && $product->is_purchasable() && $product->is_in_stock()) {
            $btn = '<div class="exp-cross-sell-action"><a href="' . esc_url($product->add_to_cart_url()) . '" class="exp-cross-sell-btn ajax_add_to_cart add_to_cart_button" data-product_id="' . esc_attr($product->get_id()) . '" data-quantity="1">Ajouter au panier</a></div>';
        } else {
            $btn = '<div class="exp-cross-sell-action"><a href="' . $link . '" class="exp-cross-sell-btn">Voir le produit</a></div>';
        }

        $items_html .= '<div class="exp-cross-sell-item">';
        $items_html .= '<a href="' . $link . '" class="exp-cross-sell-link">';
        $items_html .= '<div class="exp-cross-sell-image"><img src="' . esc_url($image) . '" alt="' . esc_attr($name) . '">' . $badge . '</div>';
        $items_html .= '<div class="exp-cross-sell-info"><h3 class="exp-cross-sell-title">' . $name . '</h3>';
        $items_html .= '<div class="exp-cross-sell-price">' . $price . '</div></div>';
        $items_html .= '</a>';
        $items_html .= $btn;
        $items_html .= '</div>';
    }

    ?>
    <script>
    (function() {
        function injectCrossSells() {
            if (document.querySelector('.exp-cross-sells')) return;
            
            // Target: WooCommerce block-based cart
            var cartBlock = document.querySelector('.wp-block-woocommerce-cart');
            if (!cartBlock) return;

            // Wait for cart to be fully loaded (not in loading state)
            if (cartBlock.classList.contains('is-loading')) {
                setTimeout(injectCrossSells, 500);
                return;
            }

            var crossSellsHtml = '<div class="exp-cross-sells cross-sells">' +
                '<h2>Vous seriez peut-être intéressé par...</h2>' +
                '<div class="exp-cross-sells-grid">' +
                <?php echo json_encode($items_html); ?> +
                '</div></div>';

            var wrapper = document.createElement('div');
            wrapper.innerHTML = crossSellsHtml;
            cartBlock.parentNode.insertBefore(wrapper.firstChild, cartBlock.nextSibling);
        }

        // Run when DOM is ready and also after potential AJAX cart updates
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(injectCrossSells, 1000);
            });
        } else {
            setTimeout(injectCrossSells, 1000);
        }

        // Re-inject after WC block cart updates
        var observer = new MutationObserver(function(mutations) {
            for (var m of mutations) {
                if (m.target.classList && m.target.classList.contains('wp-block-woocommerce-cart')) {
                    if (!m.target.classList.contains('is-loading') && !document.querySelector('.exp-cross-sells')) {
                        setTimeout(injectCrossSells, 500);
                    }
                }
            }
        });
        var cartEl = document.querySelector('.wp-block-woocommerce-cart');
        if (cartEl) {
            observer.observe(cartEl, { attributes: true, attributeFilter: ['class'] });
        }
    })();
    </script>
    <?php
}
add_action('wp_footer', 'exp_ca_inject_cross_sells_block_cart', 20);
