<?php
/**
 * Plugin Name: EXP Cart & Account
 * Description: Personnalisation des pages Panier, Checkout et Mon Compte selon le design system Express Échafaudage.
 * Version: 3.3.0
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

define('EXP_CA_VERSION', '3.3.0');
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
 * Get suggested products for cross-sells section.
 * Priority: 1) WooCommerce cross-sells  2) Products from same categories  3) Recent products
 *
 * @return WC_Product[] Array of product objects (max 4)
 */
function exp_ca_get_suggested_products() {
    $cart = WC()->cart;
    if (!$cart || $cart->is_empty()) {
        return [];
    }

    $cart_product_ids = [];
    foreach ($cart->get_cart() as $item) {
        $cart_product_ids[] = $item['product_id'];
    }

    // 1) Try WooCommerce cross-sells
    $cross_sell_ids = $cart->get_cross_sells();
    if (!empty($cross_sell_ids)) {
        $cross_sell_ids = array_slice($cross_sell_ids, 0, 4);
        $products = array_filter(array_map('wc_get_product', $cross_sell_ids));
        if (!empty($products)) {
            return $products;
        }
    }

    // 2) Fallback: products from the same categories (excluding cart items)
    $category_ids = [];
    foreach ($cart_product_ids as $pid) {
        $terms = get_the_terms($pid, 'product_cat');
        if ($terms && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                $category_ids[] = $term->term_id;
            }
        }
    }
    $category_ids = array_unique($category_ids);

    if (!empty($category_ids)) {
        $args = [
            'status'   => 'publish',
            'limit'    => 4,
            'exclude'  => $cart_product_ids,
            'orderby'  => 'rand',
            'category' => $category_ids,
        ];
        $products = wc_get_products($args);
        if (!empty($products)) {
            return $products;
        }
    }

    // 3) Last fallback: recent products
    $args = [
        'status'  => 'publish',
        'limit'   => 4,
        'exclude' => $cart_product_ids,
        'orderby' => 'date',
        'order'   => 'DESC',
    ];
    $products = wc_get_products($args);
    return $products ?: [];
}

/**
 * Build the HTML for a single cross-sell product card
 */
function exp_ca_render_product_card($product) {
    $link  = esc_url($product->get_permalink());
    $name  = esc_html($product->get_name());
    $image_id = $product->get_image_id();
    $image = $image_id ? wp_get_attachment_image_url($image_id, 'woocommerce_thumbnail') : wc_placeholder_img_src('woocommerce_thumbnail');
    $price = $product->get_price_html();
    $badge = $product->is_on_sale() ? '<span class="exp-cross-sell-badge">Promo</span>' : '';
    $is_simple = $product->is_type('simple') && $product->is_purchasable() && $product->is_in_stock();

    $html  = '<div class="exp-cross-sell-item">';
    $html .= '<a href="' . $link . '" class="exp-cross-sell-link">';
    $html .= '<div class="exp-cross-sell-image">';
    $html .= '<img src="' . esc_url($image) . '" alt="' . $name . '">' . $badge;
    $html .= '</div>';
    $html .= '<div class="exp-cross-sell-info">';
    $html .= '<h3 class="exp-cross-sell-title">' . $name . '</h3>';
    $html .= '<div class="exp-cross-sell-price">' . $price . '</div>';
    $html .= '</div></a>';
    $html .= '<div class="exp-cross-sell-action">';
    if ($is_simple) {
        $html .= '<a href="' . esc_url($product->add_to_cart_url()) . '" class="exp-cross-sell-btn ajax_add_to_cart add_to_cart_button" data-product_id="' . esc_attr($product->get_id()) . '" data-quantity="1">Ajouter au panier</a>';
    } else {
        $html .= '<a href="' . $link . '" class="exp-cross-sell-btn">Voir le produit</a>';
    }
    $html .= '</div></div>';
    return $html;
}

/**
 * Inject cross-sells into WooCommerce block-based cart via footer script
 */
function exp_ca_inject_cross_sells_block_cart() {
    if (!is_cart()) {
        return;
    }

    $products = exp_ca_get_suggested_products();
    if (empty($products)) {
        return;
    }

    $cards_html = '';
    foreach ($products as $product) {
        $cards_html .= exp_ca_render_product_card($product);
    }
    $cards_json = json_encode($cards_html);

    ?>
    <script>
    (function() {
        function injectCrossSells() {
            if (document.querySelector('.exp-cross-sells')) return;

            var cartBlock = document.querySelector('.wp-block-woocommerce-cart');
            if (!cartBlock) return;

            if (cartBlock.classList.contains('is-loading')) {
                setTimeout(injectCrossSells, 500);
                return;
            }

            var filledCart = cartBlock.querySelector('.wp-block-woocommerce-filled-cart-block');
            if (!filledCart || filledCart.offsetHeight === 0) {
                setTimeout(injectCrossSells, 500);
                return;
            }

            var wrapper = document.createElement('div');
            wrapper.className = 'exp-cross-sells cross-sells';
            wrapper.innerHTML = '<h2>Vous seriez peut-\u00eatre int\u00e9ress\u00e9 par...</h2>' +
                '<div class="exp-cross-sells-grid">' +
                <?php echo $cards_json; ?> +
                '</div>';

            cartBlock.parentNode.insertBefore(wrapper, cartBlock.nextSibling);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(injectCrossSells, 1500);
            });
        } else {
            setTimeout(injectCrossSells, 1500);
        }

        var observer = new MutationObserver(function(mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var target = mutations[i].target;
                if (target.classList && (
                    target.classList.contains('wp-block-woocommerce-cart') ||
                    target.classList.contains('wp-block-woocommerce-filled-cart-block')
                )) {
                    if (!document.querySelector('.exp-cross-sells')) {
                        setTimeout(injectCrossSells, 800);
                    }
                }
            }
        });

        var initObserver = function() {
            var cartEl = document.querySelector('.wp-block-woocommerce-cart');
            if (cartEl) {
                observer.observe(cartEl, { attributes: true, attributeFilter: ['class'], subtree: true, childList: true });
            } else {
                setTimeout(initObserver, 500);
            }
        };
        initObserver();
    })();
    </script>
    <?php
}
add_action('wp_footer', 'exp_ca_inject_cross_sells_block_cart', 20);

/**
 * Also hook into traditional WooCommerce cart (non-block) for compatibility
 */
function exp_ca_display_cross_sells_classic() {
    if (!is_cart()) {
        return;
    }

    $products = exp_ca_get_suggested_products();
    if (empty($products)) {
        return;
    }

    echo '<div class="exp-cross-sells cross-sells">';
    echo '<h2>Vous seriez peut-&ecirc;tre int&eacute;ress&eacute; par...</h2>';
    echo '<div class="exp-cross-sells-grid">';
    foreach ($products as $product) {
        echo exp_ca_render_product_card($product);
    }
    echo '</div></div>';
}
add_action('woocommerce_after_cart', 'exp_ca_display_cross_sells_classic', 10);
