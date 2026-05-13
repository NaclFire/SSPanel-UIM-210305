<?php

namespace App\Command;

use App\Models\BlockIp;
use App\Models\Bought;
use App\Models\DetectBanLog;
use App\Models\EmailQueue;
use App\Models\EmailVerify;
use App\Models\Ip;
use App\Models\LoginIp;
use App\Models\Node;
use App\Models\NodeInfoLog;
use App\Models\NodeOnlineLog;
use App\Models\RadiusBan;
use App\Models\Shop;
use App\Models\Speedtest;
use App\Models\TelegramSession;
use App\Models\Token;
use App\Models\TrafficLog;
use App\Models\UnblockIp;
use App\Models\User;
use App\Models\UserSubscribeLog;
use App\Services\Config;
use App\Services\Mail;
use App\Services\RedisClient;
use App\Utils\CloudflareDriver;
use App\Utils\DatatablesHelper;
use App\Utils\QQWry;
use App\Utils\Radius;
use App\Utils\Telegram;
use App\Utils\Tools;
use Exception;
use Illuminate\Database\Capsule\Manager as DB;
class Job extends Command
{
    public $description = ''
    . '├─=: php xcat Job [选项]' . PHP_EOL
    . '│ ├─ DailyJob                - 每日任务' . PHP_EOL
    . '│ ├─ CheckJob                - 检查任务，每分钟' . PHP_EOL;

    public function boot()
    {
        if (count($this->argv) === 2) {
            echo $this->description;
        } else {
            $methodName = $this->argv[2];
            if (method_exists($this, $methodName)) {
                $this->$methodName();
            } else {
                echo '方法不存在.' . PHP_EOL;
            }
        }
    }

    /**
     * 发邮件
     *
     * @return void
     */
    public function SendMail()
    {
        if (file_exists(BASE_PATH . '/storage/email_queue')) {
            echo "程序正在运行中" . PHP_EOL;
            return false;
        }
        $myfile = fopen(BASE_PATH . '/storage/email_queue', 'wb+') or die('Unable to open file!');
        $txt = '1';
        fwrite($myfile, $txt);
        fclose($myfile);
        $email_queues = EmailQueue::all();
        foreach ($email_queues as $email_queue) {
            try {
                Mail::send($email_queue->to_email, $email_queue->subject, $email_queue->template, json_decode($email_queue->array), []);
            } catch (Exception $e) {
                echo $e->getMessage();
            }
            echo '发送邮件至 ' . $email_queue->to_email . PHP_EOL;
            $email_queue->delete();
        }
        unlink(BASE_PATH . '/storage/email_queue');
    }

