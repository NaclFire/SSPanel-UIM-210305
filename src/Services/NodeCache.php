<?php

namespace App\Services;

class NodeCache
{
    public static function cacheNodeUsers($node)
    {
        $redis = new RedisClient();

        $users = User::where(function ($query) use ($node) {

            $query->where(function ($query1) use ($node) {

                if ($node->node_group != 0) {
                    $query1->where('class', '>=', $node->node_class)
                        ->where('node_group', $node->node_group);
                } else {
                    $query1->where('class', '>=', $node->node_class);
                }

            })->orWhere('is_admin', 1);

        })
            ->where('enable', 1)
            ->where('expire_in', '>', date('Y-m-d H:i:s'))
            ->get();

        $redis->setex(
            "node_users:{$node->id}",
            120,
            json_encode($users)
        );
    }
}
