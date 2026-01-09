<?php

namespace App\Console\Commands;

use App\Services\ShardManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class UrlsPurgeExpired extends Command
{
    protected $signature = 'urls:purge-expired {--chunk=2000}';
    protected $description = 'Delete expired URLs on all shards (active cleanup)';

    public function handle(ShardManager $sm): int
    {
        $chunk = (int) $this->option('chunk');

        foreach ($sm->allShardConnections() as $conn) {
            $this->info("Purging expired on {$conn}");

            while (true) {
                $rows = DB::connection($conn)->table('urls')
                    ->select('hash')
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<', now())
                    ->limit($chunk)
                    ->get();

                if ($rows->isEmpty()) break;

                $hashes = $rows->pluck('hash')->all();

                DB::connection($conn)->table('urls')->whereIn('hash', $hashes)->delete();

                // best-effort cache remove
                foreach ($hashes as $h) {
                    Cache::store('redis')->forget("url:{$h}");
                }
            }
        }

        return self::SUCCESS;
    }
}
