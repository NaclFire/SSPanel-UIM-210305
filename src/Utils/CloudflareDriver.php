<?php
/**
 * Created by PhpStorm.
 * User: tonyzou
 * Date: 2018/9/10
 * Time: 上午9:54
 */

namespace App\Utils;

use App\Services\Config;
use Cloudflare\API\Adapter\Guzzle;
use Cloudflare\API\Auth\APIKey;
use Cloudflare\API\Endpoints\DNS;
use Cloudflare\API\Endpoints\Zones;

class CloudflareDriver
{

    // @todo: parameters
    public static function modifyRecord(DNS $dns, $zoneID, $recordID, $name, $content, $type, $proxied = false)
    {
        $details = ['type' => $type, 'name' => $name, 'content' => $content, 'proxied' => $proxied];
        echo '$details = ' . \GuzzleHttp\json_encode($details);
        if ($dns->updateRecordDetails($zoneID, $recordID, $details)->success == true) {
            return 1;
        }
        return 0;
    }

    public static function addRecord(DNS $dns, $zoneID, $type, $name, $content, $ttl = 120, $proxied = false)
    {
        if ($dns->addRecord($zoneID, $type, $name, $content, $ttl, $proxied) == true) {
            return 1;
        }
        return 0;
    }

    public static function updateRecord($name, $content, $proxied = false)
    {
        $key = new APIKey($_ENV['cloudflare_email'], $_ENV['cloudflare_key']);
        $adapter = new Guzzle($key);
        $zones = new Zones($adapter);
        $domainList = explode('.', $name);
        $zoneID = $zones->getZoneID($domainList[1] . '.' . $domainList[2]);

        $dns = new DNS($adapter);

        $r = $dns->listRecords($zoneID, '', $name);
        $recordCount = $r->result_info->count;

        // 获取ip版本
        $ipVersion = Tools::getIpVersion($content);
        if ($ipVersion == 4) {
            $type = 'A';
        } elseif ($ipVersion == 6) {
            $type = 'AAA';
        } else {
            return;
        }
        echo 'type = ' . $type . PHP_EOL;
        if ($recordCount == 0) {
            self::addRecord($dns, $zoneID, $type, $name, $content);
        } elseif ($recordCount >= 1) {
            $records = $r->result;
            foreach ($records as $record) {
                try {
                    if ($record->content !== $content) {
                        $recordID = $record->id;
                        self::modifyRecord($dns, $zoneID, $recordID, $name, $content, $type, $proxied);
                    }
                } catch (\Exception $e) {
                    echo $e->getMessage() . PHP_EOL;
                }
            }
        }
    }
}
