<?php
/**
 * Created by PhpStorm.
 * User: tonyzou
 * Date: 2018/9/24
 * Time: 下午7:07
 */

namespace App\Services;

use App\Services\Config;
use App\Services\Gateway\{
    AopF2F,
    Codepay,
    DoiAMPay,
    TomatoPay,
    PaymentWall,
    ChenPay,
    SPay,
    TrimePay,
    YftPay,
    flyfoxpay,
    BitPayX,
    MaterialPay,
    ZYGPay,
    PayBeaver,
    Vmqpay
};

class Payment
{
    public static function getClient()
    {
        $method = Config::get("payment_system");
        switch ($method) {
            case ('bitpayx'):
                return new BitPayX(Config::get('bitpay_secret'));
            case("codepay"):
                return new Codepay();
            case("flyfoxpay"):
                return new flyfoxpay();
            case("doiampay"):
                return new DoiAMPay();
            case("tomatopay"):
                return new TomatoPay();
            case("paymentwall"):
                return new PaymentWall();
            case("spay"):
                return new SPay();
            case("f2fpay"):
                return new AopF2F();
            case("yftpay"):
                return new YftPay();
            case("chenAlipay"):
                return new ChenPay();
            case("trimepay"):
                return new TrimePay(Config::get('trimepay_secret'));
            case ('materialpay'):
                return new MaterialPay(Config::get('materialpay_secret'));
            case("ZYGPay"):
                return new ZYGPay();
            case ("paybeaver"):
                return new PayBeaver();
            case ("vmqpay"):
                return new Vmqpay();
            default:
                return NULL;
        }
    }

    public static function notify($request, $response, $args)
    {
        return self::getClient()->notify($request, $response, $args);
    }

    public static function returnHTML($request, $response, $args)
    {
        return self::getClient()->getReturnHTML($request, $response, $args);
    }

    public static function purchaseHTML()
    {
        if (self::getClient() != NULL) {
            return self::getClient()->getPurchaseHTML();
        } else {
            return '';
        }
    }

    public static function getStatus($request, $response, $args)
    {
        return self::getClient()->getStatus($request, $response, $args);
    }

    public static function purchase($request, $response, $args)
    {
        return self::getClient()->purchase($request, $response, $args);
    }
}
