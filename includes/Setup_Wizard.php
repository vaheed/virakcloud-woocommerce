<?php
namespace VirakCloud\Woo;
defined('ABSPATH') || exit;

class Setup_Wizard {
    private static $current_step = 1;
    private static $total_steps = 5;
    
    public static function hooks(): void {
        add_action('wp_ajax_vcw_wizard_step', [__CLASS__, 'ajax_step']);
        add_action('wp_ajax_vcw_wizard_save', [__CLASS__, 'ajax_save']);
        add_action('wp_ajax_vcw_wizard_get_state', [__CLASS__, 'ajax_get_state']);
        add_action('wp_ajax_vcw_wizard_test_connection', [__CLASS__, 'ajax_test_connection']);
        add_action('wp_ajax_vcw_wizard_sync_products', [__CLASS__, 'ajax_sync_products']);
    }
    
    public static function render(): void {
        if (!current_user_can('manage_options')) wp_die(esc_html__('No permission.','virakcloud-woo'));
        
        $current_step = isset($_GET['step']) ? intval($_GET['step']) : 1;
        $current_step = max(1, min($current_step, self::$total_steps));
        
        wp_enqueue_style('vcw-wizard', VCW_URL.'assets/vcw-wizard.css', [], '1.0');
        wp_enqueue_style('vcw-persian', VCW_URL.'assets/vcw-persian.css', [], '1.0');
        wp_enqueue_script('vcw-wizard', VCW_URL.'assets/vcw-wizard.js', [], '1.0', true);
        wp_localize_script('vcw-wizard', 'vcwWizard', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vcw_wizard_nonce'),
            'current_step' => $current_step,
            'total_steps' => self::$total_steps
        ]);
        
        ?>
        <div class="wrap vcw-wizard">
            <h1><?php echo esc_html__('Virak Cloud Setup Wizard','virakcloud-woo'); ?></h1>
            
            <!-- Progress Bar -->
            <div class="vcw-wizard-progress">
                <div class="vcw-wizard-steps">
                    <div class="vcw-step <?php echo $current_step >= 1 ? 'active' : ''; ?> <?php echo $current_step > 1 ? 'completed' : ''; ?>">
                        <span class="vcw-step-number">1</span>
                        <span class="vcw-step-title"><?php echo esc_html__('API Connection','virakcloud-woo'); ?></span>
                    </div>
                    <div class="vcw-step <?php echo $current_step >= 2 ? 'active' : ''; ?> <?php echo $current_step > 2 ? 'completed' : ''; ?>">
                        <span class="vcw-step-number">2</span>
                        <span class="vcw-step-title"><?php echo esc_html__('Zone Selection','virakcloud-woo'); ?></span>
                    </div>
                    <div class="vcw-step <?php echo $current_step >= 3 ? 'active' : ''; ?> <?php echo $current_step > 3 ? 'completed' : ''; ?>">
                        <span class="vcw-step-number">3</span>
                        <span class="vcw-step-title"><?php echo esc_html__('Service Selection','virakcloud-woo'); ?></span>
                    </div>
                    <div class="vcw-step <?php echo $current_step >= 4 ? 'active' : ''; ?> <?php echo $current_step > 4 ? 'completed' : ''; ?>">
                        <span class="vcw-step-number">4</span>
                        <span class="vcw-step-title"><?php echo esc_html__('Product Sync','virakcloud-woo'); ?></span>
                    </div>
                    <div class="vcw-step <?php echo $current_step >= 5 ? 'active' : ''; ?>">
                        <span class="vcw-step-number">5</span>
                        <span class="vcw-step-title"><?php echo esc_html__('Finish Setup','virakcloud-woo'); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Step Content -->
            <div class="vcw-wizard-content">
                <?php self::render_step($current_step); ?>
            </div>
            
            <!-- Navigation -->
            <div class="vcw-wizard-navigation">
                <?php if ($current_step > 1): ?>
                    <a href="?page=virakcloud-woo-wizard&step=<?php echo $current_step - 1; ?>" class="button button-secondary">
                        ← <?php echo esc_html__('Previous','virakcloud-woo'); ?>
                    </a>
                <?php endif; ?>
                
