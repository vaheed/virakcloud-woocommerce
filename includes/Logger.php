<?php
namespace VirakCloud\Woo;
defined('ABSPATH') || exit;
class Logger {
    private const OPT='vcw_logs'; private const MAX=500;
    public static function log(string $level,string $message,array $context=[]): void {
        $rows=get_option(self::OPT,[]); if(!is_array($rows)) $rows=[];
        $rows[]=['ts'=>time(),'level'=>$level,'message'=>$message,'context'=>$context];
        if(count($rows)>self::MAX) $rows=array_slice($rows,-self::MAX);
        update_option(self::OPT,$rows,false);
    }
    public static function get(): array { $rows=get_option(self::OPT,[]); return is_array($rows)?$rows:[]; }
}
