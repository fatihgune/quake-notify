<?php

namespace App\Console\Commands;

use App\Repositories\EarthquakeRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MainCommand extends Command
{
    protected $signature = 'quake:check';

    protected $description = 'Check if there\'s new records and send sms that contains records information if the conditions apply.';

    public function handle(): void
    {
        $repo = app()->make(EarthquakeRepository::class);
        $repo->init();
        Log::info('schedule ran');
    }
}
