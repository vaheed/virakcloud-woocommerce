<?php
/**
 * Plugin Name: Virak Cloud for WooCommerce
 * Plugin URI: https://virakcloud.com
 * Description: Resell Virak Cloud VM services via WooCommerce with inline instance management for customers and admins. Features VM configurator, provisioning, and real-time instance control.
 * Version: 1.1.0
 * Requires at least: 6.2
 * Requires PHP: 7.0
 * Author: VirakCloud
 * Author URI: https://virakcloud.com
 * Text Domain: virakcloud-woo
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */
defined('ABSPATH') || exit;
if (!defined('VCW_PATH')) define('VCW_PATH', plugin_dir_path(__FILE__));
if (!defined('VCW_URL'))  define('VCW_URL', plugin_dir_url(__FILE__));

spl_autoload_register(function($class){
    if (strpos($class, 'VirakCloud\\Woo\\') !== 0) return;
    $rel = str_replace('VirakCloud\\Woo\\','',$class);
    $rel = str_replace('\\', DIRECTORY_SEPARATOR, $rel);
    $file = VCW_PATH . 'includes/' . $rel . '.php';
    if (file_exists($file)) require_once $file;
});

add_action('plugins_loaded', static function(){
    if (class_exists('VirakCloud\\Woo\\Plugin')) {
        \VirakCloud\Woo\Plugin::instance()->init();
    }
});

// Hide Virak Cloud API IDs from order item meta display (client-facing)
add_filter('woocommerce_hidden_order_itemmeta', function($hidden_meta) {
    $vcw_hidden_meta = [
        'vcw_service_offering_id',
        'vcw_zone_id', 
        'vcw_network_offering_id',
        'vcw_image_id',
        'vcw_instance_id',
        'vcw_provision_status'
    ];
    return array_merge($hidden_meta, $vcw_hidden_meta);
});

// Additional filter to hide API IDs from order item meta key display
add_filter('woocommerce_order_item_display_meta_key', function($display_key, $meta, $item) {
    $vcw_hidden_keys = [
        'vcw_service_offering_id',
        'vcw_zone_id', 
        'vcw_network_offering_id',
        'vcw_image_id',
        'vcw_instance_id',
        'vcw_provision_status'
    ];
    
    if (in_array($meta->key, $vcw_hidden_keys)) {
        return ''; // Hide the meta key completely
    }
    
    return $display_key;
}, 10, 3);

// Additional filter to hide API IDs from order item meta value display
add_filter('woocommerce_order_item_display_meta_value', function($display_value, $meta, $item) {
    $vcw_hidden_keys = [
        'vcw_service_offering_id',
        'vcw_zone_id', 
        'vcw_network_offering_id',
        'vcw_image_id',
        'vcw_instance_id',
        'vcw_provision_status'
    ];
    
    if (in_array($meta->key, $vcw_hidden_keys)) {
        return ''; // Hide the meta value completely
    }
    
    return $display_value;
}, 10, 3);

// Add plugin links
add_filter('plugin_row_meta', function($links, $file) {
    if (plugin_basename(__FILE__) === $file) {
        $links[] = '<a href="https://docs.virakcloud.com/" target="_blank">' . __('Docs', 'virakcloud-woo') . '</a>';
        $links[] = '<a href="https://api-docs.virakcloud.com/" target="_blank">' . __('API docs', 'virakcloud-woo') . '</a>';
    }
    return $links;
}, 10, 2);

add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    $links[] = '<a href="https://docs.virakcloud.com/" target="_blank">' . __('Docs', 'virakcloud-woo') . '</a>';
    $links[] = '<a href="https://api-docs.virakcloud.com" target="_blank">' . __('API docs', 'virakcloud-woo') . '</a>';
    return $links;
});
