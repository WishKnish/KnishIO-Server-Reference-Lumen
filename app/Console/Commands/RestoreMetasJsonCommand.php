<?php
/**
 *
 * PHP version >= 7.0
 *
 * @category Console_Command
 * @package  App\Console\Commands
 */

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use WishKnish\KnishIO\Models\Molecule;

/**
 * Class RestoreMetasJsonCommand
 * @package App\Console\Commands
 */
class RestoreMetasJsonCommand extends Command {
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'metas_json:restore';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restore metas json command';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle (): void {
        try {
            set_time_limit( 9999 );
            $start_time = microtime( true );

            // Molecules
            $molecules = Molecule::orderBy( 'knishio_molecules.processed_at', 'asc' )
                ->get();
            $this->info( "Starting metas_json restore process..." );
            $count = 0;
            foreach ( $molecules as $molecule ) {
                foreach ( $molecule->atoms as $atom ) {
                    if ( $atom->restoreMetasJson() ) {
                        $this->info( "Restoring metas_json for " . $molecule->molecular_hash );
                        $count++;
                    }
                }
            }

            $this->info( "Restored metas_json: " . $count );
            $this->info( 'Time Spent: ' . round( microtime( true ) - $start_time, 2 ) );
        }
        catch ( Exception $e ) {
            $this->error( 'An error occurred:' . $e );
        }
    }

}
