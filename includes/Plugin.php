<?php
namespace VirakCloud\Woo;
defined('ABSPATH') || exit;

final class Plugin {
    private static ?Plugin $instance = null;
    public static function instance(): Plugin { return self::$instance ??= new self(); }

    public function init(): void {
        /**
         * Load the plugin translation files.
         *
         * WordPress fires the `plugins_loaded` hook before this init method
         * is called (the Plugin class is bootstrapped on that hook in the
         * main plugin file). Previously the translation loader was added
         * within another `plugins_loaded` callback which never executed
         * because the hook had already run. Consequently, translation
         * strings were not loaded and English text remained even when
         * Persian (fa_IR) or other locales were active.
         *
         * To resolve this, we call `load_plugin_textdomain` directly from
         * within the init method. The domain path parameter must point
         * to the `languages` subdirectory relative to the plugin’s
         * directory name inside the WordPress plugins folder. Using
         * `basename(VCW_PATH)` yields the correct directory name even
         * when the plugin folder is versioned (e.g. `virakcloud-woo-1.0.11`).
         */
        $plugin_dir = basename( VCW_PATH );
        // Load compiled MO files located in <plugin_dir>/languages
        load_plugin_textdomain( 'virakcloud-woo', false, $plugin_dir . '/languages' );
        if (is_admin()) {
            (new Admin_UI())->hooks();
            Logs_UI::hooks();
            Queue_UI::hooks();
            Setup_Wizard::hooks();
        }
        Inventory_Sync::bootstrap();
        CLI::bootstrap();
        Frontend_Configurator::hooks();
        Order_Provisioner::hooks();
        My_Account::hooks();
        Catalog_Behavior::hooks();
    }
}
