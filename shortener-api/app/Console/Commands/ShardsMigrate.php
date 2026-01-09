<?php

namespace App\Console\Commands;

use App\Services\ShardManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;

final class ShardsMigrate extends Command
{
    protected $signature = 'shards:migrate {--fresh}';
    protected $description = 'Run migrations on each shard DB (shard-only migrations)';

    public function handle(ShardManager $sm): int
    {
        foreach ($sm->allShardConnections() as $conn) {
            $this->info("Migrating shard: {$conn}");

            // switch default connection to shard
            Config::set('database.default', $conn);

            $cmd = $this->option('fresh') ? 'migrate:fresh' : 'migrate';

            Artisan::call($cmd, [
                '--path' => 'database/migrations/shards',
                '--force' => true,
            ]);

            $this->output->write(Artisan::output());
        }

        // restore default connection to primary (safe)
        Config::set('database.default', 'mysql_primary');

        return self::SUCCESS;
    }
}
