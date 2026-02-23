<?php
/**
 * Plugin Name: EXP Cart & Account
 * Description: Personnalisation des pages Panier, Checkout et Mon Compte selon le design system Express Échafaudage.
 * Version: 1.2.0
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

define('EXP_CA_VERSION', '1.2.0');
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