    /**
     * 每日任务
     *
     * @return void
     */
    public function DailyJob()
    {
        ini_set('memory_limit', '-1');
        $nodes = Node::all();
        foreach ($nodes as $node) {
            if (date('d') == $node->bandwidthlimit_resetday) {
                $node->node_bandwidth = 0;
                $node->save();
            }
        }

        // 清理订阅记录
        UserSubscribeLog::where(
            'request_time',
            '<',
            date('Y-m-d H:i:s', time() - 86400 * (int)$_ENV['subscribeLog_keep_days'])
        )->delete();

        Token::where('expire_time', '<', time())->delete();
        NodeInfoLog::where('log_time', '<', time() - 86400 * 3)->delete();
        LoginIp::where('datetime', '<', time() - 86400 * 3)->delete();
        NodeOnlineLog::where('log_time', '<', time() - 86400 * 3)->delete();
        TrafficLog::where('log_time', '<', time() - 86400 * 6)->where('type', '=', 1)->delete();
//        DetectLog::where('datetime', '<', time() - 86400 * 3)->delete();
//        Speedtest::where('datetime', '<', time() - 86400 * 3)->delete();
        EmailVerify::where('expire_in', '<', time() - 86400 * 3)->delete();
        system('rm ' . BASE_PATH . '/storage/*.png', $ret);

        $db = new DatatablesHelper();
        Tools::reset_auto_increment($db, 'user_traffic_log');
        Tools::reset_auto_increment($db, 'ss_node_online_log');
        Tools::reset_auto_increment($db, 'ss_node_info');

        if (Config::getconfig('Telegram.bool.DailyJob')) {
            Telegram::Send(Config::getconfig('Telegram.string.DailyJob'));
            echo 'Telegram.bool.DailyJob' . PHP_EOL;
        }

//        $shopid = [];
//        $shops = Shop::where('status', 1)->get();    //已下架的商品不支持重置使用
//        foreach ($shops as $auto_reset_shop) {
//            if ($auto_reset_shop->use_loop()) {
//                $shopid[] = $auto_reset_shop->id;
//            }
//        }

        //auto reset
        $shopRenew = Shop::where('status', '1')->where('content', 'like', '%reset_value%')->get(['id'])->toArray();
        $shopRenewId = Bought::whereIn('shopid', array_filter(array_column($shopRenew, 'id')))->groupBy('userid')->orderBy("id", "desc")->get(['id']);
        $boughts = Bought::whereIn('id', array_filter(array_column(json_decode($shopRenewId), 'id')))->get();
        $bought_users = array();
        foreach ($boughts as $bought) {
            $user = $bought->user();
            if ($user == null) {
                continue;
            }

            $shop = $bought->shop();
            if ($shop == null) {
                $bought->delete();
                continue;
            }

            if ($shop->use_loop()) {
                $bought_users[] = $bought->userid;
                if ($bought->valid() && $bought->used_days() % $shop->reset() == 0 && $bought->used_days() != 0) {
                    echo('流量重置-' . $user->id . "\n");
                    $user->transfer_enable = Tools::toGB($shop->reset_value());
                    $user->u = 0;
                    $user->d = 0;
                    $user->last_day_t = 0;
                    $user->save();
                    $user->sendMail(
                        $_ENV['appName'] . '-您的流量被重置了',
                        'news/warn.tpl',
                        [
                            'text' => '您好，根据您所订购的订单 ID:' . $bought->id . '，流量已经被重置为' . $shop->reset_value() . 'GB'
                        ],
                        [],
                        $_ENV['email_queue']
                    );
                }
            }
        }

        $users = User::all();
        foreach ($users as $user) {
            // 记录每日流量
            $origin_traffic = $user->u + $user->d - $user->last_day_t;
            if ($origin_traffic != 0) {
                $traffic = new TrafficLog();
                $traffic->user_id = $user->id;
                $traffic->u = 0;
                $traffic->d = 0;
                $traffic->rate = 1;
                $traffic->node_id = 0;
                $traffic->traffic = $origin_traffic;
                $traffic->log_time = time();
                $traffic->type = 1;
                $traffic->save();
            }

            $user->last_day_t = ($user->u + $user->d);
            $user->save();
            if (in_array($user->id, $bought_users)) {
                continue;
            }
            if (date('d') == $user->auto_reset_day) {
                $user->u = 0;
                $user->d = 0;
                $user->last_day_t = 0;
                $user->transfer_enable = $user->auto_reset_bandwidth * 1024 * 1024 * 1024;
                $user->save();
            }
        }

        $url = 'https://github.com/metowolf/qqwry.dat/releases/latest/download/qqwry.dat';
        $qqwry = @file_get_contents($url);
        if ($qqwry !== false && strlen($qqwry) > 1024) {
            $datFile = BASE_PATH . '/storage/qqwry.dat';
            $bakFile = BASE_PATH . '/storage/qqwry.dat.bak';
            if (file_exists($datFile)) {
                rename($datFile, $bakFile);
            }
            file_put_contents($datFile, $qqwry);
            echo "qqwry.dat 更新成功";
        } else {
            echo "qqwry.dat 下载失败";
        }

        $iplocation = new QQWry();
        $location = $iplocation->getlocation('8.8.8.8');
        $Userlocation = $location['country'];
        if (iconv('gbk', 'utf-8//IGNORE', $Userlocation) !== '美国') {
            unlink(BASE_PATH . '/storage/qqwry.dat');
            rename(BASE_PATH . '/storage/qqwry.dat.bak', BASE_PATH . '/storage/qqwry.dat');
        }
    }

