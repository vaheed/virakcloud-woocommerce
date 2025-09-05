<?php
namespace VirakCloud\Woo;
defined('ABSPATH') || exit;

class Admin_UI {
    public function hooks(): void {
        add_action('admin_menu', [$this, 'menu']);
        add_action('wp_ajax_vcw_instance_action', [$this, 'ajax_instance_action']);
    }

    public function menu(): void {
        $cap = 'manage_options';
        add_menu_page(__('Virak Cloud','virakcloud-woo'), __('Virak Cloud','virakcloud-woo'), $cap, 'virakcloud-woo-user-instances', [$this,'page_user_instances'], 'dashicons-cloud', 56);
        add_submenu_page('virakcloud-woo-user-instances', __('User Instances','virakcloud-woo'), __('User Instances','virakcloud-woo'), $cap, 'virakcloud-woo-user-instances', [$this,'page_user_instances']);
        add_submenu_page('virakcloud-woo-user-instances', __('Setup Wizard','virakcloud-woo'), __('Setup Wizard','virakcloud-woo'), $cap, 'virakcloud-woo-wizard', [$this,'page_setup_wizard']);
    }
    
    public function page_setup_wizard(): void {
        if (!current_user_can('manage_options')) wp_die(esc_html__('No permission.','virakcloud-woo'));
        
        // Get the Setup_Wizard instance and render it
        \VirakCloud\Woo\Setup_Wizard::render();
    }






    /* ---------- AJAX ---------- */





