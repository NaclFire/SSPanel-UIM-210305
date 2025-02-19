<?php

namespace App\Models;

/**
 * Bought Model
 *
 * @property-read   int     $id             id
 * @property        int     $userid         User id
 * @property        int     $shopid         Shop id
 * @property        string  $datetime       Bought complete datetime
 * @property        int     $renew          Time to renew this bought
 * @property        string  $coupon         Coupon applied to this bought
 * @property        float   $price          Price after coupon applied
 * @property        float   $salesman_price Price after coupon applied
 * @property        bool    $is_notified    If this bought is notified for renew
 */
class Bought extends Model
{
    protected $connection = 'default';

    protected $table = 'bought';

    public function renew_date()
    {
        return date('Y-m-d H:i:s', $this->renew);
    }

    public function datetime()
    {
        return date('Y-m-d H:i:s', $this->datetime);
    }

    public function user(): ?User
    {
        $user = User::where('id', $this->userid)->first();
        if ($user == null) {
            $this->delete();
            return null;
        }
        return $user;
    }

    public function shop(): Shop
    {
        return Shop::where('id', $this->shopid)->first();
    }

    /*
     * 套餐已使用的天数
     */
    public function used_days(): int
    {
        return (int) ((time() - $this->datetime) / 86400);
    }

    /*
     * 是否有效期内
     */
    public function valid(): bool
    {
        $shop = $this->shop();
        if ($shop->use_loop()) {
            return (time() - $shop->reset_exp() * 86400 < $this->datetime);
        }
        return false;
    }

    /*
     * 下一次流量重置时间
     */
    public function reset_time($unix = false)
    {
        $shop = $this->shop();
        if ($shop->use_loop()) {
            $day = 24 * 60 * 60;
            $resetIndex = 1 +  (int)((time() - $this->datetime - $day) / ($shop->reset() * $day));
            $restTime = $resetIndex * $shop->reset() * $day + $this->datetime;
            $time = time() + ($day * 86400);
            return (!$unix ? date('Y-m-d', strtotime('+1 day', strtotime(date('Y-m-d', $restTime)))) : $time);
        }
        return (!$unix ? '-' : 0);
    }

    /*
     * 过期时间
     */
    public function exp_time($unix = false)
    {
        $shop = $this->shop();
        if ($shop->use_loop()) {
            $time = $this->datetime + ($shop->reset_exp() * 86400);
            return (!$unix ? date('Y-m-d H:i:s', $time) : $time);
        }
        return (!$unix ? '-' : 0);
    }
}