    /**
     * 检查任务，每分钟
     *
     * @return void
     */
    public function CheckJob()
    {
        TrafficLog::where('log_time', '<', time() - 86400 * 2)->where('type', '=', 0)->delete();
        //在线人数检测
//        $users = User::where('node_connector', '>', 0)->get();
//        $full_alive_ips = Ip::where('datetime', '>=', time() - 60)->orderBy('ip')->get();
//        $alive_ipset = array();
//        foreach ($full_alive_ips as $full_alive_ip) {
//            $full_alive_ip->ip = Tools::getRealIp($full_alive_ip->ip);
//            $is_node = Node::where('node_ip', $full_alive_ip->ip)->first();
//            if ($is_node) {
//                continue;
//            }
//
//            if (!isset($alive_ipset[$full_alive_ip->userid])) {
//                $alive_ipset[$full_alive_ip->userid] = new ArrayObject();
//            }
//
//            $alive_ipset[$full_alive_ip->userid]->append($full_alive_ip);
//        }
//
//        foreach ($users as $user) {
//            $alive_ips = ($alive_ipset[$user->id] ?? new ArrayObject());
//            $ips = array();
//
//            $disconnected_ips = explode(',', $user->disconnect_ip);
//
//            foreach ($alive_ips as $alive_ip) {
//                if (!isset($ips[$alive_ip->ip]) && !in_array($alive_ip->ip, $disconnected_ips)) {
//                    $ips[$alive_ip->ip] = 1;
//                    if ($user->node_connector < count($ips)) {
//                        //暂时封禁
//                        $isDisconnect = Disconnect::where('id', '=', $alive_ip->ip)->where(
//                            'userid',
//                            '=',
//                            $user->id
//                        )->first();
//
//                        if ($isDisconnect == null) {
//                            $disconnect = new Disconnect();
//                            $disconnect->userid = $user->id;
//                            $disconnect->ip = $alive_ip->ip;
//                            $disconnect->datetime = time();
//                            $disconnect->save();
//
//                            if ($user->disconnect_ip == null || $user->disconnect_ip == '') {
//                                $user->disconnect_ip = $alive_ip->ip;
//                            } else {
//                                $user->disconnect_ip .= ',' . $alive_ip->ip;
//                            }
//                            $user->save();
//                        }
//                    }
//                }
//            }
//        }
//
//        //解封
//        $disconnecteds = Disconnect::where('datetime', '<', time() - 300)->get();
//        foreach ($disconnecteds as $disconnected) {
//            $user = User::where('id', '=', $disconnected->userid)->first();
//            if (empty($user)) {
//                continue;
//            }
//            $ips = explode(',', $user->disconnect_ip);
//            $new_ips = '';
//            $first = 1;
//
//            foreach ($ips as $ip) {
//                if ($ip != $disconnected->ip && $ip != '') {
//                    if ($first == 1) {
//                        $new_ips .= $ip;
//                        $first = 0;
//                    } else {
//                        $new_ips .= ',' . $ip;
//                    }
//                }
//            }
//
//            $user->disconnect_ip = $new_ips;
//
//            if ($new_ips == '') {
//                $user->disconnect_ip = null;
//            }
//
//            $user->save();
//
//            $disconnected->delete();
//        }

        //自动续费
        $boughts = Bought::where('renew', '<', time() + 60)->where('renew', '<>', 0)->get();
        foreach ($boughts as $bought) {
            /** @var Bought $bought */
            $user = $bought->user();
            if ($user == null) {
                continue;
            }

            $shop = $bought->shop();
            if ($shop == null) {
                $bought->delete();
                $bought->is_notified = true;
                $bought->save();
                continue;
            }
            if ($user->money >= $shop->price) {
                $user->money -= $shop->price;
                $user->save();
                $shop->buy($user, 1);
                $bought->renew = 0;
                $bought->save();

                $bought_new = new Bought();
                $bought_new->userid = $user->id;
                $bought_new->shopid = $shop->id;
                $bought_new->datetime = time();
                $bought_new->renew = time() + $shop->auto_renew * 86400;
                $bought_new->price = $shop->price;
                $bought_new->coupon = '';
                $bought_new->save();
                $bought->is_notified = true;
                $bought->save();
            } elseif ($bought->is_notified == false) {
                $bought->is_notified = true;
                $bought->save();
            }
        }

        Ip::where('datetime', '<', time() - 300)->delete();
        UnblockIp::where('datetime', '<', time() - 300)->delete();
        BlockIp::where('datetime', '<', time() - 86400)->delete();
        TelegramSession::where('datetime', '<', time() - 900)->delete();

//        $adminUser = User::where('is_admin', '=', '1')->get();

        echo '用户检测开始' . PHP_EOL;
//        $users = User::where('class', '!=', 0)->get();
        $users = User::where('class', '!=', 0)
            ->get([
                'id',
                'uuid',
                'email',
                'class',
                'passwd',
                'node_group',
                'is_admin',
                'u',
                'd',
                'transfer_enable',
                'class_expire',
                'enable',
                'node_speedlimit',
                'node_connector'
            ]);
        $redis = new RedisClient();
        $redis->setex(
            "users:all",
            120,
            json_encode($users->toArray())
        );
        foreach ($users as $user) {
            if (($user->transfer_enable <= $user->u + $user->d || $user->enable == 0 || (strtotime($user->expire_in) < time() && strtotime($user->expire_in) > 644447105))
                && RadiusBan::where(
                    'userid',
                    $user->id
                )->first() == null) {
                $rb = new RadiusBan();
                $rb->userid = $user->id;
                $rb->save();
                Radius::Delete($user->email);
            }

            if (
                $user->class != 0 &&
                strtotime($user->class_expire) < time() &&
                strtotime($user->class_expire) > 1420041600
            ) {
                $user->class = 0;
            }

            // 审计封禁解封
            if ($user->enable == 0) {
                $logs = DetectBanLog::where('user_id', $user->id)->orderBy('id', 'desc')->first();
                if ($logs != null) {
                    if (($logs->end_time + $logs->ban_time * 60) <= time()) {
                        $user->enable = 1;
                    }
                }
            }

            $user->save();
        }
        echo '用户检测结束' . PHP_EOL;
        $rbusers = RadiusBan::all();
        foreach ($rbusers as $sinuser) {
            $user = User::find($sinuser->userid);

            if ($user == null) {
                $sinuser->delete();
                continue;
            }

            if ($user->enable == 1 && (strtotime($user->expire_in) > time() || strtotime(
                        $user->expire_in
                    ) < 644447105) && $user->transfer_enable > $user->u + $user->d) {
                $sinuser->delete();
                Radius::Add($user, $user->passwd);
            }
        }

        // 多ip对接节点，检查ip可用性
        echo '节点ip检测开始' . PHP_EOL;
        // 只检测ip字段是多个ip格式的节点
//        $nodes = Node::where('node_ip', 'like', '%;%')->get();
        $nodes = Node::all();
        foreach ($nodes as $node) {
            $heartbeat = $redis->get("node:heartbeat:{$node->id}");
            if ($heartbeat !== null) {
                $node->node_heartbeat = $heartbeat;
                $node->save();
            }
            if (str_contains($node->node_ip, ';')) {
                $nodeIps = explode(';', $node->node_ip);
                echo '开始检测：' . $node->name . PHP_EOL;
                $milliseconds = microtime(true);
                // 检测间隔超过5分钟
                if ($milliseconds - $node->last_check_time > 5 * 60) {
                    echo '当前节点超过5分钟未检测' . PHP_EOL;
                    $availableIp = '';
                    foreach ($nodeIps as $nodeIp) {
                        // 查看node_ip是否是双栈
                        $nodeIpVersion = explode('#', $nodeIp);
                        if (count($nodeIpVersion) == 2) {
                            $nodeIpV4 = $nodeIpVersion[0];
                        } else {
                            $nodeIpV4 = $nodeIp;
                        }
                        // 测试ip是否能ping通
                        if (Tools::pingIp($nodeIpV4)) {
                            // 如果第一个ip和当前要解析的ip不一致才执行更新DNS
                            if (!str_contains($nodeIps[0], $nodeIpV4)) {
                                // 更新cloudflare上节点域名解析的ip
                                CloudflareDriver::updateRecord(explode(';', $node->server)[0], $nodeIpV4);
                                $availableIp = $nodeIp;
                                echo '域名：' . explode(';', $node->server)[0] . '，已解析ip：' . $availableIp . PHP_EOL;
                            }
                            $node->last_check_time = $milliseconds;
                            $node->save();
                            break;
                        } else {
                            echo 'ip : ' . $nodeIp . '不可用' . PHP_EOL;
                        }
                    }
                    $key = array_search($availableIp, $nodeIps);
                    if ($key !== false) {
                        $item = $nodeIps[$key];
                        unset($nodeIps[$key]);
                        array_unshift($nodeIps, $item);
                        $node->node_ip = implode(';', $nodeIps);
                        $node->save();
                    }
                }
            } else {
                $server = $node->getOutServer();
                if (!Tools::is_ip($server) && $node->changeNodeIp($server)) {
                    $node->save();
                }
            }
        }
        echo '节点ip检测结束' . PHP_EOL;

        //更新节点 IP，每分钟
//        $nodes = Node::all();
//        $allNodeID = [];
//        foreach ($nodes as $node) {
//            $allNodeID[] = $node->id;
//            $server = $node->getOutServer();
//            if (!Tools::is_ip($server) && $node->changeNodeIp($server)) {
//                $node->save();
//            }
//            if (in_array($node->sort, array(0, 10, 12))) {
//                Tools::updateRelayRuleIp($node);
//            }
//        }

        // 删除无效的中转
//        $allNodeID = implode(', ', $allNodeID);
//        $datatables = new DatatablesHelper();
//        $datatables->query(
//            'DELETE FROM `relay` WHERE `source_node_id` NOT IN(' . $allNodeID . ') OR `dist_node_id` NOT IN(' . $allNodeID . ')'
//        );
    }

