<?php

//Thanks to http://blog.csdn.net/jollyjumper/article/details/9823047

namespace App\Controllers;

use App\Models\{
    Link,
    User,
    UserSubscribeLog
};
use App\Utils\{Subcribe, URL, Tools, AppURI, ConfRender};
use voku\helper\AntiXSS;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\{
    Request,
    Response
};

/**
 *  LinkController
 */
class LinkController extends BaseController
{
    public static $clientFlags = [
        'clash' => ['meta', 'verge', 'flclash', 'nekobox', 'clashmetaforandroid', 'stash'],
        'v2rayn' => ['general', 'v2rayn', 'v2rayng', 'passwall', 'ssrplus', 'sagernet', 'shadowrocket'],
        'quantumult' => ['quantumult%20x', 'quantumult-x'],
        'loon' => ['loon'],
        'shadowsocks' => ['shadowsocks'],
        'surge' => ['surge'],
        'singbox' => ['sing-box', 'hiddify', 'sfm'],
        'surfboard' => ['surfboard'],
    ];

    public static function GenerateRandomLink()
    {
        for ($i = 0; $i < 10; $i++) {
            $token = Tools::genRandomChar(16);
            $Elink = Link::where('token', $token)->first();
            if ($Elink == null) {
                return $token;
            }
        }

        return "couldn't alloc token";
    }

