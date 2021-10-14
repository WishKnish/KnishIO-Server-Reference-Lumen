<?php

namespace App\Console;

use App\Console\Commands\RestoreMetasJsonCommand;
use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;
use Laravelista\LumenVendorPublish\VendorPublishCommand;

class Kernel extends ConsoleKernel {
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        VendorPublishCommand::class, Commands\RebondMoleculesCommand::class, Commands\CleanMoleculesCommand::class, Commands\BenchmarkCommand::class, Commands\MoleculeMetaCommand::class, RestoreMetasJsonCommand::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param Schedule $schedule
     *
     * @return void
     */
    protected function schedule ( Schedule $schedule ): void {
        //
    }
}
