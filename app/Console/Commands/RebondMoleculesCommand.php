<?php
/**
 *
 * PHP version >= 7.0
 *
 * @category Console_Command
 * @package  App\Console\Commands
 */

namespace App\Console\Commands;


use App\Post;

use Exception;
use Illuminate\Console\Command;


/**
 * Class deletePostsCommand
 *
 * @category Console_Command
 * @package  App\Console\Commands
 */
class RebondMoleculesCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'molecule:rebond';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rebond all molecules';


    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle ()
    {
        try {
            set_time_limit( 9999 );

            $molecules = \WishKnish\KnishIO\Models\Molecule::orderBy( 'created_at', 'asc' )->get();
            $this->info( 'Detaching bonds...' );
            foreach ( $molecules as $molecule ) {
                $molecule->bonds()->detach();
            }

            foreach ( $molecules as $molecule ) {
                //if($molecule->bonds->count() === 0) {
                $this->info( 'Rebonding ' . $molecule->molecular_hash );
                $molecule->chooseBonds();
                //}
            }

            $this->info( 'All molecules have been rebonded' );
        }
        catch ( Exception $e ) {
            $this->error( 'An error occurred' );
        }
    }
}
