<?php
namespace VirakCloud\Woo;
defined('ABSPATH') || exit;

class Queue_UI {
    public static function hooks(): void {
        add_action('admin_menu', [__CLASS__, 'menu']);
    }
    public static function menu(): void {
        add_submenu_page('virakcloud-woo', __('Queue','virakcloud-woo'), __('Queue','virakcloud-woo'), 'manage_options', 'virakcloud-woo-queue', [__CLASS__, 'render']);
    }
    public static function render(): void {
        if (!current_user_can('manage_options')) wp_die(esc_html__('No permission.','virakcloud-woo'));
        $nonce = wp_create_nonce('vcw_settings_nonce');
        ?>
        <div class="wrap"><h1><?php echo esc_html__('Virak Cloud — Queue','virakcloud-woo'); ?></h1>
        <p>
            <button class="button button-primary" id="vcw-sync-now"><?php echo esc_html__('Sync Now','virakcloud-woo'); ?></button>
            <button class="button" id="vcw-retry"><?php echo esc_html__('Retry Failed','virakcloud-woo'); ?></button>
            <button class="button" id="vcw-clear"><?php echo esc_html__('Clear Queue','virakcloud-woo'); ?></button>
            <button class="button" id="vcw-archive"><?php echo esc_html__('Archive Removed Products','virakcloud-woo'); ?></button>
        </p>
        <div id="vcw-progress" style="height:16px;background:#f1f1f1;border-radius:8px;overflow:hidden;margin-bottom:12px;"><div id="vcw-bar" style="height:100%;width:0%;background:#2271b1;"></div></div>
        <table class="widefat striped"><thead><tr>
            <th><?php echo esc_html__( 'Status', 'virakcloud-woo' ); ?></th>
            <th><?php echo esc_html__( 'Count', 'virakcloud-woo' ); ?></th>
        </tr></thead><tbody id="vcw-status"></tbody></table>
        </div>
        <script>
        (function(){
            const nonce = '<?php echo esc_js($nonce); ?>';
            const lblPending = <?php echo wp_json_encode( __('Pending','virakcloud-woo') ); ?>;
            const lblDone    = <?php echo wp_json_encode( __('Done','virakcloud-woo') ); ?>;
            const lblFailed  = <?php echo wp_json_encode( __('Failed','virakcloud-woo') ); ?>;
            const msgClear   = <?php echo wp_json_encode( __('Clear queue?', 'virakcloud-woo' ) ); ?>;
            const msgArchive = <?php echo wp_json_encode( __('Archive removed products (set to draft)?', 'virakcloud-woo' ) ); ?>;
            function aj(action, body){
                const f=new FormData(); f.append('action', action); f.append('_wpnonce', nonce);
                Object.keys(body||{}).forEach(k=>f.append(k, body[k]));
                return fetch(ajaxurl,{method:'POST',credentials:'same-origin',body:f}).then(r=>r.json());
            }
            function refresh(){
                aj('vcw_queue_status',{}).then(d=>{
                    if(!(d&&d.success))return;
                    const s=d.data||{pending:0,done:0,failed:0};
                    const total = s.pending + s.done + s.failed;
                    const pct = total ? Math.round((s.done/total)*100) : 0;
                    document.getElementById('vcw-bar').style.width = pct+'%';
                    const tb=document.getElementById('vcw-status');
                    tb.innerHTML=''; [[lblPending,s.pending],[lblDone,s.done],[lblFailed,s.failed]].forEach(x=>{ const tr=document.createElement('tr'); tr.innerHTML='<td>'+x[0]+'</td><td>'+x[1]+'</td>'; tb.appendChild(tr); });
                });
            }
            document.getElementById('vcw-sync-now').addEventListener('click', function(){ aj('vcw_sync_now',{}).then(()=>{ refresh(); }); });
            document.getElementById('vcw-retry').addEventListener('click', function(){ aj('vcw_queue_retry',{}).then(()=>{ refresh(); }); });
            document.getElementById('vcw-clear').addEventListener('click', function(){ if(confirm(msgClear)) aj('vcw_queue_clear',{}).then(()=>{ refresh(); }); });
            document.getElementById('vcw-archive').addEventListener('click', function(){ if(confirm(msgArchive)) aj('vcw_archive_removed',{}).then(d=>{ alert(<?php echo wp_json_encode(__('Archived:', 'virakcloud-woo')); ?> + ' '+((d&&d.success&&d.data&&d.data.archived)||0)); refresh(); }); });
            setInterval(refresh, 3000); refresh();
        })();
        </script>
        <?php
        wp_enqueue_style( 'vcw-persian', VCW_URL . 'assets/vcw-persian.css', [], '1.0' );
    }
}
