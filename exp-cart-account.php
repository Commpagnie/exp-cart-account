<?php
/**
 * Plugin Name: EXP Cart & Account
 * Description: Personnalisation des pages Panier, Checkout et Mon Compte selon le design system Express Échafaudage.
 * Version: 3.5.1
 * Author: Commpagnie
 * Author URI: https://commpagnie.fr
 * Text Domain: exp-cart-account
 * Requires PHP: 7.4
 * Requires at least: 5.8
 * WC requires at least: 7.0
 */

if (!defined('ABSPATH')) exit;

define('EXP_CA_VERSION', '3.5.2');
define('EXP_CA_PATH', plugin_dir_path(__FILE__));
define('EXP_CA_URL', plugin_dir_url(__FILE__));

function exp_ca_enqueue_styles() {
    if (is_cart() || is_checkout() || is_account_page()) {
        wp_enqueue_style('exp-cart-account', EXP_CA_URL . 'assets/css/exp-cart-account.css', [], EXP_CA_VERSION);
    }
}
add_action('wp_enqueue_scripts', 'exp_ca_enqueue_styles', 99);

function exp_ca_body_classes($classes) {
    if (is_cart()) $classes[] = 'exp-styled-cart';
    if (is_checkout()) $classes[] = 'exp-styled-checkout';
    if (is_account_page()) $classes[] = 'exp-styled-account';
    return $classes;
}
add_filter('body_class', 'exp_ca_body_classes');

function exp_ca_enable_registration() {
    add_filter('pre_option_woocommerce_enable_myaccount_registration', '__return_true');
    add_filter('pre_option_woocommerce_registration_generate_username', '__return_true');
    add_filter('pre_option_woocommerce_registration_generate_password', '__return_true');
}
add_action('init', 'exp_ca_enable_registration');

/**
 * Custom empty cart — injected as HTML in wp_footer (outside React).
 * CSS hides the WC default empty cart block.
 * JS toggles visibility based on the WC Store API cart data.
 */
