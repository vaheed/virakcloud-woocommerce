<?php
namespace VirakCloud\Woo;
defined('ABSPATH') || exit;

class My_Account {
    public static function hooks() {
        add_action('init', [__CLASS__, 'add_endpoint']);
        add_filter('query_vars', function($vars){ $vars[]='cloud-instances'; return $vars; });
        add_filter('woocommerce_account_menu_items', [__CLASS__, 'menu']);
        add_action('woocommerce_account_cloud-instances_endpoint', [__CLASS__, 'render']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'assets']);
        add_action('wp_ajax_vcw_instance_action', [__CLASS__, 'ajax_action']);
    }

    public static function add_endpoint() {
        add_rewrite_endpoint('cloud-instances', EP_ROOT | EP_PAGES);
    }

    public static function menu($items){
        $items = array_slice($items, 0, 1, true) + ['cloud-instances' => __('Cloud Instances','virakcloud-woo')] + $items;
        return $items;
    }

    public static function assets() {
        if (!is_user_logged_in()) return;
        if (!function_exists('is_account_page') || !is_account_page()) return;
        // Inline UI (no modal)
        wp_enqueue_style('vcw-cloud-inline', VCW_URL.'assets/vcw-cloud-inline.css', [], '2.3');
        wp_enqueue_style('vcw-persian', VCW_URL.'assets/vcw-persian.css', [], '1.0');
        wp_enqueue_script('vcw-cloud-inline', VCW_URL.'assets/vcw-cloud-inline.js', [], '2.3', true);
        wp_localize_script('vcw-cloud-inline','vcwInline',[ 'ajax_url'=>admin_url('admin-ajax.php') ]);
    }

    /** Build a minimal list of instances from the customer's orders */
    private static function user_instances( $user_id) {
        $rows = [];
        $orders = wc_get_orders(['customer_id'=>$user_id,'limit'=>-1,'return'=>'ids','status'=>array_keys(wc_get_order_statuses())]);
        foreach ($orders as $oid){
            $o = wc_get_order($oid); if(!$o) continue;
            foreach ($o->get_items() as $it){
                $iid = (string) $it->get_meta('vcw_instance_id', true);
                if (!$iid) continue;
                $rows[$iid] = [
                    'id' => $iid,
                    'order_id' => $oid,
                    'name' => (string) $it->get_name(),
                    'zone_id' => (string) $it->get_meta('vcw_zone_id', true),
                    'status' => (string) $it->get_meta('vcw_provision_status', true),
                ];
            }
        }
        return array_values($rows);
    }

    public static function render() {
        if (!is_user_logged_in()){ echo '<p>'.esc_html__('Please log in.','virakcloud-woo').'</p>'; return; }
        $uid = get_current_user_id();
        $list = self::user_instances($uid);
        echo '<div class="vcw-instances-header">';
        echo '<h2 class="vcw-instances-title">'.esc_html__('Your Cloud Instances','virakcloud-woo').'</h2>';
        echo '<div class="vcw-instances-count">'.count($list).' '.esc_html__('instance(s)','virakcloud-woo').'</div>';
        echo '</div>';
        
        if (empty($list)){ 
            echo '<div class="vcw-no-instances">';
            echo '<div class="vcw-no-instances-icon">☁️</div>';
            echo '<h3>'.esc_html__('No instances yet','virakcloud-woo').'</h3>';
            echo '<p>'.esc_html__('You haven\'t purchased any cloud instances yet. Browse our products to get started!','virakcloud-woo').'</p>';
            echo '<a href="'.esc_url(wc_get_page_permalink('shop')).'" class="vcw-btn vcw-btn--primary">'.esc_html__('Browse Products','virakcloud-woo').'</a>';
            echo '</div>';
            return; 
        }
        
        $nonce = wp_create_nonce('vcw_instances');
        echo '<div class="vcw-cloud vcw-instances-list">';
        foreach ($list as $r){
            $id = esc_html($r['id']); 
            $name = esc_html($r['name'] ?? $id);
            $zone = esc_attr($r['zone_id'] ?? '');
            
            // Get real-time status from API for better button display
            $real_status = '';
            $is_running = false;
            try {
                $status_result = Instance_Manager::get_instance_status($r['zone_id'], $r['id']);
                if (!isset($status_result['error'])) {
                    $real_status = $status_result['status'] ?? $r['status'];
                    $is_running = $status_result['is_running'] ?? false;
                } else {
                    $real_status = $r['status'] ?? '—';
                    $is_running = in_array(strtolower($r['status'] ?? ''), ['running', 'active', 'started', 'up']);
                }
            } catch (Exception $e) {
                $real_status = $r['status'] ?? '—';
                $is_running = in_array(strtolower($r['status'] ?? ''), ['running', 'active', 'started', 'up']);
            }
            
            // Get IP address for display
            $ip_address = '';
            try {
                // Get network information to find IP address
                $networks = Instance_Manager::list_networks($r['zone_id']);
                if (!isset($networks['error'])) {
                    foreach ($networks as $network) {
                        if (isset($network['instance_network'])) {
                            foreach ($network['instance_network'] as $instance_network) {
                                if ($instance_network['instance_id'] === $r['id']) {
                                    $ip_address = $instance_network['ipaddress'] ?? '';
                                    break 2; // Break out of both loops
                                    }
                                }
                            }
                        }
                    }
                    
                    // Fallback to instance details if network method fails
                    if (empty($ip_address)) {
                        $details = Instance_Manager::credentials($r['zone_id'], $r['id']);
                        if (!isset($details['error']) && isset($details['public_ip'])) {
                            $ip_address = $details['public_ip'];
                        }
                    }
            } catch (Exception $e) {
                // IP not available
            }
            
            $instance_title_html = esc_html($name);
            if ($ip_address) {
                $instance_title_html .= ' (IP: <span class="vcw-ip">' . esc_html($ip_address) . '</span> '
                    . '<button type="button" class="vcw-copy-btn" data-copy="' . esc_attr($ip_address) . '"'
                    . ' title="' . esc_attr__( 'Copy IP', 'virakcloud-woo' ) . '">' . esc_html__( 'Copy', 'virakcloud-woo' ) . '</button>)';
            }

            echo '<div class="vcw-card vcw-instance-card" data-vcw-instance data-id="'.$id.'" data-zone="'.$zone.'" data-name="'.esc_attr($name).'" data-nonce="'.esc_attr($nonce).'">';
            
            // Card Header with Status
            echo '<div class="vcw-card-header">';
            echo '<div class="vcw-instance-info">';
            echo '<h3 class="vcw-instance-name">' . $instance_title_html . '</h3>';
            echo '<div class="vcw-instance-meta">';
            echo '<span class="vcw-meta-item"><strong>'.esc_html__('Zone:','virakcloud-woo').'</strong> '.esc_html($r['zone_id']).'</span>';
            echo '</div>';
            echo '</div>';
            echo '<div class="vcw-status-indicator" data-status="'.esc_attr(strtolower($real_status)).'">';
            echo '<span class="vcw-status-dot"></span>';
            echo '<span class="vcw-status-text">'.esc_html($real_status).'</span>';
            echo '</div>';
            echo '</div>';
            
            // Card Body with Actions
            echo '<div class="vcw-card-body">';
            echo '<div class="vcw-actions-grid">';
            
            // Primary Actions (Power Management)
            echo '<div class="vcw-action-group vcw-primary-actions">';
            echo '<h4 class="vcw-action-group-title">'.esc_html__('Power Management','virakcloud-woo').'</h4>';
            echo '<div class="vcw-action-buttons">';
            if ($is_running) {
                echo '<button class="vcw-btn vcw-act vcw-btn--warning vcw-btn--icon" data-act="stop" title="'.esc_attr__('Stop Instance','virakcloud-woo').'">';
                echo '<span class="vcw-btn-icon">⏹️</span> '.esc_html__('Stop','virakcloud-woo');
                echo '</button>';
                echo '<button class="vcw-btn vcw-act vcw-btn--warning vcw-btn--icon" data-act="reboot" title="'.esc_attr__('Reboot Instance','virakcloud-woo').'">';
                echo '<span class="vcw-btn-icon">🔄</span> '.esc_html__('Reboot','virakcloud-woo');
                echo '</button>';
            } else {
                echo '<button class="vcw-btn vcw-act vcw-btn--success vcw-btn--icon" data-act="start" title="'.esc_attr__('Start Instance','virakcloud-woo').'">';
                echo '<span class="vcw-btn-icon">▶️</span> '.esc_html__('Start','virakcloud-woo');
                echo '</button>';
            }
            echo '</div>';
            echo '</div>';
            
            // Secondary Actions (Access & Management)
            echo '<div class="vcw-action-group vcw-secondary-actions">';
            echo '<h4 class="vcw-action-group-title">'.esc_html__('Access & Management','virakcloud-woo').'</h4>';
            echo '<div class="vcw-action-buttons">';
            echo '<button class="vcw-btn vcw-act vcw-btn--primary vcw-btn--icon" data-act="credentials" title="'.esc_attr__('View Credentials','virakcloud-woo').'">';
            echo '<span class="vcw-btn-icon">🔑</span> '.esc_html__('Credentials','virakcloud-woo');
            echo '</button>';
            echo '<button class="vcw-btn vcw-act vcw-btn--info vcw-btn--icon" data-act="vnc" title="'.esc_attr__('Open Console','virakcloud-woo').'">';
            echo '<span class="vcw-btn-icon">🖥️</span> '.esc_html__('Console','virakcloud-woo');
            echo '</button>';
            echo '</div>';
            echo '</div>';
            
                            // Advanced Actions
                echo '<div class="vcw-action-group vcw-advanced-actions">';
                echo '<h4 class="vcw-action-group-title">'.esc_html__('Advanced','virakcloud-woo').'</h4>';
                echo '<div class="vcw-action-buttons">';
                echo '<button class="vcw-btn vcw-act vcw-btn--secondary vcw-btn--icon" data-act="rebuild_list_images" title="'.esc_attr__('Rebuild with New Image','virakcloud-woo').'">';
                echo '<span class="vcw-btn-icon">🔧</span> '.esc_html__('Rebuild','virakcloud-woo');
                echo '</button>';
                echo '<button class="vcw-btn vcw-act vcw-btn--secondary vcw-btn--icon" data-act="snapshot_list" title="'.esc_attr__('Manage Snapshots','virakcloud-woo').'">';
                echo '<span class="vcw-btn-icon">📸</span> '.esc_html__('Snapshots','virakcloud-woo');
                echo '</button>';
                echo '<button class="vcw-btn vcw-act vcw-btn--secondary vcw-btn--icon" data-act="details" title="'.esc_attr__('Show Instance Details','virakcloud-woo').'">';
                echo '<span class="vcw-btn-icon">📊</span> '.esc_html__('Details','virakcloud-woo');
                echo '</button>';
                echo '<button class="vcw-btn vcw-act vcw-btn--secondary vcw-btn--icon" data-act="refresh_status" title="'.esc_attr__('Refresh Status Only','virakcloud-woo').'">';
                echo '<span class="vcw-btn-icon">🔄</span> '.esc_html__('Refresh','virakcloud-woo');
                echo '</button>';
                echo '</div>';
                echo '</div>';
            
            echo '</div>'; // End actions-grid
            echo '<div class="vcw-inline"></div>';
            echo '</div></div>';
        }
        echo '</div>';
    }

    public static function ajax_action() {
        if (!is_user_logged_in()) wp_send_json(array('error'=>'auth'), 401);
        check_ajax_referer('vcw_instances');
        $act = isset($_POST['act']) ? sanitize_text_field(wp_unslash($_POST['act'])) : '';
        $id  = isset($_POST['id']) ? sanitize_text_field(wp_unslash($_POST['id'])) : '';
        $zone = isset($_POST['zone']) ? sanitize_text_field(wp_unslash($_POST['zone'])) : '';
        $uid = get_current_user_id();
        if (!$id || !Instance_Manager::user_owns($id, $uid)) wp_send_json(array('error'=>'forbidden'), 403);
        if (!$zone) wp_send_json(array('error'=>'missing zone'), 400);
        
        switch ($act) {
            case 'snapshot_revert':
                $sid = isset($_POST['snapshot_id']) ? sanitize_text_field(wp_unslash($_POST['snapshot_id'])) : '';
                $r = Instance_Manager::revert_snapshot($zone,$id,$sid); break;
            case 'snapshot_delete':
                $sid = isset($_POST['snapshot_id']) ? sanitize_text_field(wp_unslash($_POST['snapshot_id'])) : '';
                $r = Instance_Manager::delete_snapshot($zone,$id,$sid); break;
            case 'snapshot_list':
                // Retrieve list of snapshots and decode the JSON body for easy consumption.
                $r = Instance_Manager::list_snapshots($zone,$id);
                if (!isset($r['error']) && isset($r['body_raw'])) {
                    $json = json_decode($r['body_raw'], true);
                    if (is_array($json) && isset($json['data'])) {
                        $r['snapshots'] = is_array($json['data']) ? $json['data'] : [];
                    } else {
                        $r['snapshots'] = [];
                    }
                }
                break;
            case 'snapshot_create':
                $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : ('snap-'.date('Ymd-His'));
                $r = Instance_Manager::snapshot($zone,$id,$name); break;
            case 'credentials': $r = Instance_Manager::credentials($zone,$id); break;
            case 'start': $r=Instance_Manager::start($zone,$id); break;
            case 'stop': $r=Instance_Manager::stop($zone,$id); break;
            case 'reboot': $r=Instance_Manager::reboot($zone,$id); break;
            case 'details': $r=Instance_Manager::details($zone,$id); break;
            case 'status': $r=Instance_Manager::get_instance_status($zone,$id); break;
                            case 'vnc': $r=Instance_Manager::get_vnc_url($zone,$id); break;
                case 'refresh_status':
                    $r = Instance_Manager::refresh_instance_status($zone, $id);
                    break;
                case 'rebuild_list_images':
                    // Only show images that were selected in the setup wizard
                    $settings = Settings_Store::get();
                    $selected_image_ids = $settings['selected_image_ids'] ?? [];
                    
                    if (empty($selected_image_ids)) {
                        $r = ['error' => 'No VM images configured. Please complete the setup wizard first.'];
                    } else {
                        $images = Instance_Manager::list_vm_images($zone);
                        if (isset($images['error'])) {
                            $r = ['error' => $images['error']];
                        } else {
                            $all_images = $images['data'] ?? [];
                            // Filter to only show selected images
                            $filtered_images = array_filter($all_images, function($img) use ($selected_image_ids) {
                                $img_id = $img['id'] ?? $img['uuid'] ?? $img['slug'] ?? '';
                                return in_array($img_id, $selected_image_ids);
                            });
                            
                            $r = [
                                'code' => 200,
                                'images' => array_values($filtered_images),
                                'body_pretty' => null,
                                'body_raw' => null,
                            ];
                        }
                    }
                    break;
                case 'rebuild':
                    $image_id = isset($_POST['vm_image_id']) ? sanitize_text_field(wp_unslash($_POST['vm_image_id'])) : '';
                    $r = Instance_Manager::rebuild_instance($zone, $id, $image_id);
                    break;
                default: wp_send_json(array('error'=>'bad act'), 400);
        }
        
        // Return proper JSON response format
        if (isset($r['error'])) {
            wp_send_json_error(['message' => $r['error']]);
        } else {
            wp_send_json_success($r);
        }
    }
}