<?php

namespace App\Controllers\Mod_Mu;

use App\Controllers\BaseController;
use App\Models\{Ip, Node, NodeOnlineLog, User};
use App\Services\RedisClient;
use App\Utils\Tools;

class UserController extends BaseController
{
    /**
     * User List
     *
     * @param \Slim\Http\Request $request
     * @param \Slim\Http\Response $response
     * @param array $args
     *
     * @return \Slim\Http\Response
     */
    public function index($request, $response, $args)
    {
        $redis = new RedisClient();

        $node_id = $request->getQueryParam('node_id', '0');

        if ($node_id == '0') {
            $node = Node::where('node_ip', $_SERVER['REMOTE_ADDR'])->first();
        } else {
            $node = Node::where('id', $node_id)->first();
        }

        if ($node == null) {
            return $this->echoJson($response, ['ret' => 0]);
        }

        /* ===============================
         * 心跳改为 Redis（避免写 MySQL）
         * =============================== */

        $redis->setex("node:heartbeat:{$node->id}", 120, time());

        /* ===============================
         * 节点流量限制
         * =============================== */

        if (
            $node->node_bandwidth_limit != 0 &&
            $node->node_bandwidth_limit < $node->node_bandwidth
        ) {
            return $this->echoJson($response, [
                'ret' => 1,
                'data' => null
            ]);
        }

        /* ===============================
         * 获取用户缓存
         * =============================== */

        $cacheKey = "users:all";
        $users_raw = $redis->get($cacheKey);

        if (empty($users_raw)) {
            // 防缓存击穿锁
            if (!$redis->get("users:lock")) {
                $redis->setex("users:lock", 5, 1);
                $users_raw = User::where('enable', 1)
                    ->where('class', '>', 0)
                    ->where('expire_in', '>', date('Y-m-d H:i:s'))
                    ->get([
                        'id',
                        'uuid',
                        'email',
                        'passwd',
                        'class',
                        'node_group',
                        'node_speedlimit',
                        'node_connector',
                        'is_admin',
                        'u',
                        'd',
                        'transfer_enable'
                    ])
                    ->toArray();
                $redis->setex(
                    $cacheKey,
                    120,
                    json_encode($users_raw)
                );
            }

            usleep(500000); // 等缓存生成
            $users_raw = $redis->get($cacheKey);
        }

        if ($users_raw !== null) {
            $users = json_decode($users_raw, true);
        } else {
            $users = [];
        }

        /* ===============================
         * 节点权限筛选（高性能 foreach）
         * =============================== */

        $filtered_users = [];

        foreach ($users as $user) {

            if ($user['is_admin'] == 1) {
                $filtered_users[] = $user;
                continue;
            }

            if ($node->node_group != 0) {

                if (
                    $user['class'] >= $node->node_class &&
                    $user['node_group'] == $node->node_group
                ) {
                    $filtered_users[] = $user;
                }

            } else {

                if ($user['class'] >= $node->node_class) {
                    $filtered_users[] = $user;
                }
            }

        }

        /* ===============================
         * 生成节点用户列表
         * =============================== */

        $users = [];

        $key_list = [
            'email',
            'node_speedlimit',
            'id',
            'passwd',
            'node_connector',
            'uuid'
        ];

        foreach ($filtered_users as $user) {

            if ($user['transfer_enable'] <= $user['u'] + $user['d']) {

                if ($_ENV['keep_connect'] === true) {
                    $user['node_speedlimit'] = 1;
                } else {
                    continue;
                }
            }

            if ($node->sort === 1) {

                $user['passwd'] = $user['uuid'];

            } else {

                $passwdArray = explode(':', $user['passwd']);

                if ($node->method === '2022-blake3-aes-128-gcm') {
                    $user['passwd'] = $passwdArray[0] ?? '';
                } else {
                    $user['passwd'] = $passwdArray[1] ?? '';
                }
            }

            $user = array_intersect_key($user, array_flip($key_list));

            $users[] = $user;
        }

        /* ===============================
         * 返回
         * =============================== */

        return $this->echoJson($response, [
            'ret' => 1,
            'data' => $users
        ]);
    }

