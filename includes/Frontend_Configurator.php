<?php
namespace VirakCloud\Woo;
defined('ABSPATH') || exit;

class Frontend_Configurator {
    public static function hooks(): void {
        add_action('wp_enqueue_scripts', [__CLASS__, 'assets']);
        add_action('woocommerce_before_add_to_cart_button', [__CLASS__, 'render_fields']);
        add_filter('woocommerce_add_cart_item_data', [__CLASS__, 'capture'], 10, 3);
        add_filter('woocommerce_add_to_cart_validation', [__CLASS__, 'validate_before_add'], 10, 3);

        add_action('woocommerce_checkout_create_order_line_item', [__CLASS__, 'persist_to_order'], 10, 4);
        add_action('wp_ajax_vcw_cfg_load', [__CLASS__, 'ajax_load']);
        add_action('wp_ajax_nopriv_vcw_cfg_load', [__CLASS__, 'ajax_load']);
    }
    public static function assets(): void {
        wp_register_script('vcw-cfg', VCW_URL.'assets/vcw-cfg.js', ['jquery'], '1.0', true);
        wp_localize_script('vcw-cfg','VCWCFG',[
            'ajax'=>admin_url('admin-ajax.php'),
            'nonce'=>wp_create_nonce('vcw_cfg'),
            'defaults'=>Settings_Store::get(),
        ]);
        wp_enqueue_script('vcw-cfg');
        wp_enqueue_style('vcw-cfg-css', VCW_URL.'assets/vcw-cfg.css', [], '1.0');
    }
    public static function render_fields(): void {
        global $product;
        if (!$product || !($product instanceof \WC_Product)) return;
        $sid = get_post_meta($product->get_id(), '_vcw_service_id', true);
        $type = get_post_meta($product->get_id(), '_vcw_service_type', true);
        if (!$sid || $type !== 'service_offerings') return;
        $d = Settings_Store::get();
        ?>
        <div class="vcw-config">
            <h4><?php echo esc_html__('Cloud Instance Options','virakcloud-woo'); ?></h4>
            <div class="vcw-row">
                <label for="vcw_cfg_zone"><?php echo esc_html__('Zone','virakcloud-woo'); ?></label>
                <select id="vcw_cfg_zone" name="vcw_cfg_zone"></select>
            </div>
            <div class="vcw-row">
                <label for="vcw_cfg_net"><?php echo esc_html__('Network Offering','virakcloud-woo'); ?></label>
                <select id="vcw_cfg_net" name="vcw_cfg_net"></select>
            </div>
            <div class="vcw-row">
                <label for="vcw_cfg_img"><?php echo esc_html__('VM Image','virakcloud-woo'); ?></label>
                <select id="vcw_cfg_img" name="vcw_cfg_img"></select>
            </div>
        </div>
        <script>window.VCWCFG_PROD = <?php echo wp_json_encode(['default_zone'=>$d['zone_id'],'default_net'=>$d['network_offering_id'],'default_img'=>$d['vm_image_id']]); ?>;</script>
        <?php
    }
    public static function capture($cart_item_data, $product_id, $variation_id){
        $sid = get_post_meta($product_id, '_vcw_service_id', true);
        $type = get_post_meta($product_id, '_vcw_service_type', true);
        if (!$sid || $type!=='service_offerings') return $cart_item_data;
        $zone = isset($_POST['vcw_cfg_zone']) ? sanitize_text_field(wp_unslash($_POST['vcw_cfg_zone'])) : '';
        $net  = isset($_POST['vcw_cfg_net']) ? sanitize_text_field(wp_unslash($_POST['vcw_cfg_net'])) : '';
        $img  = isset($_POST['vcw_cfg_img']) ? sanitize_text_field(wp_unslash($_POST['vcw_cfg_img'])) : '';
        $cart_item_data['vcw'] = ['service_offering_id'=>$sid, 'zone_id'=>$zone, 'network_offering_id'=>$net, 'image_id'=>$img];
        return $cart_item_data;
    }
    public static function persist_to_order($item, $cart_item_key, $values, $order){
        if (!empty($values['vcw'])){
            foreach ($values['vcw'] as $k=>$v){ $item->add_meta_data('vcw_'.$k, $v, true); }
        }
    }

    public static function validate_before_add($passed, $product_id, $quantity){
        $sid = get_post_meta($product_id, '_vcw_service_id', true);
        $type = get_post_meta($product_id, '_vcw_service_type', true);
        if ($sid && $type==='service_offerings'){
            $zone = isset($_POST['vcw_cfg_zone']) ? sanitize_text_field(wp_unslash($_POST['vcw_cfg_zone'])) : '';
            $net  = isset($_POST['vcw_cfg_net']) ? sanitize_text_field(wp_unslash($_POST['vcw_cfg_net'])) : '';
            $img  = isset($_POST['vcw_cfg_img']) ? sanitize_text_field(wp_unslash($_POST['vcw_cfg_img'])) : '';
            $d = Settings_Store::get();
            if (!$zone) $zone = (string)($d['zone_id'] ?? '');
            if (!$net)  $net  = (string)($d['network_offering_id'] ?? '');
            if (!$img)  $img  = (string)($d['vm_image_id'] ?? '');
            if (!$zone || !$net || !$img){
                wc_add_notice(__('Please select Zone, Network and VM Image.','virakcloud-woo'), 'error');
                return false;
            }
        }
        return $passed;
    }

    public static function ajax_load(): void {
        check_ajax_referer('vcw_cfg','nonce');
        $what = isset($_POST['what']) ? sanitize_text_field(wp_unslash($_POST['what'])) : '';
        $zone = isset($_POST['zone_id']) ? sanitize_text_field(wp_unslash($_POST['zone_id'])) : '';
        $base = Secure_Store::get_base_url(); $tok = Secure_Store::get_token();
        if (!$base || !$tok) wp_send_json_error(['message'=>'not configured'],400);
        $c = new Rest_Client($base, $tok);
        $settings = Settings_Store::get();
        switch ($what) {
            case 'zones':
                // zones are not persisted per-selection yet; return all
                $r=$c->get_zones();
                wp_send_json_success(['items'=>$r['data']??[]]);
                break;
            case 'nets':
                $r=$c->get_network_offerings($zone);
                $items = $r['data'] ?? [];
                $allow = array_map('strval', $settings['selected_network_ids'] ?? []);
                if (!empty($allow)) {
                    $items = array_values(array_filter($items, function($row) use ($allow){
                        $id = (string)($row['id'] ?? ($row['uuid'] ?? ($row['slug'] ?? '')));
                        return $id && in_array($id, $allow, true);
                    }));
                }
                wp_send_json_success(['items'=>$items]);
                break;
            case 'imgs':
                $r=$c->get_vm_images($zone);
                $items = $r['data'] ?? [];
                $allow = array_map('strval', $settings['selected_image_ids'] ?? []);
                if (!empty($allow)) {
                    $items = array_values(array_filter($items, function($row) use ($allow){
                        $id = (string)($row['id'] ?? ($row['uuid'] ?? ($row['slug'] ?? '')));
                        return $id && in_array($id, $allow, true);
                    }));
                }
                wp_send_json_success(['items'=>$items]);
                break;
        }
        wp_send_json_error(['message'=>'bad request'],400);
    }
}
