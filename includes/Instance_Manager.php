<?php
namespace VirakCloud\Woo;
defined('ABSPATH') || exit;

class Instance_Manager {

    /** Core HTTP helper with JSON body support */
    private static function request( $method, $path, $body = null) {
        if (!class_exists('\\WP_Http') && !function_exists('wp_remote_request')) {
            return ['code'=>0,'error'=>'WordPress HTTP API not available'];
        }
        $base = rtrim((string) Secure_Store::get_base_url(), '/');
        $token = (string) Secure_Store::get_token();
        $url = $base . $path;
        $args = [
            'method'  => $method,
            'timeout' => 25,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
            ],
        ];
        if (!is_null($body)) {
            $args['headers']['Content-Type'] = 'application/json; charset=utf-8';
            $args['body'] = wp_json_encode($body);
        }
        $res = wp_remote_request($url, $args);
        if (is_wp_error($res)) {
            return ['code'=>0, 'url'=>$url, 'error'=>$res->get_error_message()];
        }
        $code = (int) wp_remote_retrieve_response_code($res);
        $raw  = (string) wp_remote_retrieve_body($res);
        return ['code'=>$code, 'url'=>$url, 'body_raw'=>$raw, 'body_pretty'=>self::pretty($raw)];
    }

    private static function pretty( $raw) {
        $d = json_decode($raw, true);
        return (json_last_error()===JSON_ERROR_NONE) ? (string) wp_json_encode($d, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) : $raw;
    }

    /** ==== Public actions used by UI (customer + admin) ==== */

    public static function start( $zone, $id) {
        if (!Secure_Store::is_zone_allowed($zone)) {
            return ['code'=>403, 'error'=>'Zone not allowed'];
        }
        return self::request('POST', '/zone/' . rawurlencode($zone) . '/instance/' . rawurlencode($id) . '/start');
    }

    public static function stop( $zone, $id, $forced = false) {
        if (!Secure_Store::is_zone_allowed($zone)) {
            return ['code'=>403, 'error'=>'Zone not allowed'];
        }
        return self::request('POST', '/zone/' . rawurlencode($zone) . '/instance/' . rawurlencode($id) . '/stop', ['forced' => $forced]);
    }

    public static function reboot( $zone, $id) {
        if (!Secure_Store::is_zone_allowed($zone)) {
            return ['code'=>403, 'error'=>'Zone not allowed'];
        }
        return self::request('POST', '/zone/' . rawurlencode($zone) . '/instance/' . rawurlencode($id) . '/reboot');
    }

    public static function delete( $zone, $id, $name = null) {
        if (!Secure_Store::is_zone_allowed($zone)) {
            return ['code'=>403, 'error'=>'Zone not allowed'];
        }
        
        // If name is not provided, try to get it from instance details
        if (empty($name)) {
            $details = self::details($zone, $id);
            if (isset($details['data']['name'])) {
                $name = $details['data']['name'];
            }
        }
        
        // Prepare request body with instance name if available
        $body = null;
        if (!empty($name)) {
            $body = ['name' => $name];
        }
        
        return self::request('DELETE', '/zone/' . rawurlencode($zone) . '/instance/' . rawurlencode($id), $body);
    }

    public static function details( $zone, $id) {
        if (!Secure_Store::is_zone_allowed($zone)) {
            return ['code'=>403, 'error'=>'Zone not allowed'];
        }
        
        // Get instance details from API
        $r = self::request('GET', '/zone/' . rawurlencode($zone) . '/instance?filter_id=' . rawurlencode($id));
        if (isset($r['error'])) {
            return $r;
        }
        
        $data = json_decode($r['body_raw'] ?? '', true);
        $row = self::pick_instance_row($data, $id);
        
        if (!$row) {
            return ['code'=>404, 'error'=>'Instance not found'];
        }
        
        // Extract zone information from instance data
        $zone_name = $row['zone']['name'] ?? 'Unknown Zone';
        
        // Extract service offering information
        $service_offering = $row['service_offering']['name'] ?? 'Unknown';
        $category = $row['service_offering']['category'] ?? 'Unknown';
        $description = $row['service_offering']['description'] ?? null;
        
        // Extract hardware information from service offering
        $hardware = $row['service_offering']['hardware'] ?? [];
        $cpu_cores = $hardware['cpu_core'] ?? null;
        $memory = $hardware['memory_mb'] ?? null;
        $cpu_speed = $hardware['cpu_speed_MHz'] ?? null;
        $disk_size = $hardware['root_disk_size_gB'] ?? null;
        $network_rate = $hardware['network_rate'] ?? null;
        $disk_iops = $hardware['disk_iops'] ?? null;
        
        // Extract pricing information
        $hourly_price = $row['service_offering']['hourly_price'] ?? [];
        $hourly_price_up = $hourly_price['up'] ?? null;
        $hourly_price_down = $hourly_price['down'] ?? null;
        
        // Extract network information from network endpoint
        $network_info = self::get_network_info_for_instance($zone, $row['id']);
        
        // Format the response for the details display
        return [
            'details' => [
                'status' => $row['status'] ?? 'Unknown',
                'running_state' => $row['running_state'] ?? 'Unknown',
                'name' => $row['name'] ?? 'Unknown',
                'id' => $row['id'] ?? 'Unknown',
                'service_offering' => $service_offering,
                'category' => $category,
                'description' => $description,
                'cpu_cores' => $cpu_cores,
                'memory' => $memory,
                'cpu_speed' => $cpu_speed,
                'disk_size' => $disk_size,
                'network_rate' => $network_rate,
                'disk_iops' => $disk_iops,
                'hourly_price_up' => $hourly_price_up,
                'hourly_price_down' => $hourly_price_down,
                'is_available' => $row['service_offering']['is_available'] ?? null,
                'is_public' => $row['service_offering']['is_public'] ?? null,
                'suggested' => $row['service_offering']['suggested'] ?? null,
                'has_image_requirement' => $row['service_offering']['has_image_requirement'] ?? null,
                'vm_image' => $row['vm_image'] ?? null,
                'network' => $network_info['network'] ?? null,
                'ip_address' => $network_info['ip_address'] ?? null,
                'public_ip' => $network_info['public_ip'] ?? null,
                'zone' => $zone,
                'zone_name' => $zone_name
            ]
        ];
    }
    
    
    
    private static function get_network_info_for_instance($zone, $instance_id) {
        $network_info = [
            'network' => null,
            'ip_address' => null,
            'public_ip' => null
        ];
        
        // Get networks from the network endpoint
        $r = self::request('GET', '/zone/' . rawurlencode($zone) . '/network');
        if (isset($r['error'])) {
            return $network_info;
        }
        
        $data = json_decode($r['body_raw'] ?? '', true);
        if (!isset($data['data']) || !is_array($data['data'])) {
            return $network_info;
        }
        
        // Search through networks to find the one with our instance
        foreach ($data['data'] as $network) {
            if (!isset($network['instance_network']) || !is_array($network['instance_network'])) {
                continue;
            }
            
            foreach ($network['instance_network'] as $instance_network) {
                if (is_array($instance_network) && ($instance_network['instance_id'] ?? '') === $instance_id) {
                    $network_info['ip_address'] = $instance_network['ipaddress'] ?? null;
                    $network_info['public_ip'] = $instance_network['ipaddress'] ?? null;
                    $network_info['network'] = $network['name'] ?? null;
                    return $network_info;
                }
            }
        }
        
        return $network_info;
    }

    public static function credentials( $zone, $id) {
        if (!Secure_Store::is_zone_allowed($zone)) {
            return ['code'=>403, 'error'=>'Zone not allowed'];
        }
        
        // Get instance details first
        $r = self::request('GET', '/zone/' . rawurlencode($zone) . '/instance?filter_id=' . rawurlencode($id));
        if (isset($r['error'])) {
            return $r;
        }
        
        $data = json_decode($r['body_raw'] ?? '', true);
        $row = self::pick_instance_row($data, $id);
        
        // Extract credentials from instance data
        $username = '';
        $password = '';
        $public_ip = null;
        
        if (is_array($row)) {
            $username = (string) ($row['username'] ?? $row['user'] ?? '');
            $password = (string) ($row['password'] ?? $row['pass'] ?? '');
            
            // Try to get public IP from instance data first
            $public_ip = $row['public_ip'] ?? $row['ip'] ?? $row['ip_address'] ?? $row['publicip'] ?? null;
            if ($public_ip) {
                $public_ip = (string) $public_ip;
            }
        }
        
        // If no public IP found in instance data, try network scan
        if (!$public_ip) {
            $public_ip = self::find_public_ip($zone, $id);
        }
        
        $cred = [
            'username' => $username,
            'password' => $password,
        ];

        return [
            'code' => $r['code'] ?? 200,
            'url'  => $r['url'] ?? '',
            'credentials' => $cred,
            'body_pretty' => null,
            'body_raw'    => null,
        ];
    }

    public static function snapshot( $zone, $id, $name) {
        if (!Secure_Store::is_zone_allowed($zone)) {
            return ['code'=>403, 'error'=>'Zone not allowed'];
        }
        $name = is_string($name) ? trim($name) : '';
        if ($name === '') {
            $name = 'snap-' . date('Ymd-His');
        }

        return self::request(
            'POST',
            '/zone/' . rawurlencode($zone) . '/instance/' . rawurlencode($id) . '/snapshot',
            [
                'instance_id' => $id,
                'name'        => $name,
            ]
        );
    }

    public static function list_snapshots( $zone, $id) {
        if (!Secure_Store::is_zone_allowed($zone)) {
            return ['code'=>403, 'error'=>'Zone not allowed'];
        }
        $r = self::request('GET', '/zone/' . rawurlencode($zone) . '/instance?filter_id=' . rawurlencode($id));
        $data = json_decode($r['body_raw'] ?? '', true);
        $row = self::pick_instance_row($data, $id);
        $snaps = is_array($row) ? ($row['snapshot'] ?? []) : [];
        $body = wp_json_encode(['data'=>$snaps]);
        return ['code'=>$r['code'] ?? 200, 'url'=>$r['url'] ?? '', 'body_raw'=>$body, 'body_pretty'=>self::pretty($body)];
    }

    public static function delete_snapshot( $zone, $id, $snapshot_id) {
        if (!Secure_Store::is_zone_allowed($zone)) {
            return ['code'=>403, 'error'=>'Zone not allowed'];
        }
        return self::request(
            'DELETE',
            '/zone/' . rawurlencode($zone) . '/instance/' . rawurlencode($id) . '/snapshot/' . rawurlencode($snapshot_id),
            [
                'instanceId' => $id,
                'instance_id' => $id,
            ]
        );
    }

    public static function revert_snapshot( $zone, $id, $snapshot_id) {
        if (!Secure_Store::is_zone_allowed($zone)) {
            return ['code'=>403, 'error'=>'Zone not allowed'];
        }
        $path_with_id = '/zone/' . rawurlencode($zone) . '/instance/' . rawurlencode($id) . '/snapshot/' . rawurlencode($snapshot_id) . '/revert';
        $response = self::request('POST', $path_with_id, null);
        $code = (int)($response['code'] ?? 0);
        if (in_array($code, [400, 404, 405, 500], true)) {
            $payload = [
                'snapshotId'  => $snapshot_id,
                'snapshot_id' => $snapshot_id,
                'instanceId'  => $id,
                'instance_id'=> $id,
            ];
            $response = self::request('POST', '/zone/' . rawurlencode($zone) . '/instance/' . rawurlencode($id) . '/snapshot/revert', $payload);
        }
        return $response;
    }

    public static function get_instance_status( $zone, $id) {
        if (!Secure_Store::is_zone_allowed($zone)) {
            return ['code'=>403, 'error'=>'Zone not allowed'];
        }
        
        $r = self::request('GET', '/zone/' . rawurlencode($zone) . '/instance?filter_id=' . rawurlencode($id));
        if (isset($r['error'])) {
            return $r;
        }
        
        $data = json_decode($r['body_raw'] ?? '', true);
        $row = self::pick_instance_row($data, $id);
        
        $status = '';
        $is_running = false;
        $service_offering_name = '';
        $service_details = [];
        $instance_details = [];

        if (is_array($row)) {
            // Extract the instance status from various possible keys
            $status = (string) ($row['instance_status'] ?? $row['status'] ?? '');
            // Determine if the instance is running based on common status values
            $is_running = in_array(strtolower($status), ['running', 'active', 'started', 'up']);

            if (isset($row['service_offering_name']) && !is_array($row['service_offering_name'])) {
                $service_offering_name = (string) $row['service_offering_name'];
            } elseif (isset($row['service_offering']) && !is_array($row['service_offering'])) {
                $service_offering_name = (string) $row['service_offering'];
            } elseif (isset($row['service_offering_id']) && !is_array($row['service_offering_id'])) {
                $service_offering_name = (string) $row['service_offering_id'];
            }

            $instance_fields = [
                'id' => 'Id',
                'name' => 'Name',
                'has_image_requirement' => 'Has Image Requirement',
                'is_available' => 'Is Available',
                'is_public' => 'Is Public',
                'suggested' => 'Suggested',
                'category' => 'Category',
                'description' => 'Description'
            ];
            foreach ($instance_fields as $field => $label) {
                if (array_key_exists($field, $row)) {
                    $val = $row[$field];
                    $translated_label = __($label, 'virakcloud-woo');
                    if (is_bool($val)) {
                        $instance_details[$translated_label] = $val ? 'true' : 'false';
                    } elseif ($val === null) {
                        $instance_details[$translated_label] = 'null';
                    } else {
                        $instance_details[$translated_label] = (string) $val;
                    }
                }
            }

            // Extract any service offering details if provided as an array or object.
            // If present, flatten nested objects and format known fields into
            // human‑friendly strings. Nested arrays that are not recognised
            // will be JSON‑encoded for display.
            $detailSource = null;
            if (isset($row['service_offering']) && is_array($row['service_offering'])) {
                $detailSource = $row['service_offering'];
            } elseif (isset($row['offering']) && is_array($row['offering'])) {
                $detailSource = $row['offering'];
            } elseif (isset($row['service_details']) && is_array($row['service_details'])) {
                $detailSource = $row['service_details'];
            }
            if ($detailSource !== null) {
                foreach ($detailSource as $key => $value) {
                    // Normalize the key into a more readable label by
                    // replacing underscores with spaces and capitalising
                    // each word. Specific keys will be further customised.
                    $label = ucwords(str_replace('_', ' ', (string) $key));
                    // Handle pricing fields. When the API returns an
                    // hourly_price or hourly_price_no_discount object, it
                    // typically includes "up" and "down" amounts in the
                    // smallest currency unit (e.g. micro‑dollars). Convert
                    // these into a human‑readable dollar per hour format.
                    if (($key === 'hourly_price' || $key === 'hourly_price_no_discount') && is_array($value)) {
                        $upRaw = $value['up'] ?? null;
                        $downRaw = $value['down'] ?? null;
                        if (is_numeric($upRaw)) {
                            $priceUp = round($upRaw / 1_000_000, 4);
                            $service_details['Hourly Price Up'] = '$' . $priceUp . '/hr';
                        }
                        if (is_numeric($downRaw)) {
                            $priceDown = round($downRaw / 1_000_000, 4);
                            $service_details['Hourly Price Down'] = '$' . $priceDown . '/hr';
                        }
                        continue;
                    }
                    // Handle hardware specifications. The hardware object may
                    // include CPU cores, memory (MB), CPU speed (MHz), disk
                    // size (GB), network rate (Mbps) and disk IOPS. We
                    // convert these into friendly units (e.g. GB, GHz).
                    if ($key === 'hardware' && is_array($value)) {
                        $cpuCores    = $value['cpu_core'] ?? $value['cpu'] ?? null;
                        $memoryMb    = $value['memory_mb'] ?? $value['memory'] ?? null;
                        $cpuSpeed    = $value['cpu_speed_MHz'] ?? $value['cpu_speed'] ?? null;
                        $diskGb      = $value['root_disk_size_gB'] ?? $value['disk_size'] ?? $value['rootdisksize'] ?? null;
                        $networkRate = $value['network_rate'] ?? null;
                        $diskIops    = $value['disk_iops'] ?? null;
                        if ($cpuCores !== null) {
                            $service_details['CPU Cores'] = $cpuCores . ' cores';
                        }
                        if (is_numeric($memoryMb)) {
                            $service_details['Memory'] = ($memoryMb >= 1024 ? round($memoryMb / 1024, 1) . ' GB' : $memoryMb . ' MB');
                        }
                        if (is_numeric($cpuSpeed)) {
                            $service_details['CPU Speed'] = ($cpuSpeed >= 1000 ? round($cpuSpeed / 1000, 1) . ' GHz' : $cpuSpeed . ' MHz');
                        }
                        if (is_numeric($diskGb)) {
                            $service_details['Disk Size'] = ($diskGb >= 1024 ? round($diskGb / 1024, 1) . ' TB' : $diskGb . ' GB');
                        }
                        if (is_numeric($networkRate)) {
                            $service_details['Network Rate'] = ($networkRate >= 1000 ? round($networkRate / 1000, 1) . ' Gbps' : $networkRate . ' Mbps');
                        }
                        if ($diskIops !== null) {
                            $service_details['Disk IOPS'] = $diskIops;
                        }
                        continue;
                    }
                    // For boolean fields (e.g. is_available, is_public,
                    // suggested), convert to "true"/"false" strings to
                    // avoid displaying numbers or empty strings. Use the
                    // human‑friendly label derived above.
                    if (is_bool($value)) {
                        $service_details[$label] = $value ? 'true' : 'false';
                        continue;
                    }
                    // Scalar values can be displayed as-is. JSON‑encode
                    // any non‑scalar values to provide a readable
                    // representation of nested structures that we do not
                    // specifically handle above.
                    if (is_scalar($value) || $value === null) {
                        $service_details[$label] = $value;
                    } else {
                        $service_details[$label] = json_encode($value);
                    }
                }
            }
        }

        // Translate the status into the current locale if a translation exists
        $translated_status = __($status, 'virakcloud-woo');
        
        // Translate keys and boolean values in service_details to the current locale
        foreach ($service_details as $k => $v) {
            $translated_key = __($k, 'virakcloud-woo');
            if ($v === 'true' || $v === 'false') {
                $v_translated = __($v, 'virakcloud-woo');
            } else {
                $v_translated = $v;
            }
            if ($translated_key !== $k) {
                unset($service_details[$k]);
                $service_details[$translated_key] = $v_translated;
            } else {
                $service_details[$k] = $v_translated;
            }
        }
        
        // Translate boolean values in instance_details after labels have been localised
        foreach ($instance_details as $k => $v) {
            if ($v === 'true' || $v === 'false') {
                $instance_details[$k] = __($v, 'virakcloud-woo');
            }
        }
        return [
            'code' => $r['code'] ?? 200,
            'url'  => $r['url'] ?? '',
            'status' => $translated_status,
            'is_running' => $is_running,
            'service_offering_name' => $service_offering_name,
            'service_details' => $service_details,
            'instance_details' => $instance_details,
            'body_pretty' => null,
            'body_raw'    => null,
        ];
    }

    /**
     * List VM images available in a given zone. Returns the raw API
     * response from Rest_Client::get_vm_images(). Only allowed zones
     * can be queried. The response is always an associative array with
     * a `data` key containing an array of image objects.
     */
    public static function list_vm_images(string $zone): array {
        if (!Secure_Store::is_zone_allowed($zone)) {
            return ['code' => 403, 'error' => 'Zone not allowed'];
        }
        try {
            $client = new Rest_Client(Secure_Store::get_base_url(), Secure_Store::get_token());
            $images = $client->get_vm_images($zone);
            // Ensure consistent structure: wrap lists under data key
            if (!isset($images['data']) && is_array($images)) {
                $images = ['data' => $images];
            }
            return $images;
        } catch (\Throwable $e) {
            return ['code' => 500, 'error' => 'Error fetching images: ' . $e->getMessage()];
        }
    }

    /**
     * Rebuild an instance with a new VM image. Sends the required
     * payload to the `/rebuild` endpoint. Returns the API response.
     */
    public static function rebuild_instance(string $zone, string $id, string $image_id): array {
        if (!Secure_Store::is_zone_allowed($zone)) {
            return ['code' => 403, 'error' => 'Zone not allowed'];
        }
        if (!$image_id) {
            return ['code' => 400, 'error' => 'Image ID missing'];
        }
        return self::request(
            'POST',
            '/zone/' . rawurlencode($zone) . '/instance/' . rawurlencode($id) . '/rebuild',
            ['vm_image_id' => $image_id]
        );
    }
    
    /** Get real-time instance status and update order meta */
    public static function refresh_instance_status($zone, $id, $order_id = null) {
        $status_result = self::get_instance_status($zone, $id);
        
        if (isset($status_result['error'])) {
            return $status_result;
        }
        
        // Update order meta if order_id provided
        if ($order_id && function_exists('wc_get_order')) {
            $order = wc_get_order($order_id);
            if ($order) {
                foreach ($order->get_items() as $item) {
                    $item_instance_id = (string) $item->get_meta('vcw_instance_id', true);
                    if ($item_instance_id === $id) {
                        $item->add_meta_data('vcw_provision_status', $status_result['status'], true);
                        $item->save();
                        break;
                    }
                }
            }
        }
        
        return $status_result;
    }

    public static function get_vnc_url( $zone, $id) {
        if (!Secure_Store::is_zone_allowed($zone)) {
            return ['code'=>403, 'error'=>'Zone not allowed'];
        }
        
        // Try to get VNC URL from instance details
        $r = self::request('GET', '/zone/' . rawurlencode($zone) . '/instance?filter_id=' . rawurlencode($id));
        if (isset($r['error'])) {
            return $r;
        }
        
        $data = json_decode($r['body_raw'] ?? '', true);
        $row = self::pick_instance_row($data, $id);
        
        $vnc_url = '';
        if (is_array($row)) {
            // Check for VNC-related fields
            $vnc_url = $row['vnc_url'] ?? $row['console_url'] ?? $row['remote_console_url'] ?? '';
        }
        
        if (!$vnc_url) {
            $base = rtrim((string) Secure_Store::get_base_url(), '/');
            $vnc_url = $base . '/api/external/zone/' . rawurlencode($zone) . '/instance/' . rawurlencode($id) . '/console';
        }
        
        return [
            'code' => $r['code'] ?? 200,
            'url'  => $r['url'] ?? '',
            'vnc_url' => $vnc_url,
            'body_pretty' => null,
            'body_raw'    => null,
        ];
    }

    /** ==== Helpers ==== */

    private static function pick_instance_row($data, $id) {
        if (!is_array($data)) return [];
        $items = $data['data'] ?? $data;
        if (isset($items['id'])) return $items;
        if (is_array($items)) {
            foreach ($items as $it) {
                if (is_array($it) && (($it['id'] ?? '') === $id)) return $it;
            }
        }
        return [];
    }

    private static function find_public_ip( $zone, $id) {
        // Try to get from instance details
        $r = self::request('GET', '/zone/' . rawurlencode($zone) . '/instance?filter_id=' . rawurlencode($id));
        if (isset($r['error'])) {
            return null;
        }
        
        $data = json_decode($r['body_raw'] ?? '', true);
        $row = self::pick_instance_row($data, $id);
        
        // Check instance fields for public IP
        $public_ip = $row['public_ip'] ?? $row['ip'] ?? $row['ip_address'] ?? $row['publicip'] ?? null;
        if ($public_ip) {
            return (string) $public_ip;
        }
        
        // Try to get from network endpoint
        $r = self::request('GET', '/zone/' . rawurlencode($zone) . '/network');
        if (!isset($r['error'])) {
            $data = json_decode($r['body_raw'] ?? '', true);
            $items = is_array($data) ? ($data['data'] ?? $data) : [];
            if (is_array($items)) {
                foreach ($items as $network) {
                    if (isset($network['instance_network'])) {
                        foreach ($network['instance_network'] as $instance_network) {
                            if ($instance_network['instance_id'] === $id) {
                                return (string) ($instance_network['ipaddress'] ?? '');
                            }
                        }
                    }
                }
            }
        }
        
        return null;
    }

    /**
     * Get network offerings for a zone.
     */
    public static function list_networks($zone) {
        if (!Secure_Store::is_zone_allowed($zone)) {
            return ['code' => 403, 'error' => 'Zone not allowed'];
        }

        $base  = (string) Secure_Store::get_base_url();
        $token = (string) Secure_Store::get_token();
        if (!$base || !$token) {
            return ['code' => 0, 'error' => 'API connection not configured'];
        }

        try {
            $client   = new Rest_Client($base, $token);
            $response = $client->get_network_offerings($zone);

            if (is_array($response) && isset($response['data']) && is_array($response['data'])) {
                return $response['data'];
            }

            return ['code' => $response['code'] ?? 0, 'error' => 'Failed to fetch networks'];
        } catch (\Throwable $e) {
            return ['code' => 0, 'error' => $e->getMessage()];
        }
    }

    public static function extract_id($response) {
        if (!is_array($response)) return null;
        
        if (isset($response['body_raw'])) {
            $data = json_decode($response['body_raw'], true);
            if (is_array($data)) {
                $id = $data['id'] ?? $data['instance_id'] ?? $data['uuid'] ?? null;
                if ($id) return (string) $id;
                
                if (isset($data['data']) && is_array($data['data'])) {
                    $id = $data['data']['id'] ?? $data['data']['instance_id'] ?? $data['data']['uuid'] ?? null;
                    if ($id) return (string) $id;
                }
            }
        }
        
        return null;
    }

    public static function user_owns( $instance_id, $user_id) {
        if (!$instance_id || !$user_id) return false;
        if (!function_exists('wc_get_orders')) return true;
        
        $orders = wc_get_orders([
            'customer_id' => $user_id,
            'limit'       => -1,
            'return'      => 'ids',
            'status'      => array_keys(wc_get_order_statuses()),
        ]);
        
        foreach ($orders as $oid) {
            $order = wc_get_order($oid);
            if (!$order) continue;
            
            foreach ($order->get_items() as $it) {
                $v = $it->get_meta('vcw_instance_id', true);
                if ($v && (string)$v === (string)$instance_id) return true;
            }
        }
        return false;
    }
}
