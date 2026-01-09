<?php

namespace App\Services;

use App\Support\Base62;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

final class KgsService
{
    public function __construct(
        private readonly string $poolKey,
        private readonly int $poolMin,
        private readonly int $poolTarget,
    ) {}

    public function reserveOne(): string
    {
        // Fast path: in-memory pool (Redis list)
        $key = Redis::rpop($this->poolKey);
        if (is_string($key) && $key !== '') {
            $this->markUsed($key);
            return $key;
        }

        // Fallback: reserve from DB safely (transaction + row lock)
        $key = $this->reserveFromDb(1)[0] ?? null;
        if (!$key) {
            // Ensure pool then try again
            $this->ensurePool();
            $key = $this->reserveFromDb(1)[0] ?? null;
        }

        if (!$key) {
            throw new \RuntimeException('No keys available');
        }

        $this->markUsed($key);
        return $key;
    }

    public function ensurePool(): void
    {
        $len = (int) Redis::llen($this->poolKey);
        if ($len >= $this->poolMin) return;

        // Generate enough to reach target (DB first, then push to Redis)
        $toCreate = max(0, $this->poolTarget - $len);
        if ($toCreate <= 0) return;

        $keys = $this->generateKeys($toCreate);

        // Insert keys as unused
        DB::transaction(function () use ($keys) {
            $rows = array_map(fn ($k) => [
                'key' => $k,
                'status' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ], $keys);

            // chunk insert for big sizes
            foreach (array_chunk($rows, 2000) as $chunk) {
                DB::table('short_keys')->insert($chunk);
            }
        });

        // Push to Redis pool
        foreach ($keys as $k) {
            Redis::lpush($this->poolKey, $k);
        }
    }

    private function generateKeys(int $count): array
    {
        // Counter is the uniqueness guarantee (no collision)
        return DB::transaction(function () use ($count) {
            // Lock the single counter row
            $row = DB::table('key_counters')->where('id', 1)->lockForUpdate()->first();
            $start = (int) $row->value + 1;
            $end = $start + $count - 1;

            DB::table('key_counters')->where('id', 1)->update([
                'value' => $end,
                'updated_at' => now(),
            ]);

            $keys = [];
            for ($i = $start; $i <= $end; $i++) {
                $keys[] = Base62::encode($i);
            }
            return $keys;
        });
    }

    private function reserveFromDb(int $count): array
    {
        // MySQL 8 supports SKIP LOCKED: multiple KGS workers can run safely.
        return DB::transaction(function () use ($count) {
            $rows = DB::table('short_keys')
                ->select('key')
                ->where('status', 0)
                ->orderBy('id')
                ->limit($count)
                ->lockForUpdate()
                ->get();

            $keys = $rows->pluck('key')->all();
            if (!$keys) return [];

            DB::table('short_keys')
                ->whereIn('key', $keys)
                ->update(['reserved_at' => now(), 'updated_at' => now()]);

            return $keys;
        });
    }

    private function markUsed(string $key): void
    {
        DB::table('short_keys')
            ->where('key', $key)
            ->update([
                'status' => 1,
                'used_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
