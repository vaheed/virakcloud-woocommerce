<?php
namespace VirakCloud\Woo;
defined('ABSPATH') || exit;

class Order_Provisioner {
    public static function hooks() {
        // Primary transitions
        add_action('woocommerce_order_status_processing', [__CLASS__, 'maybe_provision']);
        add_action('woocommerce_order_status_completed', [__CLASS__, 'maybe_provision']);
        // Fallbacks (some gateways never fire the above immediately)
        add_action('woocommerce_payment_complete', [__CLASS__, 'maybe_provision']);
        add_action('woocommerce_thankyou', [__CLASS__, 'maybe_provision']);
        
        // Order cancellation - automatically delete instances
        add_action('woocommerce_order_status_cancelled', [__CLASS__, 'maybe_delete_instances']);
        add_action('woocommerce_order_status_refunded', [__CLASS__, 'maybe_delete_instances']);
        add_action('woocommerce_order_status_failed', [__CLASS__, 'maybe_delete_instances']);
    }

    public static function maybe_provision($order_id) {
        $order = wc_get_order($order_id);
        if(!$order){ Logger::log('error','Provision skipped: order not found', ['order'=>$order_id]); return; }

        foreach ($order->get_items() as $item){
            $item_id = $item->get_id();
            $already = (string) $item->get_meta('vcw_instance_id', true);
            if ($already){
                Logger::log('info','Provision skipped: already has instance id', ['order'=>$order_id,'item'=>$item_id,'instance_id'=>$already]);
                continue;
            }

            $svc = (string) $item->get_meta('vcw_service_offering_id', true);
            $zone = (string) $item->get_meta('vcw_zone_id', true);
            $net  = (string) $item->get_meta('vcw_network_offering_id', true);
            $img  = (string) $item->get_meta('vcw_image_id', true);

            if (!$svc || !$zone || !$net || !$img){
                Logger::log('warning','Provision skipped: missing config', ['order'=>$order_id,'item'=>$item_id,'svc'=>$svc,'zone'=>$zone,'net'=>$net,'img'=>$img]);
                continue;
            }

            try {
                $client = new Rest_Client(Secure_Store::get_base_url(), Secure_Store::get_token());
                $name = 'wc-' . $order_id . '-' . $item_id;
                $res = $client->provision_instance($zone, $svc, $net, $img, $name);

                // Try to resolve instance id
                $iid = Instance_Manager::extract_id($res);
                if (!$iid) {
                    try {
                        $list = $client->get_instances($zone);
                        $rows = $list['data'] ?? [];
                        foreach ($rows as $row){
                            $nm = (string)($row['name'] ?? '');
                            $rid= (string)($row['id'] ?? ($row['uuid'] ?? ''));
                            if ($nm === $name && $rid){ $iid = $rid; break; }
                        }
                    } catch (\Throwable $x) {
                        // ignored; will stay blank
                    }
                }

                if ($iid) { $item->add_meta_data('vcw_instance_id', (string)$iid, true); }
                $item->add_meta_data('vcw_provision_status', (string)($res['code']??''), true);
                $item->save();

                Logger::log('info','Provision requested', ['order'=>$order_id,'item'=>$item_id,'instance_id'=>$iid]);

            } catch (\Throwable $e){
                Logger::log('error','Provision failed', ['order'=>$order_id,'item'=>$item_id,'error'=>$e->getMessage()]);
            }
        }
    }

    public static function maybe_delete_instances($order_id) {
        $order = wc_get_order($order_id);
        if(!$order){ 
            Logger::log('error','Delete instances skipped: order not found', ['order'=>$order_id]); 
            return; 
        }

        foreach ($order->get_items() as $item){
            $item_id = $item->get_id();
            $instance_id = (string) $item->get_meta('vcw_instance_id', true);
            $zone_id = (string) $item->get_meta('vcw_zone_id', true);
            
            if (!$instance_id || !$zone_id){
                Logger::log('info','Delete instances skipped: missing instance or zone', [
                    'order'=>$order_id,
                    'item'=>$item_id,
                    'instance_id'=>$instance_id,
                    'zone_id'=>$zone_id
                ]);
                continue;
            }

            try {
                // Get instance name for deletion
                $instance_name = 'wc-' . $order_id . '-' . $item_id;
                
                // Attempt to delete the instance
                $result = Instance_Manager::delete($zone_id, $instance_id, $instance_name);
                
                if (isset($result['code']) && $result['code'] >= 200 && $result['code'] < 300) {
                    // Successfully deleted - remove instance ID from order item
                    $item->delete_meta_data('vcw_instance_id');
                    $item->add_meta_data('vcw_deletion_status', 'deleted', true);
                    $item->save();
                    
                    Logger::log('info','Instance deleted due to order cancellation', [
                        'order'=>$order_id,
                        'item'=>$item_id,
                        'instance_id'=>$instance_id,
                        'zone_id'=>$zone_id
                    ]);
                } else {
                    // Deletion failed
                    $error = $result['error'] ?? 'Unknown error';
                    $item->add_meta_data('vcw_deletion_status', 'failed', true);
                    $item->add_meta_data('vcw_deletion_error', $error, true);
                    $item->save();
                    
                    Logger::log('error','Instance deletion failed', [
                        'order'=>$order_id,
                        'item'=>$item_id,
                        'instance_id'=>$instance_id,
                        'zone_id'=>$zone_id,
                        'error'=>$error
                    ]);
                }

            } catch (\Throwable $e){
                Logger::log('error','Instance deletion exception', [
                    'order'=>$order_id,
                    'item'=>$item_id,
                    'instance_id'=>$instance_id,
                    'zone_id'=>$zone_id,
                    'error'=>$e->getMessage()
                ]);
            }
        }
    }
}
