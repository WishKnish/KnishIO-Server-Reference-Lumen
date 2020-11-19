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
use PHPUnit\Framework\Assert;
use WishKnish\KnishIO\Helpers\TimeLogger;


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
            $start_time = microtime( true );

            $molecules = \WishKnish\KnishIO\Models\Molecule::orderBy( 'knishio_molecules.processed_at', 'asc' )->get();
            $this->info( 'Detaching bonds...' );

            \DB::table( 'knishio_bonds' )->truncate();

            $cell_counts = [];
            $cell_origins = [];




            // --- Cell molecules initialization
            $all_cell_molecules = \WishKnish\KnishIO\Models\Molecule::whereIn( 'status', [ 'accepted', 'broadcasted' ] )
                ->orderBy( 'knishio_molecules.processed_at', 'desc' )
                ->get();
            $cell_molecules = [];
            foreach( $all_cell_molecules as $molecule ) {
                if ( !isset( $cell_molecules[ $molecule->cell_slug ] ) ) {
                    $cell_molecules[ $molecule->cell_slug ] = [];
                }
                $cell_molecules[ $molecule->cell_slug ][] = $molecule;
            }
            // ---


            $bonds = [];
            foreach ( $molecules as $index => $molecule ) {

                TimeLogger::begin('molecule_'.$index);

                $origin = null;
                $bond1 = null;
                $bond2 = null;
                $cell_slug = $molecule->cell_slug ?: 'N/A';

                // Incrementing number of molecules processed in this cell
                if ( !isset( $cell_counts[ $cell_slug ] ) ) {
                    $cell_counts[ $cell_slug ] = 0;
                }
                $cell_counts[ $cell_slug ]++;

                // Special case on the first molecule of the cell
                if ( $cell_counts[ $cell_slug ] === 1 ) {

                    TimeLogger::begin('First');

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

                    TimeLogger::end('First');
                } else {

                    TimeLogger::begin('Origin');

                    $this->info( 'Searching for origin for molecule ' . $molecule->molecular_hash );

                    // Find an origin molecule
                    $new_origin = null;
                    if ( isset( $cell_molecules[ $molecule->cell_slug ] ) ) {
                        foreach ( $cell_molecules[ $molecule->cell_slug ] as $cell_molecule ) {
                            if ( $cell_molecule->molecular_hash !== $molecule->molecular_hash &&
                                isset( $bonds[ $cell_molecule->molecular_hash ] )
                            ) {
                                $new_origin = $cell_molecule;
                                break;
                            }
                        }
                    }
                    $origin = $new_origin;

                    // Choosing the newest molecule that belongs to this cell
                    /*
                    $origin = \WishKnish\KnishIO\Models\Molecule::query()
                        ->select('knishio_molecules.*')
                        ->has( 'cascades' )
                        ->where( 'knishio_molecules.molecular_hash', '!=', $molecule->molecular_hash )
                        ->where( 'cell_slug', $molecule->cell_slug )
                        ->whereIn( 'status', [ 'accepted', 'broadcasted' ] )
                        ->orderBy( 'processed_at', 'desc' )
                        ->first();

                    try {
                        if (!$new_origin || !$origin) {
                            Assert::assertEquals(is_null($new_origin), is_null($origin));
                        } else {
                            Assert::assertEquals($new_origin->molecular_hash, $origin->molecular_hash);
                        }
                    }
                    catch (\Exception $e) {
                        file_put_contents('bonds.txt', json_encode($bonds));
                        throw $e;
                    }
                    */

                    TimeLogger::end('Origin');
                }

                if ( !$origin ) {
                    $origin = $cell_origins[ $cell_slug ];
                }

                TimeLogger::begin('chooseBonds');
                $bond_hashes = $molecule->chooseBonds(
                    $origin,
                    $bond1 ? $bond1->molecular_hash : null,
                    $bond2 ? $bond2->molecular_hash : null
                );
                // Add bonds to the common list
                foreach( $bond_hashes as $bond_hash ) {
                    if ( !isset(  $bonds[ $bond_hash ] ) ) {
                        $bonds[ $bond_hash ] = [];
                    }
                    $bonds[ $bond_hash ][] = $molecule->molecular_hash;
                }

                // dump ($bonds);
                TimeLogger::end('chooseBonds');


                TimeLogger::end('molecule_'.$index);

            }

            $this->info( 'All molecules have been rebonded' );
            $this->info('Time Spent: ' . round(microtime( true ) - $start_time, 2) );
        }
        catch ( Exception $e ) {
            $this->error( 'An error occurred:' . $e );
        }
    }
}
