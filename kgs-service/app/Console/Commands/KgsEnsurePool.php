<?php

namespace App\Console\Commands;

use App\Services\KgsService;
use Illuminate\Console\Command;

final class KgsEnsurePool extends Command
{
    protected $signature = 'kgs:ensure-pool';
    protected $description = 'Ensure Redis pool has enough keys';

    public function handle(KgsService $kgs): int
    {
        $kgs->ensurePool();
        $this->info('Pool ensured.');
        return self::SUCCESS;
    }
}