    /**
     * @param int $userid
     */
    public static function GenerateSSRSubCode(int $userid): string
    {
        $Elink = Link::where('userid', $userid)->first();
        if ($Elink != null) {
            return $Elink->token;
        }
        $NLink = new Link();
        $NLink->userid = $userid;
        $NLink->token = self::GenerateRandomLink();
        $NLink->save();

        return $NLink->token;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array $args
     */
    public static function GetContent($request, $response, $args)
    {
        echo '订阅开始';
        if (!$_ENV['Subscribe']) {
            return null;
        }

        $token = $args['token'];

        $Elink = Link::where('token', $token)->first();
        if ($Elink == null) {
            return null;
        }

        $user = $Elink->getUser();
        if ($user == null) {
            return null;
        }

        $opts = $request->getQueryParams();

        // Emoji
        $Rule['emoji'] = $_ENV['add_emoji_to_node_name'];

        // 显示流量以及到期时间等
        $Rule['extend'] = $_ENV['enable_sub_extend'];

        // 订阅类型
        $subscribe_type = '';

        $sub_type_array = ['clash', 'sub'];
        $hasSubType = false;
        foreach ($sub_type_array as $key) {
            if (isset($opts[$key])) {
                $query_value = $opts[$key];
                if ($query_value != '0' && $query_value != '') {
                    // 兼容代码开始
                    if ($key == 'sub' && $query_value != 3) {
                        $query_value = 3;
                    }
                    // 兼容代码结束
                    $SubscribeExtend = self::getSubscribeExtend($key, $query_value);
                    $filename = $SubscribeExtend['filename'] . '_' . time() . '.' . $SubscribeExtend['suffix'];
                    $subscribe_type = $SubscribeExtend['filename'];
                    $class = ('get' . $SubscribeExtend['class']);
                    $content = self::$class($user, $query_value, $opts, $Rule);
                    $hasSubType = true;
                    break;
                }
            }
        }
        if (!$hasSubType) {
            $userAgent = strtolower($request->getHeader('User-Agent')[0] ?? '');
            echo '$userAgent = ' . $userAgent . PHP_EOL;
            $client = 'unknown';
            foreach (self::$clientFlags as $type => $flags) {
                foreach ($flags as $flag) {
                    if (strpos($userAgent, $flag) !== false) {
                        $client = $type;
                        break;
                    }
                }
            }
//            echo '$client = '.$client.PHP_EOL;
            $SubscribeExtend = self::getSubscribeExtend($client);
            $filename = $SubscribeExtend['filename'] . '_' . time() . '.' . $SubscribeExtend['suffix'];
            $subscribe_type = $SubscribeExtend['filename'];
            $class = ('get' . $SubscribeExtend['class']);
            $content = self::$class($user, 0, $opts, $Rule);
        }
        $getBody = self::getBody($user, $response, $content, $filename);
        // 记录订阅日志
        if ($_ENV['subscribeLog'] === true) {
            self::Subscribe_log($user, $subscribe_type, $request->getHeaderLine('User-Agent'));
        }

        return $getBody;
    }

    /**
     * 获取订阅类型的文件名
     *
     * @param string $type 订阅类型
     * @param string|null $value 值
     *
     * @return array
     */
    public static function getSubscribeExtend($type, $value = null)
    {
        switch ($type) {
            case 'sub':
                $return = self::getSubscribeExtend('v2rayn');
                break;
            case 'clash':
                $return = [
                    'filename' => 'Clash',
                    'suffix' => 'yaml',
                    'class' => 'Clash'
                ];
                break;
            case 'v2rayn':
                $return = [
                    'filename' => 'V2RayN',
                    'suffix' => 'txt',
                    'class' => 'Sub'
                ];
                break;
            default:
                $return = [
                    'filename' => 'V2Ray',
                    'suffix' => 'txt',
                    'class' => 'Sub'
                ];
                break;
        }
        return $return;
    }

    /**
     * 记录订阅日志
     *
     * @param User $user 用户
     * @param string $type 订阅类型
     * @param string $ua UA
     *
     * @return void
     */
    private static function Subscribe_log($user, $type, $ua)
    {
        $log = new UserSubscribeLog();
        $log->user_name = $user->user_name;
        $log->user_id = $user->id;
        $log->email = $user->email;
        $log->subscribe_type = $type;
        $log->request_ip = $_SERVER['REMOTE_ADDR'];
        $log->request_time = date('Y-m-d H:i:s');
        $antiXss = new AntiXSS();
        $log->request_user_agent = $antiXss->xss_clean($ua);
        $log->save();
    }

    /**
     * 响应内容
     *
     * @param User $user
     * @param array $response
     * @param string $content 订阅内容
     * @param string $filename 文件名
     */
    public static function getBody($user, $response, $content, $filename): ResponseInterface
    {
        $response = $response
            ->withHeader(
                'Content-type',
                ' application/octet-stream; charset=utf-8'
            )
            ->withHeader(
                'Cache-Control',
                'no-store, no-cache, must-revalidate'
            )
            ->withHeader(
                'Content-Disposition',
                ' attachment; filename=' . $filename
            )
            ->withHeader(
                'Subscription-Userinfo',
                (' upload=' . $user->u
                    . '; download=' . $user->d
                    . '; total=' . $user->transfer_enable
                    . '; expire=' . strtotime($user->class_expire))
            );

        return $response->write($content);
    }

    /**
     * 订阅链接汇总
     *
     * @param User $user 用户
     * @param int $int 当前用户访问的订阅类型
     *
     * @return array
     */
    public static function getSubinfo($user, $int = 0)
    {
        if ($int == 0) {
            $int = '';
        }
        $userapiUrl = $_ENV['subUrl'] . self::GenerateSSRSubCode($user->id);
        $return_info = [
            'link' => '',
            // sub
            'ss' => '?sub=2',
            'ssr' => '?sub=1',
            'v2ray' => '?sub=3',
            'trojan' => '?sub=4',
            // apps
            'ssa' => '?list=ssa',
            'clash' => '?clash=1',
            'clash_provider' => '?list=clash',
            'clashr' => '?clash=2',
            'clashr_provider' => '?list=clashr',
            'surge' => '?surge=' . $int,
            'surge_node' => '?list=surge',
            'surge2' => '?surge=2',
            'surge3' => '?surge=3',
            'surge4' => '?surge=4',
            'surfboard' => '?surfboard=1',
            'quantumult' => '?quantumult=' . $int,
            'quantumult_v2' => '?list=quantumult',
            'quantumult_sub' => '?quantumult=2',
            'quantumult_conf' => '?quantumult=3',
            'quantumultx' => '?list=quantumultx',
            'shadowrocket' => '?list=shadowrocket',
            'kitsunebi' => '?list=kitsunebi'
        ];

        return array_map(
            function ($item) use ($userapiUrl) {
                return ($userapiUrl . $item);
            },
            $return_info
        );
    }

    public static function getListItem($item, $list)
    {
        $return = null;
        switch ($list) {
            case 'ss':
                $return = AppURI::getSSURI($item, 1);
                break;
            case 'ssr':
                $return = AppURI::getItemUrl($item, 0);
                break;
            case 'ssa':
                $return = AppURI::getSSJSON($item);
                break;
            case 'surge':
                $return = AppURI::getSurgeURI($item, 3);
                break;
            case 'clash':
                $return = AppURI::getClashURI($item);
                break;
            case 'clashr':
                $return = AppURI::getClashURI($item, true);
                break;
            case 'v2rayn':
                $return = AppURI::getV2RayNURI($item);
                break;
            case 'trojan':
                $return = AppURI::getTrojanURI($item);
                break;
            case 'kitsunebi':
                $return = AppURI::getKitsunebiURI($item);
                break;
            case 'quantumult':
                $return = AppURI::getQuantumultURI($item, true);
                break;
            case 'quantumultx':
                $return = AppURI::getQuantumultXURI($item);
                break;
            case 'shadowrocket':
                $return = AppURI::getShadowrocketURI($item);
                break;
        }
        return $return;
    }

    public static function getListExtend($user, $list)
    {
        $return = [];
        $info_array = (count($_ENV['sub_message']) != 0 ? (array)$_ENV['sub_message'] : []);
        if (strtotime($user->expire_in) > time()) {
            if ($user->transfer_enable == 0) {
                $unusedTraffic = '请勿使用-剩余流量：0';
            } else {
                $unusedTraffic = '请勿使用-剩余流量：' . $user->unusedTraffic();
            }
            $expire_in = '请勿使用-过期时间：';
            if ($user->class_expire != '1989-06-04 00:05:00') {
                $userClassExpire = explode(' ', $user->class_expire);
                $expire_in .= $userClassExpire[0];
            } else {
                $expire_in .= '无限期';
            }
        } else {
            $unusedTraffic = '账户已过期，请续费后使用';
            $expire_in = '账户已过期，请续费后使用';
        }
        $info_array[] = $unusedTraffic;
        $info_array[] = $expire_in;
        $baseUrl = explode('//', $_ENV['baseUrl'])[1];
        $baseUrl = explode('/', $baseUrl)[0];
        $Extend = [
            'remark' => '',
            'type' => 'vmess',
            'add' => $baseUrl,
            'address' => $baseUrl,
            'port' => 10086,
            'method' => 'chacha20-ietf',
            'passwd' => $user->passwd,
            'id' => $user->getUuid(),
            'aid' => 0,
            'net' => 'tcp',
            'headerType' => 'none',
            'host' => '',
            'path' => '/',
            'tls' => '',
            'protocol' => 'origin',
            'protocol_param' => '',
            'obfs' => 'plain',
            'obfs_param' => '',
            'group' => $_ENV['appName']
        ];
        foreach ($info_array as $remark) {
            $Extend['remark'] = $remark;
            $out = self::getListItem($Extend, $list);
            if ($out !== null) $return[] = $out;
        }
        return $return;
    }


    /**
     * Clash 配置
     *
     * @param User $user 用户
     * @param int $clash 订阅类型
     * @param array $opts request
     * @param array $Rule 节点筛选规则
     *
     * @return string
     */
    public static function getClash($user, $clash, $opts, $Rule)
    {
        $ssr_support = ($clash == 2 ? true : false);
        $items = URL::getAllItems($user, $Rule);
        $Proxys = [];
        foreach ($items as $item) {
            $Proxy = AppURI::getClashURI($item, $ssr_support);
            if ($Proxy !== null) {
                $Proxys[] = $Proxy;
            }
        }
        if (isset($opts['profiles']) && in_array($opts['profiles'], array_keys($_ENV['Clash_Profiles']))) {
            $Profiles = $opts['profiles'];
        } else {
            $Profiles = $_ENV['Clash_DefaultProfiles']; // 默认策略组
        }

        return ConfController::getClashConfs($user, $Proxys, $_ENV['Clash_Profiles'][$Profiles]);
    }

    /**
     * 通用订阅，ssr & v2rayn
     *
     * @param User $user 用户
     * @param int $sub 订阅类型
     * @param array $opts request
     * @param array $Rule 节点筛选规则
     *
     * @return string
     */
    public static function getSub($user, $sub, $opts, $Rule)
    {
        $return_url = '';
        // 拼接流量、到期时间、额外消息
        $getListExtend = $Rule['extend'] ? self::getListExtend($user, 'v2rayn') : [];
        // 获取所有节点
        $return_url .= URL::getAllUrl($user, $Rule);
        if ($Rule['extend']) {
            $return_url .= implode(PHP_EOL, $getListExtend) . PHP_EOL;
        }
        return base64_encode($return_url);
    }
}
