<?php

namespace App\Services;

final class ShardManager
{
    /** @var string[] */
    private array $shards = ['mysql_shard_0', 'mysql_shard_1'];

    public function connectionForHash(string $hash): string
    {
        // stable shard choice => shard = crc32(hash) % N
        $idx = (int) (sprintf('%u', crc32($hash)) % count($this->shards));
        return $this->shards[$idx];
    }

    /** for running commands across shards */
    public function allShardConnections(): array
    {
        return $this->shards;
    }
}
