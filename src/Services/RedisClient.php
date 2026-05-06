<?php

namespace App\Services;

use Predis\Client;

class RedisClient
{
    private $client;

    public function __construct()
    {
        $config = [
            'scheme'   => $_ENV['redis_scheme'],
            'host'     => $_ENV['redis_host'],
            'port'     => $_ENV['redis_port'],
            'password' => $_ENV['redis_password'],
            'database' => $_ENV['redis_database'],
        ];

        $this->client = new Client($config);
    }

    public function getClient()
    {
        return $this->client;
    }

    /* ======================
       基础
    ====================== */

    public function get($key)
    {
        return $this->client->get($key);
    }

    public function set($key, $value)
    {
        return $this->client->set($key, $value);
    }

    public function setex($key, $time, $value)
    {
        return $this->client->setex($key, $time, $value);
    }

    public function del($key)
    {
        return $this->client->del([$key]);
    }

    /* ======================
       ⭐ Hash
    ====================== */

    public function hincrby($key, $field, $value)
    {
        return $this->client->hincrby($key, $field, $value);
    }

    public function hgetall($key)
    {
        return $this->client->hgetall($key);
    }

    /* ======================
       ⭐ Set
    ====================== */

    public function sadd($key, $value)
    {
        return $this->client->sadd($key, [$value]);
    }

    public function smembers($key)
    {
        return $this->client->smembers($key);
    }

    public function srem($key, $value)
    {
        return $this->client->srem($key, $value);
    }

    /* ======================
       ⭐ String
    ====================== */

    public function incrby($key, $value)
    {
        return $this->client->incrby($key, $value);
    }

    public function expire($key, $time)
    {
        return $this->client->expire($key, $time);
    }

    /* ======================
       ⭐⭐⭐ Pipeline（核心）
    ====================== */

    public function pipeline($callback)
    {
        return $this->client->pipeline($callback);
    }
}
