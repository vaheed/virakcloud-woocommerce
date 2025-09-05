<?php
namespace VirakCloud\Woo;
defined('ABSPATH') || exit;

class Inventory_Sync {
    private const QUEUE_OPT = 'vcw_sync_queue';

    public static function bootstrap(): void {
        add_action('wp_ajax_vcw_sync_now', [__CLASS__, 'ajax_sync_now']);
        add_action('wp_ajax_vcw_queue_status', [__CLASS__, 'ajax_queue_status']);
        add_action('wp_ajax_vcw_queue_retry', [__CLASS__, 'ajax_queue_retry']);
        add_action('wp_ajax_vcw_queue_clear', [__CLASS__, 'ajax_queue_clear']);
        add_action('wp_ajax_vcw_archive_removed', [__CLASS__, 'ajax_archive_removed']);
    }

    public static function ajax_sync_now(): void {
        if (!current_user_can('manage_options')) wp_send_json_error([],403);
        check_ajax_referer('vcw_settings_nonce');
        $set = Settings_Store::get();
        $sel = $set['selected_service_ids'] ?? [];
        self::enqueue($sel);
        self::process(25);
        wp_send_json_success(self::status());
    }

    public static function ajax_queue_status(): void {
        if (!current_user_can('manage_options')) wp_send_json_error([],403);
        check_ajax_referer('vcw_settings_nonce');
        wp_send_json_success(self::status());
    }

    public static function ajax_queue_retry(): void {
        if (!current_user_can('manage_options')) wp_send_json_error([],403);
        check_ajax_referer('vcw_settings_nonce');
        $st = self::getq();
        $ids = array_map('strval', $st['failed'] ?? []);
        $st['pending'] = array_values(array_unique(array_merge($st['pending'] ?? [], $ids)));
        $st['failed'] = [];
        update_option(self::QUEUE_OPT, $st, false);
        wp_send_json_success(self::status());
    }

    public static function ajax_queue_clear(): void {
        if (!current_user_can('manage_options')) wp_send_json_error([],403);
        check_ajax_referer('vcw_settings_nonce');
        update_option(self::QUEUE_OPT, ['pending'=>[], 'done'=>[], 'failed'=>[]], false);
        wp_send_json_success(self::status());
    }

    public static function ajax_archive_removed(): void {
        if (!current_user_can('manage_options')) wp_send_json_error([],403);
        check_ajax_referer('vcw_settings_nonce');
        $selected = array_map('strval', Settings_Store::get()['selected_service_ids'] ?? []);
        $ids = get_posts(['post_type'=>'product','posts_per_page'=>-1,'fields'=>'ids','meta_key'=>'_vcw_service_id','meta_compare'=>'EXISTS']);
        $archived = 0;
        foreach ($ids as $pid) {
            $sid = (string) get_post_meta($pid, '_vcw_service_id', true);
            $typ = (string) get_post_meta($pid, '_vcw_service_type', true);
            if ($typ==='service_offerings' && $sid && !in_array($sid, $selected, true)) {
                wp_update_post(['ID'=>$pid, 'post_status'=>'draft']);
                $archived++;
            }
        }
        wp_send_json_success(['archived'=>$archived]);
    }

    private static function getq(): array {
        $q = get_option(self::QUEUE_OPT, []);
        if (!is_array($q)) $q = [];
        $q += ['pending'=>[], 'done'=>[], 'failed'=>[]];
        return $q;
    }
    
    private static function status(): array {
        $q = self::getq();
        return ['pending'=>count($q['pending']), 'done'=>count($q['done']), 'failed'=>count($q['failed'])];
    }
    public static function enqueue(array $ids): void {
        $ids = array_values(array_unique(array_map('strval', $ids)));
        $q = self::getq();
        foreach ($ids as $id) if ($id && !in_array($id, $q['pending'], true) && !in_array($id, $q['done'], true)) $q['pending'][] = $id;
        update_option(self::QUEUE_OPT, $q, false);
    }

    private static function product_upsert(array $item): void {
        if (!class_exists('\WC_Product')) return;
        $sid = (string)($item['id'] ?? '');
        if (!$sid) return;
        $name = (string)($item['name'] ?? $sid);
        $desc = (string)($item['description'] ?? '');

        $existing = get_posts(['post_type'=>'product','posts_per_page'=>1,'fields'=>'ids','meta_key'=>'_vcw_service_id','meta_value'=>$sid]);
        if ($existing) {
            $pid = (int)$existing[0];
            $p = wc_get_product($pid);
            if ($p) {
                $p->set_name($name);
                $p->set_description($desc);
                $p->set_sku($sid);
                $p->save();
            }
        } else {
            $p = new \WC_Product_Simple();
            $p->set_name($name);
            $p->set_status('publish');
            $p->set_catalog_visibility('visible');
            $p->set_sku($sid);
            $pid = $p->save();
            if ($pid) {
                update_post_meta($pid, '_vcw_service_id', $sid);
                update_post_meta($pid, '_vcw_service_type', 'service_offerings');
            }
        }
    }

    public static function process(int $limit = 25): void {
        $q = self::getq();
        $base = Secure_Store::get_base_url(); $tok = Secure_Store::get_token();
        if (!$base || !$tok) return;
        $client = new Rest_Client($base, $tok);
        $zid = Settings_Store::get()['zone_id'] ?? '';
        $map = [];
        if ($zid) {
            $data = $client->get_service_offerings($zid)['data'] ?? [];
            foreach ($data as $row) {
                $id = (string)($row['id'] ?? $row['uuid'] ?? $row['slug'] ?? '');
                if (!$id) continue;
                $map[$id] = ['id'=>$id, 'name'=>(string)($row['name'] ?? $row['displayName'] ?? $id), 'description'=>(string)($row['description'] ?? ''), 'type'=>'service_offerings', 'raw'=>$row];
            }
        }
        $n=0;
        while (!empty($q['pending']) && $n < $limit) {
            $sid = array_shift($q['pending']); $n++;
            try {
                if (isset($map[$sid])) self::product_upsert($map[$sid]);
                $q['done'][] = $sid;
            } catch (\Throwable $e) {
                $q['failed'][] = $sid;
            }
        }
        update_option(self::QUEUE_OPT, $q, false);
    }
}
