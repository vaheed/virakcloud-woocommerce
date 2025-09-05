<?php
namespace VirakCloud\Woo;
defined('ABSPATH') || exit;
class Settings_Store {
    private const OPT = 'vcw_settings';
    public static function get(): array {
        $d=['zone_id'=>'','service_offering_id'=>'','network_offering_id'=>'','vm_image_id'=>'','selected_service_ids'=>[],'selected_image_ids'=>[],'selected_network_ids'=>[]];
        $v=get_option(self::OPT,[]); if(!is_array($v)) $v=[]; $v+=['selected_service_ids'=>[],'selected_image_ids'=>[],'selected_network_ids'=>[]];
        return array_merge($d,$v);
    }
    public static function save(array $data): bool {
        $cur=self::get();
        $cur['zone_id']=sanitize_text_field($data['zone_id']??$cur['zone_id']);
        $cur['service_offering_id']=sanitize_text_field($data['service_offering_id']??$cur['service_offering_id']);
        $cur['network_offering_id']=sanitize_text_field($data['network_offering_id']??$cur['network_offering_id']);
        $cur['vm_image_id']=sanitize_text_field($data['vm_image_id']??$cur['vm_image_id']);
        $svc=$data['selected_service_ids']??$cur['selected_service_ids'];
        $img=$data['selected_image_ids']??$cur['selected_image_ids'];
        $net=$data['selected_network_ids']??$cur['selected_network_ids'];
        $cur['selected_service_ids']=array_values(array_unique(array_map('strval', is_array($svc)?$svc:[])));
        $cur['selected_image_ids']=array_values(array_unique(array_map('strval', is_array($img)?$img:[])));
        $cur['selected_network_ids']=array_values(array_unique(array_map('strval', is_array($net)?$net:[])));
        return update_option(self::OPT,$cur,false);
    }
}
