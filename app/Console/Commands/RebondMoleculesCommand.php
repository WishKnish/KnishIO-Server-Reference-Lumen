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

            $molecules = \WishKnish\KnishIO\Models\Molecule::orderBy( 'knishio_molecules.processed_at', 'asc' )->get();
            $this->info( 'Detaching bonds...' );

            \DB::table( 'knishio_bonds' )->truncate();

            $cell_counts = [];
            $cell_origins = [];

            foreach ( $molecules as $index => $molecule ) {

                $origin = null;
                $bond1 = null;
                $bond2 = null;
                $cell_slug = $molecule->cell_slug ?: 'N/A';

                // Incrementing number of molecules processed in this cell
                if ( isset( $cell_counts[ $cell_slug ] ) ) {
                    $cell_counts[ $cell_slug ]++;
                } else {
                    $cell_counts[ $cell_slug ] = 1;
                }

                // Special case on the first molecule of the cell
                if ( $cell_counts[ $cell_slug ] === 1 ) {

                    // Setting this molecule as the origin for its cell
                    $cell_origins[ $cell_slug ] = $molecule;

                    // No cell, first molecule = origin
                    if ( $cell_slug === 'N/A' ) {
                        $this->info( 'Master origin detected on molecule ' . $molecule->molecular_hash );
                        continue;
                    }

                    // We haven't started processing this cell, so the first origin must be the master origin molecule
                    $this->info( 'Forcing master origin for molecule ' . $molecule->molecular_hash );
                    $origin = $cell_origins[ 'N/A' ];
                    $bond1 = $origin;
                    $bond2 = $origin->cascades()->inRandomOrder()->first();
                } else {

                    $this->info( 'Searching for origin for molecule ' . $molecule->molecular_hash );
                    // Choosing the newest molecule that belongs to this cell
                    $origin = \WishKnish\KnishIO\Models\Molecule::query()
                        ->where( 'molecular_hash', '!=', $molecule->molecular_hash )
                        ->where( 'cell_slug', $molecule->cell_slug )
                        ->whereIn( 'status', [ 'accepted', 'broadcasted' ] )
                        ->has( 'cascades' )
                        ->orderBy( 'processed_at', 'desc' )
                        ->first();
                }

                if ( !$origin ) {
                    $origin = $cell_origins[ $cell_slug ];
                }

                $this->info( 'Rebonding #' . $cell_counts[ $cell_slug ] . ' ' . $cell_slug . ' molecule ' . $molecule->molecular_hash . ' with origin ' . $origin->molecular_hash );

                $molecule->chooseBonds( $origin, $bond1 ? $bond1->molecular_hash : null, $bond2 ? $bond2->molecular_hash : null );

            }

            $this->info( 'All molecules have been rebonded' );
        }
        catch ( Exception $e ) {
            $this->error( 'An error occurred:' . $e );
        }
    }
}
