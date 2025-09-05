<?php
namespace VirakCloud\Woo;
defined('ABSPATH') || exit;

class Rest_Client {
    private string $base_url;
    private string $token;

    public function __construct(string $base_url, string $token) {
        $this->base_url = rtrim($base_url, '/');
        $this->token = $token;
    }

    private function request(string $method, string $path): array {
        $url = $this->base_url . $path;
        $headers = [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json; charset=utf-8',
        ];
        $res = wp_remote_request($url, ['method'=>$method,'headers'=>$headers,'timeout'=>20]);
        if (is_wp_error($res)) {
            return ['label'=>$path,'url'=>$url,'code'=>0,'body_raw'=>$res->get_error_message(),'body_pretty'=>$res->get_error_message()];
        }
        $code = (int) wp_remote_retrieve_response_code($res);
        $raw  = (string) wp_remote_retrieve_body($res);
        return ['label'=>$path,'url'=>$url,'code'=>$code,'body_raw'=>$raw,'body_pretty'=>$this->pretty($raw)];
    }

    private function pretty(string $raw): string {
        $d = json_decode($raw, true);
        return json_last_error()===JSON_ERROR_NONE ? (string) wp_json_encode($d, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) : $raw;
    }

    public function run_tests(array $tests): array {
        $log=[];
        if (in_array('token',$tests,true)) $log[] = $this->request('GET','/user/token');
        if (in_array('abilities',$tests,true)) $log[] = $this->request('GET','/user/token-abilities');
        if (in_array('zones',$tests,true)) {
            $r = $this->request('GET','/zones'); $d=json_decode($r['body_raw']??'',true);
            if (!is_array($d) || (!isset($d['data']) && !isset($d[0]))) $r = $this->request('GET','/zones/list');
            $log[]=$r;
        }
        if (in_array('service_offerings',$tests,true) || in_array('images',$tests,true)) {
            $zones = $this->get_zones();
            $z = is_array($zones['data']??null) && !empty($zones['data']) ? $zones['data'][0] : null;
            $zid = is_array($z) ? (string)($z['id'] ?? $z['uuid'] ?? '') : '';
            if ($zid) {
                if (in_array('service_offerings',$tests,true)) $log[] = $this->request('GET','/zone/'.rawurlencode($zid).'/instance/service-offerings');
                if (in_array('images',$tests,true)) $log[] = $this->request('GET','/zone/'.rawurlencode($zid).'/instance/vm-images');
            }
        }
        return $log;
    }

    public function get_zones(): array {
        $r = $this->request('GET','/zones'); $d=json_decode($r['body_raw']??'',true);
        if (is_array($d)) { if (isset($d['data'])) return $d; if (isset($d[0])) return ['data'=>$d]; }
        $r = $this->request('GET','/zones/list'); $d=json_decode($r['body_raw']??'',true);
        return is_array($d) ? (isset($d['data'])?$d:['data'=>$d]) : ['data'=>[]];
    }

    public function get_service_offerings(string $zone_id): array {
        $r = $this->request('GET','/zone/'.rawurlencode($zone_id).'/instance/service-offerings'); $d=json_decode($r['body_raw']??'',true);
        return is_array($d) ? (isset($d['data'])?$d:(isset($d[0])?['data'=>$d]:['data'=>[]])) : ['data'=>[]];
    }

    public function get_vm_images(string $zone_id): array {
        $r = $this->request('GET','/zone/'.rawurlencode($zone_id).'/instance/vm-images'); $d=json_decode($r['body_raw']??'',true);
        return is_array($d) ? (isset($d['data'])?$d:(isset($d[0])?['data'=>$d]:['data'=>[]])) : ['data'=>[]];
    }

    public function get_instances(string $zone_id): array {
        $r = $this->request('GET','/zone/'.rawurlencode($zone_id).'/instance'); $d=json_decode($r['body_raw']??'',true);
        return is_array($d) ? (isset($d['data'])?$d:(isset($d[0])?['data'=>$d]:['data'=>[]])) : ['data'=>[]];
    }

    public function get_network_offerings(string $zone_id): array {
        $r = $this->request('GET','/zone/'.rawurlencode($zone_id).'/network'); $d=json_decode($r['body_raw']??'',true);
        return is_array($d) ? (isset($d['data'])?$d:(isset($d[0])?['data'=>$d]:['data'=>[]])) : ['data'=>[]];
    }

    public function provision_instance(string $zone_id, string $service_offering_id, string $network_offering_id, string $image_id, string $name): array {
        $payload = [
            'name' => $name,
            'service_offering_id' => $service_offering_id,
            'network_ids' => is_array($network_offering_id) ? $network_offering_id : array_filter([$network_offering_id]),
            'vm_image_id' => $image_id
        ];
        $url = $this->base_url . '/zone/' . rawurlencode($zone_id) . '/instance';
        $headers = [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json; charset=utf-8',
        ];
        $res = wp_remote_post($url, ['headers'=>$headers, 'timeout'=>30, 'body'=>wp_json_encode($payload)]);
        if (is_wp_error($res)) return ['code'=>0, 'error'=>$res->get_error_message()];
        $code = (int) wp_remote_retrieve_response_code($res);
        $raw  = (string) wp_remote_retrieve_body($res);
        $pretty = $this->pretty($raw);
        return ['code'=>$code, 'url'=>$url, 'body_raw'=>$raw, 'body_pretty'=>$pretty];
    }
}
