<?php
namespace VirakCloud\Woo;
defined('ABSPATH') || exit;

class Catalog_Behavior {
    public static function hooks(): void {
        add_filter('woocommerce_loop_add_to_cart_link', [__CLASS__, 'force_configure_link'], 10, 3);
    }
    public static function force_configure_link($button, $product, $args){
        if (!$product instanceof \WC_Product) return $button;
        $sid = get_post_meta($product->get_id(), '_vcw_service_id', true);
        $type= get_post_meta($product->get_id(), '_vcw_service_type', true);
        if ($sid && $type==='service_offerings'){
            $url = get_permalink($product->get_id());
            $label = esc_html__('Configure', 'virakcloud-woo');
            return '<a href="'.esc_url($url).'" class="button add_to_cart_button vcw-configure">'. $label .'</a>';
        }
        return $button;
    }
}
