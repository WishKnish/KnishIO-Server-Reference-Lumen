<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get( '/', function () use ( $router ) {
    return view( 'index' );
} );

$router->get( '/test/clean', function () use ( $router ) {
    $metas = \WishKnish\KnishIO\Models\Meta::all();
    foreach ( $metas as $meta ) {
        if ( !$meta->atom ) {
            echo '<pre>No atom for meta position ' . $meta->position . ' (' . $meta->key . ': ' . $meta->value . ')</pre>';

            $meta->delete();
        }
    }

    $atoms = \WishKnish\KnishIO\Models\Atom::all();
    foreach ( $atoms as $atom ) {
        if ( !$atom->molecule ) {
            echo '<pre>No molecule for atom position ' . $atom->position . ' (' . $atom->meta_type . ': ' . $atom->meta_id . ')</pre>';

            $atom->delete();
        }
    }

    $molecules = \WishKnish\KnishIO\Models\Molecule::all();
    foreach ( $molecules as $molecule ) {
        if ( $molecule->atoms->count() === 0 ) {
            echo '<pre>No atoms for molecule hash ' . $molecule->molecular_hash . '</pre>';

            $cascades = $molecule->cascades;
            $molecule->bonds()->detach();
            $molecule->delete();

            foreach ( $cascades as $cascade ) {
                $cascade->chooseBonds();
            }
        }
    }

    echo 'Finished';
} );

$router->get( '/test/rebond', function () use ( $router ) {
    set_time_limit( 9999 );

    $molecules = \WishKnish\KnishIO\Models\Molecule::orderBy( 'created_at', 'asc' )->get();
    foreach ( $molecules as $molecule ) {
        $molecule->bonds()->detach();
    }

    foreach ( $molecules as $molecule ) {
        //if($molecule->bonds->count() === 0) {
        $molecule->chooseBonds();
        //}
    }

    dd( $molecules );
} );