    public function addTraffic($request, $response, $args)
    {
        $params = $request->getQueryParams();
        $data = $request->getParam('data');

        $node_id = $params['node_id'];

        if ($node_id == '0') {
            $node = Node::where('node_ip', $_SERVER['REMOTE_ADDR'])->first();
            $node_id = $node->id ?? 0;
        }

        $node = Node::find($node_id);

        if ($node == null) {
            return $this->echoJson($response, [
                'ret' => 1,
                'data' => 'ok',
            ]);
        }

        // 多ip节点，记录在线用户时，只记录在用ip。
        $nodeIpList = explode(';', $node->node_ip);
        if (count($nodeIpList) == 1) {
            $this->logOnlineUser($node_id, $data);
        } else {
            // 查看node_ip是否是双栈
            $nodeIpVersion = explode('#', $nodeIpList[0]);
            // 双栈ip分割之后再处理
            if (count($nodeIpVersion) == 2) {
                if ($nodeIpVersion[0] == $_SERVER['REMOTE_ADDR']) {
                    $this->logOnlineUser($node_id, $data);
                } elseif ($nodeIpVersion[1] == $_SERVER['REMOTE_ADDR']) {
                    $this->logOnlineUser($node_id, $data);
                }
            } else {
                if ($nodeIpList[0] == $_SERVER['REMOTE_ADDR']) {
                    $this->logOnlineUser($node_id, $data);
                }
            }
        }

        if (empty($data)) {
            return $this->echoJson($response, [
                'ret' => 1,
                'data' => 'ok',
            ]);
        }

        $redis = new RedisClient();

        $redis->pipeline(function ($pipe) use ($data, $node) {

            foreach ($data as $log) {

                $user_id = $log['user_id'];

                $u = intval($log['u'] * $node->traffic_rate);
                $d = intval($log['d'] * $node->traffic_rate);

                if ($u <= 0 && $d <= 0) {
                    continue;
                }

                // 改成 用户+节点
                $key = "traffic:user:{$user_id}:{$node->id}";

                /* 用户流量累积 */
                $pipe->hincrby($key, 'u', $u);
                $pipe->hincrby($key, 'd', $d);

                // 保存节点信息
                $pipe->hset($key, 'node_id', $node->id);
                $pipe->hset($key, 'rate', $node->traffic_rate);

                $pipe->expire($key, 3600);

                /* 活跃用户集合 */
                $pipe->sadd("traffic:users", "{$user_id}:{$node->id}");

                /* 节点流量 */
                $pipe->incrby("traffic:node:{$node->id}", $u + $d);

                /* 记录活跃节点 */
                $pipe->sadd("traffic:nodes", $node->id);
            }
        });

        return $this->echoJson($response, [
            'ret' => 1,
            'data' => 'ok',
        ]);
    }

    public function addAliveIp($request, $response, $args)
    {
        $params = $request->getQueryParams();

        $data = $request->getParam('data');
        $node_id = $params['node_id'];
        if ($node_id == '0') {
            $node = Node::where('node_ip', $_SERVER['REMOTE_ADDR'])->first();
            $node_id = $node->id;
        }
        $node = Node::find($node_id);

        if ($node == null) {
            $res = [
                'ret' => 0
            ];
            return $this->echoJson($response, $res);
        }
        if (count($data) > 0) {
            foreach ($data as $log) {
                $ip = $log['ip'];
                $userid = $log['user_id'];

                // log
                $ip_log = new Ip();
                $ip_log->userid = $userid;
                $ip_log->nodeid = $node_id;
                $ip_log->ip = $ip;
                $ip_log->datetime = time();
                $ip_log->save();
            }
        }

        $res = [
            'ret' => 1,
            'data' => 'ok',
        ];
        return $this->echoJson($response, $res);
    }

    public function addDetectLog($request, $response, $args)
    {
//        $params = $request->getQueryParams();
//
//        $data = $request->getParam('data');
//        $node_id = $params['node_id'];
//        if ($node_id == '0') {
//            $node = Node::where('node_ip', $_SERVER['REMOTE_ADDR'])->first();
//            $node_id = $node->id;
//        }
//        $node = Node::find($node_id);
//
//        if ($node == null) {
//            $res = [
//                'ret' => 0
//            ];
//            return $this->echoJson($response, $res);
//        }
//
//        if (count($data) > 0) {
//            foreach ($data as $log) {
//                $list_id = $log['list_id'];
//                $user_id = $log['user_id'];
//
//                // log
//                $detect_log = new DetectLog();
//                $detect_log->user_id = $user_id;
//                $detect_log->list_id = $list_id;
//                $detect_log->node_id = $node_id;
//                $detect_log->datetime = time();
//                $detect_log->save();
//            }
//        }

        $res = [
            'ret' => 1,
            'data' => 'ok',
        ];
        return $this->echoJson($response, $res);
    }

    private function logOnlineUser($node_id, $data)
    {
        $online_log = new NodeOnlineLog();
        $online_log->node_id = $node_id;
        $online_log->online_user = count($data);
        $online_log->log_time = time();
        $online_log->save();
    }
}
