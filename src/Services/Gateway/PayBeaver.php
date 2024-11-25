<?php

/**
 * Created by PayBeaver <merchant.paybeaver.com>
 * Version: 2020-12-06
 */

namespace App\Services\Gateway;

use App\Services\View;
use App\Services\Auth;
use App\Services\Config;
use App\Models\Paylist;

class PayBeaver extends AbstractPayment
{
    private $_theme;

    public function __construct($theme = 'uim') {
        $this->_theme = $theme;
    }

    private function buildQuery($data)
    {
        ksort($data);
        return http_build_query($data);
    }

    private function sign($data)
    {
        return md5($data . Config::get('paybeaver_app_secret'));
    }

    private function verify($data, $signature)
    {
    	unset($data['sign']);
        $mySign = $this->sign($this->buildQuery($data));
        return $mySign === $signature;
    }

    public function post($data)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, Config::get('paybeaver_url') . '/v1/gateway/fetch');
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        $data = curl_exec($curl);
        curl_close($curl);
        return $data;
    }


    public function purchase($request, $response, $args)
    {
        $price = $request->getParam('amount');
        if ($price <= 0) {
            return json_encode(['code' => -1, 'msg' => '非法的金额.']);
        }
        $user = Auth::getUser();
        $pl = new Paylist();
        $pl->userid = $user->id;
        $pl->total = $price;
        $pl->tradeno = self::generateGuid();
        $pl->save();
        $data['app_id'] = Config::get('paybeaver_app_id');
        $data['out_trade_no'] = $pl->tradeno;
        $data['total_amount'] = (int)($price * 100);
        $data['notify_url'] = Config::get('baseUrl') . '/payment/notify';
        $data['return_url'] = Config::get('baseUrl') . '/user/payment/return?out_trade_no=' . $pl->tradeno;
        $params = $this->buildQuery($data);
        $data['sign'] = $this->sign($params);
    	$result = json_decode($this->post($data), true);
    	if (!isset($result['data'])) {
    		return json_encode(['code' => -1, 'msg' => '支付网关处理失败']);
    	}
        $result['pid'] = $pl->tradeno;
        return json_encode(['url' => $result['data']['pay_url'], 'code' => 0, 'pid' => $pl->tradeno]);
    }

    public function notify($request, $response, $args)
    {
    	file_put_contents(BASE_PATH . '/storage/paybeaver.log', json_encode($request->getParams())."\r\n", FILE_APPEND);
    	if (!$this->verify($request->getParams(), $request->getParam('sign'))) {
    		die('FAIL');
    	}
    	$this->postPayment($request->getParam('out_trade_no'), 'PayBeaver');
    	die('SUCCESS');
    }

    public function getPurchaseHTML()
    {
        if ($this->_theme === 'malio') return 1;
    	return View::getSmarty()->fetch('user/paybeaver.tpl');
    }

    public function getReturnHTML($request, $response, $args)
    {
        if ($this->_theme === 'cur') return $this->getCurReturnHTML($request, $response, $args);

        header('Location:/user/code');
    }

    public function getCurReturnHTML($request, $response, $args)
    {
        $pid = $_GET['out_trade_no'];
        $p = Paylist::where('tradeno', '=', $pid)->first();
        $money = $p->total;
        if ($p->status === 1) {
            $success = 1;
        }
        return View::getSmarty()
            ->assign('money', $money)
            ->assign('success', $success)
            ->fetch('user/pay_success.tpl');
    }

    public function getStatus($request, $response, $args)
    {
        $return = [];
        $p = Paylist::where('tradeno', $_POST['pid'])->first();
        $return['ret'] = 1;
        $return['result'] = $p->status;
        return json_encode($return);
    }
}
