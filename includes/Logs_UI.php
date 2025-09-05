<?php
namespace VirakCloud\Woo;
defined('ABSPATH') || exit;

class Logs_UI {
    public static function hooks(): void {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('wp_ajax_vcw_logs_fetch', [__CLASS__, 'ajax_fetch']);
    }
    public static function menu(): void {
        add_submenu_page('virakcloud-woo', __('Logs','virakcloud-woo'), __('Logs','virakcloud-woo'), 'manage_options', 'virakcloud-woo-logs', [__CLASS__, 'render']);
    }
    public static function render(): void {
        if (!current_user_can('manage_options')) wp_die(esc_html__('No permission.','virakcloud-woo'));
        $nonce = wp_create_nonce('vcw_settings_nonce');
        ?>
        <div class="wrap"><h1><?php echo esc_html__('Virak Cloud — Logs','virakcloud-woo'); ?></h1>
        <p><button class="button" id="vcw-log-refresh"><?php echo esc_html__('Refresh','virakcloud-woo'); ?></button></p>
        <table class="widefat striped"><thead><tr>
            <th><?php echo esc_html__( 'Time', 'virakcloud-woo' ); ?></th>
            <th><?php echo esc_html__( 'Level', 'virakcloud-woo' ); ?></th>
            <th><?php echo esc_html__( 'Message', 'virakcloud-woo' ); ?></th>
            <th><?php echo esc_html__( 'Context', 'virakcloud-woo' ); ?></th>
        </tr></thead><tbody id="vcw-log-rows"></tbody></table>
        </div>
        <script>
        (function(){
            const nonce = '<?php echo esc_js($nonce); ?>';
            function fetchJSON(){ const f=new FormData(); f.append('action','vcw_logs_fetch'); f.append('_wpnonce',nonce); return fetch(ajaxurl,{method:'POST',credentials:'same-origin',body:f}).then(r=>r.json()); }
            function render(rows){
                const tb=document.getElementById('vcw-log-rows'); tb.innerHTML='';
                (rows||[]).slice().reverse().forEach(r=>{
                    const tr=document.createElement('tr');
                    const dt=new Date((r.ts||0)*1000).toLocaleString();
                    tr.innerHTML='<td>'+dt+'</td><td>'+ (r.level||'') +'</td><td>'+ (r.message||'') +'</td><td><pre style="white-space:pre-wrap;margin:0">'+ JSON.stringify(r.context||{}) +'</pre></td>';
                    tb.appendChild(tr);
                });
            }
            document.getElementById('vcw-log-refresh').addEventListener('click', function(){ fetchJSON().then(d=>{ if(d&&d.success) render(d.data.rows); }); });
            fetchJSON().then(d=>{ if(d&&d.success) render(d.data.rows); });
        })();
        </script>
        <?php
        wp_enqueue_style( 'vcw-persian', VCW_URL . 'assets/vcw-persian.css', [], '1.0' );
    }
    public static function ajax_fetch(): void {
        if (!current_user_can('manage_options')) wp_send_json_error([],403);
        check_ajax_referer('vcw_settings_nonce');
        wp_send_json_success(['rows'=>Logger::get()]);
    }
}
