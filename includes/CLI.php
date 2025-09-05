<?php
namespace VirakCloud\Woo;
defined('ABSPATH') || exit;

class CLI {
    public static function bootstrap(): void {
        if (defined('WP_CLI') && \WP_CLI) {
            \WP_CLI::add_command('vcw sync', [__CLASS__, 'cmd_sync']);
        }
    }
    public static function cmd_sync(): void {
        $sel = Settings_Store::get()['selected_service_ids'] ?? [];
        Inventory_Sync::process(100);
        if (defined('WP_CLI') && \WP_CLI) { \WP_CLI::success('vcw sync completed (selected: '.count($sel).')'); }
    }
}