    public function page_user_instances() {
            if (!current_user_can('manage_options')) wp_die(esc_html__('No permission.','virakcloud-woo'));
                    wp_enqueue_style('vcw-persian', VCW_URL.'assets/vcw-persian.css', [], '1.0');
            wp_enqueue_style('vcw-cloud-inline', VCW_URL.'assets/vcw-cloud-inline.css', [], '2.3');
            wp_enqueue_script('vcw-cloud-inline', VCW_URL.'assets/vcw-cloud-inline.js', [], '2.3', true);
            wp_localize_script('vcw-cloud-inline','vcwInline',[ 'ajax_url'=>admin_url('admin-ajax.php') ]);
            $rows = [];
            $orders = wc_get_orders(['limit'=>-1,'return'=>'ids','status'=>array_keys(wc_get_order_statuses())]);
            foreach ($orders as $oid){
                $o = wc_get_order($oid); if(!$o) continue;
                $uid = $o->get_user_id();
                $user = $uid ? get_user_by('id',$uid) : null;
                $user_name = $user ? $user->display_name : __('Guest','virakcloud-woo');
                foreach ($o->get_items() as $it){
                    $iid = (string) $it->get_meta('vcw_instance_id', true);
                    if (!$iid) continue;
                    $rows[] = [
                        'user' => $user_name,
                        'user_id' => $uid,
                        'order_id' => $oid,
                        'name' => (string)$it->get_name(),
                        'instance_id' => $iid,
                        'zone_id' => (string)$it->get_meta('vcw_zone_id', true),
                        'status' => (string)$it->get_meta('vcw_provision_status', true),
                    ];
                }
            }
            echo '<div class="wrap"><h1>'.esc_html__('Virak Cloud — User Instances','virakcloud-woo').'</h1>';
            $nonce = wp_create_nonce('vcw_instances');
            echo '<div class="vcw-cloud">';
            foreach ($rows as $r){
                $order_link = admin_url('post.php?post='.$r['order_id'].'&action=edit');
                $name = esc_attr($r['name']);
                
                // Get real-time status from API for better button display
                $real_status = '';
                $is_running = false;
                try {
                    $status_result = Instance_Manager::get_instance_status($r['zone_id'], $r['instance_id']);
                    if (!isset($status_result['error'])) {
                        $real_status = $status_result['status'] ?? $r['status'];
                        $is_running = $status_result['is_running'] ?? false;
                    } else {
                        $real_status = $r['status'];
                        $is_running = in_array(strtolower($r['status']), ['running', 'active', 'started', 'up']);
                    }
                } catch (Exception $e) {
                    $real_status = $r['status'];
                    $is_running = in_array(strtolower($r['status']), ['running', 'active', 'started', 'up']);
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
                                    if ($instance_network['instance_id'] === $r['instance_id']) {
                                        $ip_address = $instance_network['ipaddress'] ?? '';
                                        break 2; // Break out of both loops
                                    }
                                }
                            }
                        }
                    }
                    
                    // Fallback to instance details if network method fails
                    if (empty($ip_address)) {
                        $details = Instance_Manager::details($r['zone_id'], $r['instance_id']);
                        if (!isset($details['error']) && isset($details['public_ip'])) {
                            $ip_address = $details['public_ip'];
                        }
                    }
                } catch (Exception $e) {
                    // IP not available
                }
                
                $instance_title_html = esc_html($r['name']);
                if ($ip_address) {
                    $instance_title_html .= ' (IP: <span class="vcw-ip">' . esc_html($ip_address) . '</span> '
                        . '<button type="button" class="vcw-copy-btn" data-copy="' . esc_attr($ip_address) . '"'
                        . ' title="' . esc_attr__( 'Copy IP', 'virakcloud-woo' ) . '">'
                        . esc_html__( 'Copy', 'virakcloud-woo' )
                        . '</button>)';
                }

                echo '<div class="vcw-card vcw-instance-card" data-vcw-instance data-id="'.esc_attr($r['instance_id']).'" data-zone="'.esc_attr($r['zone_id']).'" data-name="'.$name.'" data-nonce="'.esc_attr($nonce).'">';
                
                // Card Header with Status
                echo '<div class="vcw-card-header">';
                echo '<div class="vcw-instance-info">';
                echo '<h3 class="vcw-instance-name">' . $instance_title_html . '</h3>';
                echo '<div class="vcw-instance-meta">';
                echo '<span class="vcw-meta-item"><strong>'.esc_html__('User:','virakcloud-woo').'</strong> '.esc_html($r['user']).'</span>';
                echo '<span class="vcw-meta-item"><strong>'.esc_html__('Order:','virakcloud-woo').'</strong> <a href="'.esc_url($order_link).'">#'.intval($r['order_id']).'</a></span>';
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
                echo '<button class="vcw-btn vcw-act vcw-btn--danger vcw-btn--icon" data-act="delete" title="'.esc_attr__('Delete Instance','virakcloud-woo').'">';
                echo '<span class="vcw-btn-icon">🗑️</span> '.esc_html__('Delete','virakcloud-woo');
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
            if (empty($rows)) echo '<p>'.esc_html__('No instances yet.','virakcloud-woo').'</p>';
            echo '</div></div>';
        }
    
        /* ---------- AJAX ---------- */
    
    
    
    
        public function ajax_instance_action() {
            if (!current_user_can('manage_options')) wp_send_json(array('error'=>'forbidden'), 403);
            check_ajax_referer('vcw_instances');
            $act = isset($_POST['act']) ? sanitize_text_field(wp_unslash($_POST['act'])) : '';
            $id  = isset($_POST['id']) ? sanitize_text_field(wp_unslash($_POST['id'])) : '';
            $zone = isset($_POST['zone']) ? sanitize_text_field(wp_unslash($_POST['zone'])) : '';
            if (!$id || !$zone) wp_send_json(array('error'=>'missing parameters'), 400);
            
            switch ($act) {
                case 'snapshot_revert':
                    $sid = isset($_POST['snapshot_id']) ? sanitize_text_field(wp_unslash($_POST['snapshot_id'])) : '';
                    $r = Instance_Manager::revert_snapshot($zone,$id,$sid); break;
                case 'snapshot_delete':
                    $sid = isset($_POST['snapshot_id']) ? sanitize_text_field(wp_unslash($_POST['snapshot_id'])) : '';
                    $r = Instance_Manager::delete_snapshot($zone,$id,$sid); break;
                case 'snapshot_list':
                    // Retrieve list of snapshots for the instance and decode the body
                    $r = Instance_Manager::list_snapshots($zone,$id);
                    // If the API returned a JSON body, decode it into an array of snapshots so
                    // the frontend can display them easily. The Instance_Manager
                    // formats the response as {"data": [...]}, so extract that.
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
                case 'delete': 
                    $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : null;
                    $r=Instance_Manager::delete($zone,$id,$name); 
                    break;
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
                // Expect a VM image ID in the request payload
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