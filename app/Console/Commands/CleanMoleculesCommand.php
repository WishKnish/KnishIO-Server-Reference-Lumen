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
class CleanMoleculesCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'molecule:clean';

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

            $metas = \WishKnish\KnishIO\Models\Meta::all();
            foreach ( $metas as $meta ) {
                if ( !$meta->atom ) {
                    $this->info( 'No atom for meta position ' . $meta->position . ' (' . $meta->key . ': ' . $meta->value . ')' );

                    $meta->delete();
                }
            }

            $atoms = \WishKnish\KnishIO\Models\Atom::all();
            foreach ( $atoms as $atom ) {
                if ( !$atom->molecule ) {
                    $this->info( 'No molecule for atom position ' . $atom->position . ' (' . $atom->meta_type . ': ' . $atom->meta_id . ')' );

                    $atom->delete();
                }
            }

            $molecules = \WishKnish\KnishIO\Models\Molecule::all();
            foreach ( $molecules as $molecule ) {
                if ( $molecule->atoms->count() === 0 ) {

                    $this->info( 'No atoms for molecule hash ' . $molecule->molecular_hash );

                    $molecule->bonds()->detach();
                    $molecule->delete();

                    /*
                    foreach ( $cascades as $cascade ) {
                    $cascade->chooseBonds();
                    }
                    */
                }
            }

            $this->info( 'All molecules have been cleaned' );
        }
        catch ( Exception $e ) {
            $this->error( 'An error occurred' );
        }
    }
}