                <?php if ($current_step < self::$total_steps): ?>
                    <button type="button" class="button button-primary vcw-wizard-next" data-step="<?php echo $current_step; ?>">
                        <?php echo esc_html__('Next','virakcloud-woo'); ?> →
                    </button>
                <?php else: ?>
                    <a href="<?php echo admin_url('admin.php?page=virakcloud-woo'); ?>" class="button button-primary">
                        <?php echo esc_html__('Go to Dashboard','virakcloud-woo'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    private static function render_step($step): void {
        switch ($step) {
            case 1:
                self::render_api_connection_step();
                break;
            case 2:
                self::render_zone_selection_step();
                break;
            case 3:
                self::render_service_selection_step();
                break;
            case 4:
                self::render_product_sync_step();
                break;
            case 5:
                self::render_finish_step();
                break;
        }
    }
    
    private static function render_api_connection_step(): void {
        $saved_base = Secure_Store::get_base_url();
        $saved_token = Secure_Store::get_token();
        ?>
        <div class="vcw-wizard-step" data-step="1">
            <h2><?php echo esc_html__('Step 1: Connect to Virak Cloud API','virakcloud-woo'); ?></h2>
            <p><?php echo esc_html__('Enter your Virak Cloud API credentials to connect the plugin to your cloud infrastructure.','virakcloud-woo'); ?></p>
            
            <div class="vcw-wizard-form">
                <div class="vcw-form-row">
                    <label for="wizard_base_url"><?php echo esc_html__('API Base URL','virakcloud-woo'); ?></label>
                    <input type="url" id="wizard_base_url" name="base_url" value="<?php echo esc_attr($saved_base); ?>" 
                           placeholder="https://public-api.virakcloud.com" required>
                    <p class="vcw-form-help"><?php echo esc_html__('The base URL for your Virak Cloud API endpoint.','virakcloud-woo'); ?></p>
                </div>
                
                <div class="vcw-form-row">
                    <label for="wizard_api_token"><?php echo esc_html__('API Token','virakcloud-woo'); ?></label>
                    <input type="password" id="wizard_api_token" name="api_token" 
                           value="<?php echo esc_attr($saved_token); ?>" required>
                    <p class="vcw-form-help"><?php echo esc_html__('Your Virak Cloud API authentication token.','virakcloud-woo'); ?></p>
                </div>
                
                <div class="vcw-form-actions">
                    <button type="button" class="button button-secondary vcw-wizard-test-connection">
                        <?php echo esc_html__('Test Connection','virakcloud-woo'); ?>
                    </button>
                    <button type="button" class="button button-primary vcw-wizard-save-step" data-step="1">
                        <?php echo esc_html__('Save & Continue','virakcloud-woo'); ?>
                    </button>
                </div>
                
                <div id="wizard-connection-result" class="vcw-wizard-result" style="display: none;"></div>
            </div>
        </div>
        <?php
    }
    
    private static function render_zone_selection_step(): void {
        $settings = Settings_Store::get();
        $selected_zone = $settings['zone_id'] ?? '';
        ?>
        <div class="vcw-wizard-step" data-step="2">
            <h2><?php echo esc_html__('Step 2: Select Your Datacenter Zone','virakcloud-woo'); ?></h2>
            <p><?php echo esc_html__('Choose the datacenter zone where you want to provision cloud instances. This will determine the location of your customers\' VMs.','virakcloud-woo'); ?></p>
            
            <div class="vcw-wizard-form">
                <div class="vcw-form-row">
                    <label for="wizard_zone"><?php echo esc_html__('Datacenter Zone','virakcloud-woo'); ?></label>
                    <select id="wizard_zone" name="zone_id" required>
                        <option value=""><?php echo esc_html__('Loading zones...','virakcloud-woo'); ?></option>
                    </select>
                    <p class="vcw-form-help"><?php echo esc_html__('Select the zone where your cloud infrastructure is located.','virakcloud-woo'); ?></p>
                </div>
                
                <div class="vcw-form-actions">
                    <button type="button" class="button button-primary vcw-wizard-save-step" data-step="2">
                        <?php echo esc_html__('Save & Continue','virakcloud-woo'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
    
    private static function render_service_selection_step(): void {
        $settings = Settings_Store::get();
        ?>
        <div class="vcw-wizard-step" data-step="3">
            <h2><?php echo esc_html__('Step 3: Configure Service Offerings','virakcloud-woo'); ?></h2>
            <p><?php echo esc_html__('Select which service offerings, network configurations, and VM images you want to make available to your customers.','virakcloud-woo'); ?></p>
            
            <!-- Selection Summary -->
            <div class="vcw-selection-summary">
                <div class="vcw-summary-item">
                    <span class="vcw-summary-label"><?php echo esc_html__('Service Offerings:','virakcloud-woo'); ?></span>
                    <span class="vcw-summary-count" id="service-count">0</span>
                </div>
                <div class="vcw-summary-item">
                    <span class="vcw-summary-label"><?php echo esc_html__('Network Offerings:','virakcloud-woo'); ?></span>
                    <span class="vcw-summary-count" id="network-count">0</span>
                </div>
                <div class="vcw-summary-item">
                    <span class="vcw-summary-label"><?php echo esc_html__('VM Images:','virakcloud-woo'); ?></span>
                    <span class="vcw-summary-count" id="image-count">0</span>
                </div>
            </div>
            
            <div class="vcw-wizard-form">
                <div class="vcw-form-row">
                    <label><?php echo esc_html__('Service Offerings (VM Plans)','virakcloud-woo'); ?> <span class="vcw-required">*</span></label>
                    <div class="vcw-selection-controls">
                        <input type="text" id="wizard-service-search" placeholder="<?php echo esc_attr__('Search services...','virakcloud-woo'); ?>" class="vcw-search-input">
                        <button type="button" class="button button-secondary vcw-wizard-select-all" data-type="service"><?php echo esc_html__('Select All','virakcloud-woo'); ?></button>
                        <button type="button" class="button button-secondary vcw-wizard-clear-all" data-type="service"><?php echo esc_html__('Clear All','virakcloud-woo'); ?></button>
                    </div>
                    <div id="wizard-service-offerings" class="vcw-wizard-selection vcw-selection-list">
                        <p><?php echo esc_html__('Loading available service offerings...','virakcloud-woo'); ?></p>
                    </div>
                    <p class="vcw-form-help"><?php echo esc_html__('Select at least one VM plan to offer to customers. Use search to filter options.','virakcloud-woo'); ?></p>
                    <div class="vcw-validation-error" id="service-validation-error" style="display: none;">
                        <?php echo esc_html__('Please select at least one service offering.','virakcloud-woo'); ?>
                    </div>
                </div>
                
                <div class="vcw-form-row">
                    <label><?php echo esc_html__('Network Offerings','virakcloud-woo'); ?> <span class="vcw-required">*</span></label>
                    <div class="vcw-selection-controls">
                        <input type="text" id="wizard-network-search" placeholder="<?php echo esc_attr__('Search networks...','virakcloud-woo'); ?>" class="vcw-search-input">
                        <button type="button" class="button button-secondary vcw-wizard-select-all" data-type="network"><?php echo esc_html__('Select All','virakcloud-woo'); ?></button>
                        <button type="button" class="button button-secondary vcw-wizard-clear-all" data-type="network"><?php echo esc_html__('Clear All','virakcloud-woo'); ?></button>
                    </div>
                    <div id="wizard-network-offerings" class="vcw-wizard-selection vcw-selection-list">
                        <p><?php echo esc_html__('Loading available network offerings...','virakcloud-woo'); ?></p>
                    </div>
                    <p class="vcw-form-help"><?php echo esc_html__('Select at least one network configuration for your VMs. Use search to filter options.','virakcloud-woo'); ?></p>
                    <div class="vcw-validation-error" id="network-validation-error" style="display: none;">
                        <?php echo esc_html__('Please select at least one network offering.','virakcloud-woo'); ?>
                    </div>
                </div>
                
                <div class="vcw-form-row">
                    <label><?php echo esc_html__('VM Images (Operating Systems)','virakcloud-woo'); ?> <span class="vcw-required">*</span></label>
                    <div class="vcw-selection-controls">
                        <input type="text" id="wizard-image-search" placeholder="<?php echo esc_attr__('Search images...','virakcloud-woo'); ?>" class="vcw-search-input">
                        <button type="button" class="button button-secondary vcw-wizard-select-all" data-type="image"><?php echo esc_html__('Select All','virakcloud-woo'); ?></button>
                        <button type="button" class="button button-secondary vcw-wizard-clear-all" data-type="image"><?php echo esc_html__('Clear All','virakcloud-woo'); ?></button>
                    </div>
                    <div id="wizard-vm-images" class="vcw-wizard-selection vcw-selection-list">
                        <p><?php echo esc_html__('Loading available VM images...','virakcloud-woo'); ?></p>
                    </div>
                    <p class="vcw-form-help"><?php echo esc_html__('Select at least one operating system image for your VMs. Use search to filter options.','virakcloud-woo'); ?></p>
                    <div class="vcw-validation-error" id="image-validation-error" style="display: none;">
                        <?php echo esc_html__('Please select at least one VM image.','virakcloud-woo'); ?>
                    </div>
                </div>
                
                <div class="vcw-form-actions">
                    <button type="button" class="button button-primary vcw-wizard-save-step" data-step="3">
                        <?php echo esc_html__('Save & Continue','virakcloud-woo'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
    
    private static function render_product_sync_step(): void {
        ?>
        <div class="vcw-wizard-step" data-step="4">
            <h2><?php echo esc_html__('Step 4: Sync Products to WooCommerce','virakcloud-woo'); ?></h2>
            <p><?php echo esc_html__('Create WooCommerce products for your selected cloud services. This will allow customers to purchase and configure VMs through your store.','virakcloud-woo'); ?></p>
            
            <div class="vcw-wizard-form">
                <div class="vcw-form-row">
                    <label><?php echo esc_html__('Product Configuration','virakcloud-woo'); ?></label>
                    <div class="vcw-wizard-product-config">
                        <p><strong><?php echo esc_html__('Selected Services:','virakcloud-woo'); ?></strong> <span id="wizard-selected-count">0</span></p>
                        <p><strong><?php echo esc_html__('Products to Create:','virakcloud-woo'); ?></strong> <span id="wizard-product-count">0</span></p>
                    </div>
                </div>
                
                <div class="vcw-form-row">
                    <label for="wizard_product_prefix"><?php echo esc_html__('Product Name Prefix','virakcloud-woo'); ?></label>
                    <input type="text" id="wizard_product_prefix" name="product_prefix" value="Cloud VM - " placeholder="Cloud VM - ">
                    <p class="vcw-form-help"><?php echo esc_html__('Prefix to add before each product name (e.g., "Cloud VM - ").','virakcloud-woo'); ?></p>
                </div>
                
                <div class="vcw-form-row">
                    <label for="wizard_auto_publish"><?php echo esc_html__('Auto-publish Products','virakcloud-woo'); ?></label>
                    <input type="checkbox" id="wizard_auto_publish" name="auto_publish" checked>
                    <span class="vcw-form-help"><?php echo esc_html__('Automatically publish products to your store catalog.','virakcloud-woo'); ?></span>
                </div>
                
                <div class="vcw-form-actions">
                    <button type="button" class="button button-secondary vcw-wizard-sync-preview">
                        <?php echo esc_html__('Preview Products','virakcloud-woo'); ?>
                    </button>
                    <button type="button" class="button button-primary vcw-wizard-sync-products">
                        <?php echo esc_html__('Create Products','virakcloud-woo'); ?>
                    </button>
                </div>
                
                <div id="wizard-sync-result" class="vcw-wizard-result" style="display: none;"></div>
            </div>
        </div>
        <?php
    }
    
    private static function render_finish_step(): void {
        ?>
        <div class="vcw-wizard-step" data-step="5">
            <h2><?php echo esc_html__('Step 5: Setup Complete! 🎉','virakcloud-woo'); ?></h2>
            <p><?php echo esc_html__('Congratulations! Your Virak Cloud WooCommerce plugin is now configured and ready to use.','virakcloud-woo'); ?></p>
            
            <div class="vcw-wizard-success">
                <div class="vcw-success-icon">✅</div>
                <h3><?php echo esc_html__('What\'s Next?','virakcloud-woo'); ?></h3>
                
                <div class="vcw-next-steps">
                    <div class="vcw-next-step">
                        <h4><?php echo esc_html__('1. Review Your Products','virakcloud-woo'); ?></h4>
                        <p><?php echo esc_html__('Check your WooCommerce products to ensure they\'re configured correctly.','virakcloud-woo'); ?></p>
                        <a href="<?php echo admin_url('edit.php?post_type=product'); ?>" class="button button-secondary" target="_blank">
                            <?php echo esc_html__('View Products','virakcloud-woo'); ?>
                        </a>
                    </div>
                    
                    <div class="vcw-next-step">
                        <h4><?php echo esc_html__('2. Test the Purchase Flow','virakcloud-woo'); ?></h4>
                        <p><?php echo esc_html__('Make a test purchase to ensure the VM provisioning works correctly.','virakcloud-woo'); ?></p>
                        <a href="<?php echo home_url(); ?>" class="button button-secondary" target="_blank">
                            <?php echo esc_html__('Visit Store','virakcloud-woo'); ?>
                        </a>
                    </div>
                    
                    <div class="vcw-next-step">
                        <h4><?php echo esc_html__('3. Manage Instances','virakcloud-woo'); ?></h4>
                        <p><?php echo esc_html__('Monitor and manage customer instances from the admin dashboard.','virakcloud-woo'); ?></p>
                        <a href="<?php echo admin_url('admin.php?page=virakcloud-woo-user-instances'); ?>" class="button button-secondary">
                            <?php echo esc_html__('Manage Instances','virakcloud-woo'); ?>
                        </a>
                    </div>
                </div>
                
                <div class="vcw-wizard-actions">
                    <a href="<?php echo admin_url('admin.php?page=virakcloud-woo'); ?>" class="button button-primary">
                        <?php echo esc_html__('Go to Dashboard','virakcloud-woo'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=virakcloud-woo-settings'); ?>" class="button button-secondary">
                        <?php echo esc_html__('Advanced Settings','virakcloud-woo'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }
    
    // AJAX Handlers
    public static function ajax_step(): void {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Unauthorized'], 403);
        check_ajax_referer('vcw_wizard_nonce');
        
        $step = isset($_POST['step']) ? intval($_POST['step']) : 1;
        $base = Secure_Store::get_base_url();
        $token = Secure_Store::get_token();
        
        if (!$base || !$token) {
            wp_send_json_error(['message' => 'API connection not configured']);
        }
        
        $client = new Rest_Client($base, $token);
        
        switch ($step) {
            case 2: // Zone selection
                $zones = $client->get_zones();
                $zone_data = [];
                if (isset($zones['data']) && is_array($zones['data'])) {
                    foreach ($zones['data'] as $zone) {
                        $zone_data[] = [
                            'id' => $zone['id'] ?? $zone['uuid'] ?? '',
                            'name' => $zone['name'] ?? $zone['displayName'] ?? 'Unknown Zone',
                            'location' => $zone['location'] ?? $zone['city'] ?? 'Unknown Location'
                        ];
                    }
                }
                wp_send_json_success(['zones' => $zone_data]);
                break;
                
            case 3: // Service selection
                $zone_id = Settings_Store::get()['zone_id'] ?? '';
                if (!$zone_id) {
                    wp_send_json_error(['message' => 'Zone not selected']);
                }
                
                $services = $client->get_service_offerings($zone_id);
                $networks = $client->get_network_offerings($zone_id);
                $images = $client->get_vm_images($zone_id);
                
                // Get previously selected items
                $settings = Settings_Store::get();
                $selected_services = $settings['selected_service_ids'] ?? [];
                $selected_networks = $settings['selected_network_ids'] ?? [];
                $selected_images = $settings['selected_image_ids'] ?? [];
                
                $data = [
                    'services' => self::format_services($services['data'] ?? []),
                    'networks' => self::format_networks($networks['data'] ?? []),
                    'images' => self::format_images($images['data'] ?? []),
                    'selected_services' => $selected_services,
                    'selected_networks' => $selected_networks,
                    'selected_images' => $selected_images
                ];
                
                wp_send_json_success($data);
                break;
                
            default:
                wp_send_json_error(['message' => 'Invalid step']);
        }
    }
    
    public static function ajax_save(): void {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Unauthorized'], 403);
        check_ajax_referer('vcw_wizard_nonce');
        
        $step = isset($_POST['step']) ? intval($_POST['step']) : 1;
        $data = $_POST['data'] ?? [];
        
        switch ($step) {
            case 1: // API Connection
                            if (isset($data['base_url'])) {
                $raw_base = is_string($data['base_url']) ? trim($data['base_url']) : '';
                $clean_base = rtrim($raw_base, '/');
                Secure_Store::save_base_url($clean_base);
            }
                if (isset($data['api_token'])) {
                    $token = is_string($data['api_token']) ? trim($data['api_token']) : '';
                    Secure_Store::save_token($token);
                }
                wp_send_json_success(['message' => 'API connection saved']);
                break;
                
            case 2: // Zone Selection
                if (isset($data['zone_id'])) {
                    $settings = Settings_Store::get();
                    $settings['zone_id'] = $data['zone_id'];
                    Settings_Store::save($settings);
                    wp_send_json_success(['message' => 'Zone selected']);
                }
                break;
                
            case 3: // Service Selection
                $settings = Settings_Store::get();
                if (isset($data['selected_service_ids'])) {
                    $settings['selected_service_ids'] = $data['selected_service_ids'];
                }
                if (isset($data['selected_network_ids'])) {
                    $settings['selected_network_ids'] = $data['selected_network_ids'];
                }
                if (isset($data['selected_image_ids'])) {
                    $settings['selected_image_ids'] = $data['selected_image_ids'];
                }
                Settings_Store::save($settings);
                wp_send_json_success(['message' => 'Services configured']);
                break;
                
            default:
                wp_send_json_error(['message' => 'Invalid step']);
        }
    }
    
    public static function ajax_get_state(): void {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Unauthorized'], 403);
        check_ajax_referer('vcw_wizard_nonce');
        
        $settings = Settings_Store::get();
        
        $state = [
            'selected_service_ids' => $settings['selected_service_ids'] ?? [],
            'selected_network_ids' => $settings['selected_network_ids'] ?? [],
            'selected_image_ids' => $settings['selected_image_ids'] ?? []
        ];
        
        wp_send_json_success($state);
    }
    
    public static function ajax_test_connection(): void {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Unauthorized'], 403);
        check_ajax_referer('vcw_wizard_nonce');
        
        $base = isset($_POST['base_url']) ? esc_url_raw(wp_unslash($_POST['base_url'])) : '';
        $token = isset($_POST['api_token']) ? sanitize_text_field(wp_unslash($_POST['api_token'])) : '';
        
        if (!$base || !$token) {
            wp_send_json_error(['message' => 'Please provide both base URL and API token']);
        }
        
        
        // Validate URL format
        if (!filter_var($base, FILTER_VALIDATE_URL)) {
            wp_send_json_error(['message' => 'Invalid URL format. Please enter a valid URL.']);
        }
        
        try {

            if (!class_exists('VirakCloud\\Woo\\Rest_Client')) {
                wp_send_json_error(['message' => 'Rest_Client class not found. Please check plugin installation.']);
                return;
            }

            if (!empty($base) && substr($base, -1) !== '/') {
                $base .= '/';
            }

            $client = new Rest_Client($base, $token);
            $tests = $client->run_tests(['token']);
            $tokenTest = is_array($tests) ? array_shift($tests) : null;
            $statusCode = $tokenTest['code'] ?? null;

            if ($statusCode === 200 || $statusCode === 204) {
                wp_send_json_success(['message' => 'Connection successful! API token is valid.']);
            }

            $code = $statusCode !== null ? intval($statusCode) : 'unknown';
            wp_send_json_error(['message' => 'Connection failed. Invalid API token or endpoint (HTTP ' . $code . ').']);
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'An error occurred while testing the connection. Please try again later.']);
        }
    }
    
    public static function ajax_sync_products(): void {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Unauthorized'], 403);
        check_ajax_referer('vcw_wizard_nonce');
        
        try {
            $settings = Settings_Store::get();
            $selected_services = $settings['selected_service_ids'] ?? [];
            
            if (empty($selected_services)) {
                wp_send_json_error(['message' => 'No services selected for sync']);
            }
            
            // Check if WooCommerce is active
            if (!class_exists('\WC_Product')) {
                wp_send_json_error(['message' => 'WooCommerce is not active. Please activate WooCommerce to create products.']);
            }
            
            // Check if we have API credentials
            $base_url = Secure_Store::get_base_url();
            $api_token = Secure_Store::get_token();
            
            if (!$base_url || !$api_token) {
                wp_send_json_error(['message' => 'API credentials not configured. Please complete steps 1-3 of the setup wizard.']);
            }
            
            // Check if zone is selected
            $zone_id = $settings['zone_id'] ?? '';
            if (!$zone_id) {
                wp_send_json_error(['message' => 'No zone selected. Please complete step 2 of the setup wizard.']);
            }
            
            // Enqueue services for sync
            Inventory_Sync::enqueue($selected_services);
            
            // Process sync
            Inventory_Sync::process(count($selected_services));
            
            $created_count = count($selected_services);
            wp_send_json_success([
                'message' => "Successfully created {$created_count} products",
                'created_count' => $created_count
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Error creating products: ' . $e->getMessage()]);
        } catch (Error $e) {
            wp_send_json_error(['message' => 'Fatal error creating products: ' . $e->getMessage()]);
        }
    }
    
    private static function format_services($services): array {
        $formatted = [];
        foreach ($services as $service) {
            $hardware = $service['hardware'] ?? [];
            $cpu = $hardware['cpu_core'] ?? $service['cpu_number'] ?? $service['cpu'] ?? $service['cpu_count'] ?? 'Unknown';
            $memory = $hardware['memory_mb'] ?? $service['memory'] ?? $service['memory_size'] ?? $service['ram'] ?? 'Unknown';
            $storage = $hardware['root_disk_size_gB'] ?? $service['storage'] ?? $service['disk_size'] ?? $service['volume_size'] ?? 'Unknown';
            $cpu_speed = $hardware['cpu_speed_MHz'] ?? '';
            $network_rate = $hardware['network_rate'] ?? '';
            $disk_iops = $hardware['disk_iops'] ?? '';
            
            if (is_numeric($memory)) {
                $memory = $memory >= 1024 ? round($memory / 1024, 1) . ' GB' : $memory . ' MB';
            }
            if (is_numeric($storage)) {
                $storage = $storage >= 1024 ? round($storage / 1024, 1) . ' TB' : $storage . ' GB';
            }
            
            if (is_numeric($cpu_speed)) {
                $cpu_speed = $cpu_speed >= 1000 ? round($cpu_speed / 1000, 1) . ' GHz' : $cpu_speed . ' MHz';
            }
            
            if (is_numeric($network_rate)) {
                $network_rate = $network_rate >= 1000 ? round($network_rate / 1000, 1) . ' Gbps' : $network_rate . ' Mbps';
            }
            
            $price_info   = $service['hourly_price'] ?? $service['hourly_price_no_discount'] ?? [];
            $raw_up       = $price_info['up'] ?? null;
            $raw_down     = $price_info['down'] ?? null;
            $price_up     = is_numeric($raw_up) ? round($raw_up / 1_000_000, 4) : null;
            $price_down   = is_numeric($raw_down) ? round($raw_down / 1_000_000, 4) : null;

            $formatted[] = [
                'id'                    => $service['id'] ?? $service['uuid'] ?? '',
                'name'                  => $service['name'] ?? $service['displayName'] ?? 'Unknown Service',
                'description'           => $service['description'] ?? $service['desc'] ?? '',
                'cpu'                   => $cpu . ' cores',
                'memory'                => $memory,
                'storage'               => $storage,
                'cpu_speed'             => $cpu_speed,
                'network_rate'          => $network_rate,
                'disk_iops'             => $disk_iops ? $disk_iops . ' IOPS' : '',
                'price_up'              => ($price_up !== null) ? ('$' . $price_up . '/hr') : '',
                'price_down'            => ($price_down !== null) ? ('$' . $price_down . '/hr') : '',
                'category'              => $service['category'] ?? '',
                'is_available'          => $service['is_available'] ?? false,
                'is_public'             => $service['is_public'] ?? false,
                'suggested'             => $service['suggested'] ?? false,
                'has_image_requirement' => $service['has_image_requirement'] ?? null,
            ];
        }
        return $formatted;
    }
    
    private static function format_networks($networks): array {
        $formatted = [];
        foreach ($networks as $network) {
            $formatted[] = [
                'id' => $network['id'] ?? $network['uuid'] ?? '',
                'name' => $network['name'] ?? $network['displayName'] ?? 'Unknown Network',
                'description' => $network['description'] ?? $network['desc'] ?? '',
                'type' => $network['type'] ?? $network['network_type'] ?? 'Unknown',
                'subnet' => $network['subnet'] ?? $network['cidr'] ?? '',
                'gateway' => $network['gateway'] ?? '',
                'dns' => $network['dns'] ?? $network['dns_servers'] ?? '',
                'vlan' => $network['vlan_id'] ?? $network['vlan'] ?? '',
                'zone' => $network['zone'] ?? $network['zone_id'] ?? ''
            ];
        }
        return $formatted;
    }
    
    private static function format_images($images): array {
        $formatted = [];
        foreach ($images as $image) {
            $hardware = $image['hardware_requirement'] ?? [];
            $cpu_req = $hardware['cpunumber'] ?? '';
            $cpu_speed_req = $hardware['cpuspeed'] ?? '';
            $memory_req = $hardware['memory'] ?? '';
            $disk_req = $hardware['rootdisksize'] ?? '';
            
            if (is_numeric($cpu_speed_req)) {
                $cpu_speed_req = $cpu_speed_req >= 1000 ? round($cpu_speed_req / 1000, 1) . ' GHz' : $cpu_speed_req . ' MHz';
            }
            if (is_numeric($memory_req)) {
                $memory_req = $memory_req >= 1024 ? round($memory_req / 1024, 1) . ' GB' : $memory_req . ' MB';
            }
            if (is_numeric($disk_req)) {
                $disk_req = $disk_req >= 1024 ? round($disk_req / 1024, 1) . ' TB' : $disk_req . ' GB';
            }
            
            $formatted[] = [
                'id'                      => $image['id'] ?? $image['uuid'] ?? '',
                'name'                    => $image['name'] ?? $image['displayName'] ?? 'Unknown Image',
                'display_text'            => $image['display_text'] ?? '',
                'name_original'           => $image['name_original'] ?? $image['name_orginal'] ?? '',
                'description'             => $image['description'] ?? $image['desc'] ?? '',
                'type'                    => $image['type'] ?? '',
                'os_type'                => $image['os_type'] ?? 'Unknown OS',
                'os_name'                => $image['os_name'] ?? '',
                'os_version'             => $image['os_version'] ?? '',
                'cpu_requirement'         => $cpu_req ? ($cpu_req . ' cores') : '',
                'cpu_speed_requirement'   => $cpu_speed_req,
                'memory_requirement'      => $memory_req,
                'disk_requirement'        => $disk_req,
                'category'                => $image['category'] ?? '',
                'is_available'            => $image['is_available'] ?? false,
                'ready_to_use_app'        => $image['ready_to_use_app'] ?? false,
                'ready_to_use_app_name'   => $image['ready_to_use_app_name'] ?? '',
                'ready_to_use_app_version' => $image['ready_to_use_app_version'] ?? ''
            ];
        }
        return $formatted;
    }
}