    public function AddTrafficLog()
    {
        echo '流量统计开始' . PHP_EOL;
        $redis = new RedisClient();
        echo '用户流量入库开始' . PHP_EOL;
        $user_ids = $redis->smembers('traffic:users');
        foreach ($user_ids as $user_id) {
            $key = "traffic:user:$user_id";
            $traffic = $redis->hgetall($key);
            if (!$traffic) continue;
            $u = intval($traffic['u'] ?? 0);
            $d = intval($traffic['d'] ?? 0);
            if ($u > 0 || $d > 0) {
                User::where('id', $user_id)->update([
                    'u' => DB::raw("u + $u"),
                    'd' => DB::raw("d + $d"),
                    't' => time()
                ]);
            }
            $redis->del($key);
            $redis->srem('traffic:users', $user_id);
        }
        echo '用户流量入库结束' . PHP_EOL;
        echo '节点流量入库开始' . PHP_EOL;
        // 查找所有节点流量key
        $nodeKeys = $redis->smembers('traffic:node:*');
        foreach ($nodeKeys as $key) {
            // traffic:node:3
            $node_id = str_replace('traffic:node:', '', $key);
            $traffic = intval($redis->get($key));
            if ($traffic <= 0) {
                $redis->del($key);
                continue;
            }
            // 写入节点流量
            Node::where('id', $node_id)->update([
                'node_bandwidth' => DB::raw("node_bandwidth + $traffic")
            ]);
            // 清理redis
            $redis->del($key);
        }
        echo '节点流量入库结束' . PHP_EOL;
        echo '流量统计结束' . PHP_EOL;
    }
}