function exp_ca_custom_empty_cart() {
    if (!is_cart()) return;

    $shop_url = get_permalink(wc_get_page_id('shop'));
    if (!$shop_url) $shop_url = home_url('/boutique/');
    ?>

    <div id="exp-empty-cart-custom" class="exp-empty-cart" style="display:none;">
        <div class="exp-empty-cart__inner">
            <div class="exp-empty-cart__icon">
                <svg width="80" height="80" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="40" cy="40" r="38" stroke="#E94F1A" stroke-width="2" stroke-dasharray="6 4" opacity="0.3"/>
                    <path d="M25 28H29.2L34.4 52H50.8L55 34H31.6" stroke="#E94F1A" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="36" cy="57" r="2.5" fill="#E94F1A"/>
                    <circle cx="50" cy="57" r="2.5" fill="#E94F1A"/>
                </svg>
            </div>
            <h2 class="exp-empty-cart__title">Votre panier est vide</h2>
            <p class="exp-empty-cart__text">Parcourez notre catalogue d'échafaudages professionnels et trouvez l'équipement adapté à votre chantier.</p>
            <a href="<?php echo esc_url($shop_url); ?>" class="exp-empty-cart__btn">
                <span>Voir nos produits</span>
                <span class="exp-empty-cart__btn-icon">
                    <svg width="15" height="8" viewBox="0 0 15 8" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M14.4516 2.66578L12.0326 0.187487C11.9745 0.128079 11.9053 0.0809252 11.8292 0.048746C11.7531 0.016567 11.6715 0 11.589 0C11.5066 0 11.425 0.016567 11.3489 0.048746C11.2728 0.0809252 11.2036 0.128079 11.1455 0.187487C11.0293 0.306427 10.9643 0.466144 10.9643 0.632531C10.9643 0.798918 11.0293 0.958636 11.1455 1.07758L12.9484 2.93837H0.625806C0.46115 2.93837 0.303193 3.00459 0.186708 3.12266C0.0702232 3.24073 0.00483871 3.40083 0.00483871 3.56774C0.00483871 3.73466 0.0702232 3.89476 0.186708 4.01283C0.303193 4.1309 0.46115 4.19712 0.625806 4.19712H12.9484L11.1455 6.05159C11.0293 6.17053 10.9643 6.33025 10.9643 6.49663C10.9643 6.66302 11.0293 6.82274 11.1455 6.94168C11.2036 7.00109 11.2728 7.04824 11.3489 7.08042C11.425 7.1126 11.5066 7.12917 11.589 7.12917C11.6715 7.12917 11.7531 7.1126 11.8292 7.08042C11.9053 7.04824 11.9745 7.00109 12.0326 6.94168L14.4516 4.46338C14.6839 4.22562 14.8145 3.90418 14.8145 3.56958C14.8145 3.23498 14.6839 2.91354 14.4516 2.67578V2.66578Z" fill="currentColor"/></svg>
                </span>
            </a>
            <div class="exp-empty-cart__contact">
                <span class="exp-empty-cart__contact-text">Besoin d'aide ?</span>
                <a href="tel:0383213436" class="exp-empty-cart__phone">03 83 21 34 36</a>
            </div>
        </div>
    </div>

    <script>
    (function() {
        var customEl = document.getElementById('exp-empty-cart-custom');
        if (!customEl) return;

        /* Move our block inside the Elementor cart wrapper so it sits in the right place */
        var cartBlock = document.querySelector('.wp-block-woocommerce-cart');
        if (cartBlock) {
            cartBlock.parentNode.insertBefore(customEl, cartBlock.nextSibling);
        }

        function updateVisibility() {
            /* Method 1: WC Store API (most reliable) */
            if (window.wp && wp.data && wp.data.select && wp.data.select('wc/store/cart')) {
                var cart = wp.data.select('wc/store/cart').getCartData();
                if (cart && typeof cart.itemsCount !== 'undefined') {
                    customEl.style.display = cart.itemsCount === 0 ? 'block' : 'none';
                    return;
                }
            }

            /* Method 2: Check if filled cart block is hidden by React */
            var filledBlock = document.querySelector('.wp-block-woocommerce-filled-cart-block');
            if (filledBlock) {
                var style = window.getComputedStyle(filledBlock);
                if (style.display === 'none') {
                    customEl.style.display = 'block';
                    return;
                }
                customEl.style.display = 'none';
                return;
            }

            /* Method 3: Check is-loading removed and no filled cart rendered */
            var wrapper = document.querySelector('.wp-block-woocommerce-cart');
            if (wrapper && !wrapper.classList.contains('is-loading')) {
                var hasItems = wrapper.querySelector('.wc-block-cart__main');
                customEl.style.display = hasItems ? 'none' : 'block';
            }
        }

        /* Poll until React has loaded */
        var attempts = 0;
        function poll() {
            var wrapper = document.querySelector('.wp-block-woocommerce-cart');
            if (wrapper && !wrapper.classList.contains('is-loading')) {
                updateVisibility();
                startObserver();
                return;
            }
            if (attempts++ < 50) setTimeout(poll, 200);
        }

        function startObserver() {
            var wrapper = document.querySelector('.wp-block-woocommerce-cart');
            if (!wrapper) return;
            new MutationObserver(function() {
                setTimeout(updateVisibility, 100);
            }).observe(wrapper, { childList: true, subtree: true, attributes: true });
        }

        /* Also subscribe to WC Store changes if available */
        function subscribeStore() {
            if (window.wp && wp.data && wp.data.subscribe) {
                wp.data.subscribe(function() {
                    updateVisibility();
                });
            } else {
                setTimeout(subscribeStore, 1000);
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                poll();
                subscribeStore();
            });
        } else {
            poll();
            subscribeStore();
        }
    })();
    </script>
    <?php
}
add_action('wp_footer', 'exp_ca_custom_empty_cart', 20);
