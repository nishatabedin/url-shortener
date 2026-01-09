<?php

namespace App\Console\Commands;

use App\Services\ShardManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;

final class ShardsMigrate extends Command
{
    protected $signature = 'shards:migrate {--fresh}';
    protected $description = 'Run migrations on each shard DB';

    public function handle(ShardManager $sm): int
    {
        foreach ($sm->allShardConnections() as $conn) {
            $this->info("Migrating shard: {$conn}");

            Config::set('database.default', $conn);

            Artisan::call($this->option('fresh') ? 'migrate:fresh' : 'migrate', [
                '--force' => true,
            ]);

            $this->output->write(Artisan::output());
        }

        return self::SUCCESS;
    }
}
