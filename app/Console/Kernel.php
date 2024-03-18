<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\App;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        if (App::environment("testing")) {
            //Testing
            $schedule->command("inspire")->everyMinute()->appendOutputTo(storage_path("logs/inspire.log"));
            $schedule->command("order:sync-orders")->everyMinute()->timezone("Asia/Kathmandu");
            $schedule->command("order:remove-unmodified")->everyMinute()->timezone("Asia/Kathmandu");
        } else {
            // Production
            $schedule->command("order:sync-orders")->dailyAt("12:00")->timezone("Asia/Kathmandu");
            $schedule->command("order:remove-unmodified")->dailyAt("00:00")->timezone("Asia/Kathmandu");
        }
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
