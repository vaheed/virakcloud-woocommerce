<?php
namespace VirakCloud\Woo;
defined('ABSPATH') || exit;
class Secure_Store {
    private const OPT_BASE = 'vcw_base_url';
    private const OPT_TOKEN = 'vcw_token';
    public static function save_base_url(string $u): bool { return update_option(self::OPT_BASE, esc_url_raw($u), false); }
    public static function get_base_url(): string { return (string) get_option(self::OPT_BASE, ''); }
    public static function save_token(string $t): bool { return update_option(self::OPT_TOKEN, wp_hash_password($t).'::'.base64_encode($t), false); }
    public static function get_token(): string {
        $v=(string) get_option(self::OPT_TOKEN,''); if (strpos($v,'::')!==false){ [, $enc]=explode('::',$v,2); return (string) base64_decode($enc); } return $v;
    }
    
    /** Check if a zone is allowed based on settings */
    public static function is_zone_allowed(string $zone_id): bool {
        if (!$zone_id) return false;
        
        $settings = Settings_Store::get();
        $allowed_zones = $settings['zone_id'] ?? '';
        
        // If no specific zone is configured, allow all
        if (empty($allowed_zones)) return true;
        
        // Check if the zone matches the configured zone
        return $zone_id === $allowed_zones;
    }
}
