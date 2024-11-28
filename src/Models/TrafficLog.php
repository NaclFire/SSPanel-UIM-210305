<?php


namespace App\Models;

use App\Utils\Tools;

class TrafficLog extends Model
{
    protected $connection = 'default';
    protected $table = 'user_traffic_log';

    public function node()
    {
        $node = Node::where('id', $this->attributes['node_id'])->first();
        if ($node == null) {
            self::where('id', '=', $this->attributes['id'])->delete();
            return null;
        }

        return $node;
    }

    public function user()
    {
        $user = User::where('id', $this->attributes['user_id'])->first();
        if ($user == null) {
            self::where('id', '=', $this->attributes['id'])->delete();
            return null;
        }

        return $user;
    }

    public function totalUsed()
    {
        return $this->attributes['traffic'];
    }

    public function totalUsedRaw()
    {
        return number_format($this->attributes['traffic'], 2, '.', '');
    }

    public function logTime()
    {
        return Tools::toDateTime($this->attributes['log_time']);
    }

    public static function getTotalUsedRaw($startTime, $userId)
    {
        $totalUsed = self::where('type', '=', 0)
            ->where('user_id', '=', $userId)
            ->where('log_time', '>=', $startTime)->pluck('traffic');
        $totalTraffic = 0;
        foreach ($totalUsed as $traffic) {
            $totalTraffic += $traffic; // 累加每条记录的值
        }
        return $totalTraffic;
    }
}
