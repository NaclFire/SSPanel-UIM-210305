<?php

namespace App\Services\Gateway;

use App\Services\Auth;
use App\Models\Paylist;
use App\Services\Config;
use App\Services\View;

require_once("ZYGPay/epay_submit.class.php");
require_once("ZYGPay/epay_notify.class.php");
class ZYGPay extends AbstractPayment
{
	function isHTTPS()
    {
        define('HTTPS', false);
        if (defined('HTTPS') && HTTPS) return true;
        if (!isset($_SERVER)) return FALSE;
        if (!isset($_SERVER['HTTPS'])) return FALSE;
        if ($_SERVER['HTTPS'] === 1) {  //Apache
            return TRUE;
        } elseif ($_SERVER['HTTPS'] === 'on') { //IIS
            return TRUE;
        } elseif ($_SERVER['SERVER_PORT'] == 443) { //其他
            return TRUE;
        }
        return FALSE;
    }
    public function purchase($request, $response, $args)
    {
    	
		$user = Auth::getUser();
		$type = $request->getParsedBodyParam('type');
        $price = $request->getParam('price');
        $settings = Config::get("ZYGPay");
        if ($price < $settings['min_price']) {
			$return['ret'] = 0;
			$return['msg'] = "金额低于".$settings['min_price'].'元';
            return json_encode($return);
        }
		
        $pl = new Paylist();
        $pl->userid = $user->id;
        $pl->total = $price;
        $pl->tradeno = self::generateGuid();
        $pl->datetime = time(); // date("Y-m-d H:i:s");
        $pl->save();
        
        $alipay_config = array(
            'partner' => $settings['partner'],
            'key' => $settings['key'],
            'sign_type' => $settings['sign_type'],
            'input_charset' => $settings['input_charset'],
            'transport' => $settings['transport'],
            'apiurl' => $settings['apiurl']
        );
		$url_notify = Config::get("baseUrl") . '/payment/notify';  
        $url_return = (self::isHTTPS() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
		
        /**************************请求参数**************************/
        //商户订单号
        $out_trade_no = $pl->tradeno;
        //商品名称
        $name = $settings["subjects"];
        //付款金额
        $money = (float)$price;
        //站点名称
        $sitename = $settings['appname'];
        //构造要请求的参数数组，无需改动、、路由
        $parameter = array(
            "pid" => trim($alipay_config['partner']),
            "type" => $type,
            "notify_url"    => $url_notify,
            "return_url"    => $url_return."/user/code",
            "out_trade_no"  => $out_trade_no,
            "name"  => $name,
            "money" => $money,
            "sitename"  => $sitename
        );

        //建立请求
        $alipaySubmit = new AlipaySubmit($alipay_config);
        $result = $alipaySubmit->buildRequestForm($parameter);
        //$result = '<script>window.location.href="'.$url.'";</script>';
        $return['ret'] = 1;
        $return['url'] = $result;
        $return['pid'] = $pl->tradeno;
        $return['type'] = $type;
        return json_encode($return);
		
    }
	
    public function notify($request, $response, $args)
    {
        $pid = $_GET['out_trade_no'];
        unset($_GET['s']);
        $p = Paylist::where('tradeno', '=', $pid)->first();
        
        if ($p->status == 1) {
        	
            $success = 1;
        } else {
            $settings = Config::get("ZYGPay");
            $alipay_config = array(
                'partner' => $settings['partner'],
                'key' => $settings['key'],
                'sign_type' => $settings['sign_type'],
                'input_charset' => $settings['input_charset'],
                'transport' => $settings['transport'],
                'apiurl' => $settings['apiurl']
            );
		if ($_GET['type'] == "alipay") {
            $type = "支付宝";
        }elseif($_GET['type'] == "wxpay") {
            $type = "微信支付";
        }elseif($_GET['type'] == "qqpay") {
        	$type = "QQ支付";
        }else{
        	$type = "ZYGPay";
        }
            //计算得出通知验证结果
            $alipayNotify = new AlipayNotify($alipay_config);
            $verify_result = $alipayNotify->verifyNotify();

            if($verify_result) {

                if ($_GET['trade_status'] == 'TRADE_SUCCESS') {
                    $this->postPayment($_GET['out_trade_no'], $type);
                    $success = 1;
                }
                else {
                    $success = 0;
                }

            }
            else {
                $success = 0;
            }
        }
        if ($success==1){
            echo "success";
        }else{
            echo "fail";
        }
    }
    public function getReturnHTML($request, $response, $args)
    {

        $pid = $_GET['out_trade_no'];
        $p = Paylist::where('tradeno', '=', $pid)->first();
        $money = $p->total;
        if ($p->status == 1) {
            $success = 1;
        } else {
            $settings = Config::get("ZYGPay");
            $alipay_config = array(
                'partner' => $settings['partner'],
                'key' => $settings['key'],
                'sign_type' => $settings['sign_type'],
                'input_charset' => $settings['input_charset'],
                'transport' => $settings['transport'],
                'apiurl' => $settings['apiurl']
            );
		if ($_GET['type'] == "alipay") {
            $type = "支付宝";
        }elseif($_GET['type'] == "wxpay") {
            $type = "微信支付";
        }elseif($_GET['type'] == "qqpay") {
        	$type = "QQ支付";
        }else{
        	$type = "ZYGPay";
        }
            //计算得出通知验证结果
            $alipayNotify = new AlipayNotify($alipay_config);
            $verify_result = $alipayNotify->verifyNotify();

            if($verify_result) {

                if ($_GET['trade_status'] == 'TRADE_SUCCESS') {
                    $this->postPayment($_GET['out_trade_no'], $type);
                    $success = 1;
                }
                else {
                    $success = 0;
                }

            }
            else {
                $success = 0;
            }
        }
        return View::getSmarty()->assign('money', $money)->assign('success', $success)->fetch('user/pay_success.tpl');



    }
    public function getPurchaseHTML()
    {
        return '
								
										<div class="card-heading">支付方式:</div>
										<nav class="tab-nav margin-top-no">
											<ul class="nav nav-list">
											        <li class="nav-item">
                                                        <a class="nav-link waves-attach waves-effect type active" data-toggle="tab" data-pay="alipay"><img src="data:image/svg+xml;base64,CjxzdmcgdmVyc2lvbj0iMS4xIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rIiB4PSIwcHgiIHk9IjBweCIgdmlld0JveD0iMCAwIDEwMDAgMTAwMCIgZW5hYmxlLWJhY2tncm91bmQ9Im5ldyAwIDAgMTAwMCAxMDAwIiB4bWw6c3BhY2U9InByZXNlcnZlIj4KPG1ldGFkYXRhPiDnn6Lph4/lm77moIfkuIvovb0gOiBodHRwOi8vd3d3LnNmb250LmNuLyA8L21ldGFkYXRhPjxnPjxwYXRoIGQ9Ik05OTAsNjgwLjlWMTk4LjVDOTkwLDk0LjQsOTA1LjcsMTAsODAxLjUsMTBIMTk4LjVDOTQuNCwxMCwxMCw5NC4zLDEwLDE5OC41djYwMy4xQzEwLDkwNS42LDk0LjMsOTkwLDE5OC41LDk5MGg2MDMuMWM5Mi44LDAsMTY5LjktNjcsMTg1LjUtMTU1LjNjLTUwLTIxLjUtMjY2LjctMTE1LjEtMzc5LjQtMTY5Yy04NS44LDEwNC0xNzUuOCwxNjYuNS0zMTEuMywxNjYuNXMtMjI2LTgzLjMtMjE1LjEtMTg1LjZjNy4xLTY3LjIsNTMuMi0xNzYuNiwyNTMtMTU3LjhjMTA1LjMsMTAsMTUzLjUsMjkuNSwyMzkuNCw1Ny45YzIyLjEtNDAuNyw0MC42LTg1LjUsNTQuNi0xMzMuMkgyNDcuNXYtMzcuN2gxODguM3YtNjcuOEgyMDZ2LTQxLjVoMjI5Ljh2LTk3LjhjMCwwLDIuMi0xNS4zLDE5LTE1LjNoOTQuM3YxMTMuMWgyNDV2NDEuNWgtMjQ1djY3LjhoMTk5LjdjLTE4LjMsNzQuOC00Ni4yLDE0My41LTgxLDIwMy41QzcyNS45LDYwMC4yLDk5MCw2ODAuOSw5OTAsNjgwLjlMOTkwLDY4MC45TDk5MCw2ODAuOXogTTI4MS40LDc2Ny42Yy0xNDMuMywwLTE2NS44LTkwLjUtMTU4LjMtMTI4LjJzNDktODYuNywxMjguNi04Ni43YzkxLjUsMCwxNzMuNSwyMy40LDI3MS44LDcxLjNDNDU0LjUsNzE0LDM2OS41LDc2Ny42LDI4MS40LDc2Ny42TDI4MS40LDc2Ny42eiIgc3R5bGU9ImZpbGw6IzU2YWJlNCI+PC9wYXRoPjwvZz48L3N2Zz4gIA==" height="40"></img></a>
                                                    </li>
                                            
                                                    <li class="nav-item">
                                                        <a class="nav-link waves-attach waves-effect type" data-toggle="tab" data-pay="wxpay"><img src="data:image/svg+xml;base64,CjxzdmcgdmVyc2lvbj0iMS4xIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rIiB4PSIwcHgiIHk9IjBweCIgdmlld0JveD0iMCAwIDEwMDAgMTAwMCIgZW5hYmxlLWJhY2tncm91bmQ9Im5ldyAwIDAgMTAwMCAxMDAwIiB4bWw6c3BhY2U9InByZXNlcnZlIj4KPG1ldGFkYXRhPiDnn6Lph4/lm77moIfkuIvovb0gOiBodHRwOi8vd3d3LnNmb250LmNuLyA8L21ldGFkYXRhPjxnPjxwYXRoIGQ9Ik0zMTIuNiwzMTUuN2MtMTkuMSwwLTM4LjMsMTIuNi0zOC4zLDMxLjhjMCwxOSwxOS4yLDMxLjcsMzguMywzMS43YzE5LjEsMCwzMS43LTEyLjgsMzEuNy0zMS44QzM0NC4zLDMyOC4yLDMzMS43LDMxNS43LDMxMi42LDMxNS43TDMxMi42LDMxNS43TDMxMi42LDMxNS43eiBNNDkwLjMsMzc5LjFjMTkuMiwwLDMxLjgtMTIuOCwzMS44LTMxLjdjMC0xOS4xLTEyLjYtMzEuOC0zMS44LTMxLjhjLTE5LDAtMzguMSwxMi42LTM4LjEsMzEuOEM0NTIuMywzNjYuNCw0NzEuNCwzNzkuMSw0OTAuMywzNzkuMUw0OTAuMywzNzkuMUw0OTAuMywzNzkuMXogTTU3Mi45LDUwMGMtMTIuNiwwLTI1LjQsMTIuNi0yNS40LDI1LjNjMCwxMi44LDEyLjgsMjUuNCwyNS40LDI1LjRjMTkuMiwwLDMxLjgtMTIuNiwzMS44LTI1LjRDNjA0LjcsNTEyLjYsNTkyLjIsNTAwLDU3Mi45LDUwMEw1NzIuOSw1MDBMNTcyLjksNTAweiBNNzEyLjcsNTAwYy0xMi42LDAtMjUuMywxMi43LTI1LjMsMjUuNGMwLDEyLjgsMTIuOCwyNS40LDI1LjMsMjUuNGMxOS4xLDAsMzEuOC0xMi42LDMxLjgtMjUuNEM3NDQuNSw1MTIuNiw3MzEuOCw1MDAsNzEyLjcsNTAwTDcxMi43LDUwMEw3MTIuNyw1MDB6IE04MDEuNSwxMEgxOTguNEM5NC40LDEwLDEwLDk0LjQsMTAsMTk4LjR2NjAzLjJDMTAsOTA1LjYsOTQuMyw5OTAsMTk4LjQsOTkwaDYwMy4xYzkyLjcsMCwxNjkuOC02NywxODUuNS0xNTUuMmwyLjktMTUzLjlWMTk4LjRDOTkwLDk0LjQsOTA1LjYsMTAsODAxLjUsMTBMODAxLjUsMTBMODAxLjUsMTB6IE0zOTUuMiw2MzkuOGMtMzEuNywwLTU3LjItNi40LTg4LjktMTIuN2wtODguOCw0NC41bDI1LjQtNzYuNGMtNjMuNi00NC41LTEwMS43LTEwMS44LTEwMS43LTE3MS41YzAtMTIwLjksMTE0LjQtMjE2LDI1NC4xLTIxNmMxMjQuOSwwLDIzNC4zLDc2LjEsMjU2LjMsMTc4LjRjLTguMi0wLjktMTYuNC0xLjUtMjQuNS0xLjVjLTEyMC43LDAtMjE1LjksOTAtMjE1LjksMjAxYzAsMTguNiwyLjksMzYuNCw3LjgsNTMuM0M0MTEsNjM5LjQsNDAzLjEsNjM5LjgsMzk1LjIsNjM5LjhMMzk1LjIsNjM5LjhMMzk1LjIsNjM5Ljh6IE03NjkuNyw3MjguOGwxOS4yLDYzLjVsLTY5LjctMzguM2MtMjUuNCw2LjQtNTAuOSwxMi43LTc2LjIsMTIuN2MtMTIwLjksMC0yMTYtODIuNS0yMTYtMTg0LjNjMC0xMDEuNiw5NS4xLTE4NC4zLDIxNi0xODQuM2MxMTQuMSwwLDIxNS44LDgyLjgsMjE1LjgsMTg0LjNDODU4LjgsNjM5LjgsODIwLjgsNjkwLjUsNzY5LjcsNzI4LjhMNzY5LjcsNzI4LjhMNzY5LjcsNzI4Ljh6IiBzdHlsZT0iZmlsbDojMTFjZDZlIj48L3BhdGg+PC9nPjwvc3ZnPiAg" height="40"></img></a>
                                                    </li>
                                     
                                                    <li class="nav-item">
                                                        <a class="nav-link waves-attach waves-effect type" data-toggle="tab" data-pay="qqpay"><img src="data:image/png;base64,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" height="40"></img></a>
                                                    </li>
                                                    
                                            </ul>
                                            
                                       	</nav>
										<div class="form-group form-group-label">
											<label class="floating-label" for="amount">金额</label>
											<input class="form-control" id="amount" type="text">
										</div>
									   <hr>
										<div class="card-action-btn pull-left">
                                            <button class="btn btn-primary submit-amounth" id="ZYGPaypay" >充值</button>
                                        </div>
                                    
                        
';
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
