<?php

namespace App\Controllers\Mod_Mu;

use App\Controllers\BaseController;
use App\Services\Auth;
use App\Models\{
    Ip,
    Node,
    User,
    DetectLog,
    TrafficLog,
    NodeOnlineLog
};
use App\Utils\Tools;
use App\Services\RedisClient;

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

        /* ===============================
         * 获取节点
         * =============================== */

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
         * ⭐ 心跳改为 Redis（避免写 MySQL）
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
         * ⭐ 获取用户缓存
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

                // ⭐ gzip 压缩（极大降低 Redis 压力）
                $redis->setex(
                    $cacheKey,
                    120,
                    gzcompress(json_encode($users_raw))
                );
            }

            usleep(200000); // 等缓存生成
            $users_raw = $redis->get($cacheKey);
        }

        if ($users_raw !== null) {

            // 尝试解压
            $data = @gzuncompress($users_raw);

            if ($data !== false) {
                // gzip缓存
                $users = json_decode($data, true);
            } else {
                // 普通json缓存（兼容旧版本）
                $users = json_decode($users_raw, true);
            }

        } else {
            $users = [];
        }

        /* ===============================
         * ⭐ 节点权限筛选（高性能 foreach）
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
         * ⭐ 生成节点用户列表
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

            /* ---- 流量耗尽判断 ---- */

            if ($user['transfer_enable'] <= $user['u'] + $user['d']) {

                if ($_ENV['keep_connect'] === true) {
                    $user['node_speedlimit'] = 1;
                } else {
                    continue;
                }
            }

            /* ---- 密码处理 ---- */

            if ($node->sort === 1) {

                // AnyTLS
                $user['passwd'] = $user['uuid'];

            } else {

                $passwdArray = explode(':', $user['passwd']);

                if ($node->method === '2022-blake3-aes-128-gcm') {
                    $user['passwd'] = $passwdArray[0] ?? '';
                } else {
                    $user['passwd'] = $passwdArray[1] ?? '';
                }
            }

            $users[] = Tools::keyFilter($user, $key_list);
        }

        /* ===============================
         * 返回
         * =============================== */

        return $this->echoJson($response, [
            'ret' => 1,
            'data' => $users
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
