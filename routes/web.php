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


use WishKnish\KnishIO\Client\Meta;
use WishKnish\KnishIO\Models\Atom;
use WishKnish\KnishIO\Models\Token;



// Custom schema => graphql
$router->get( 'schema.graphql', function() use ( $router ) {
    echo app(\WishKnish\KnishIO\Lighthouse\ASTBuilder::class)->doPrintSchema();
} );


// Custom schema => json-ld
$router->get( 'schema.jsonld', function() use ( $router ) {

    $metaContext = new \WishKnish\KnishIO\Models\Meta\MetaContext( 'local' );

    header( 'application/ld+json' );
    echo $metaContext->getJsonldObject()
        ->toJsonldSchema();
} );

// Custom schema overview
$router->get( 'schema', function() use ( $router ) {

    $metaContext = new \WishKnish\KnishIO\Models\Meta\MetaContext( 'local' );
    $jsonldObject = $metaContext->getJsonldObject();

    // Get only parent jsonld types @todo add cascade check to disable child with childs
    $jsonldTypes = [];
    foreach( $jsonldObject->graph() as $jsonldType ) {
        if ( $jsonldType->fields() ) {
            $jsonldTypes[] = $jsonldType;
        }
    }

    return view( 'schema/index', [ 'jsonldTypes' => $jsonldTypes ] );
} );

// Fields
$router->get( 'schema/{type}', function( $type ) use ( $router ) {

    $metaContext = new \WishKnish\KnishIO\Models\Meta\MetaContext( 'local' );
    $jsonldType = $metaContext->getJsonldObject()->graphType( $type );
    return view( 'schema/type', [ 'jsonldType' => $jsonldType,] );

} );


// Meta instance json-ld data
$router->get( 'scope/{metaType}/{metaId}.jsonld', function( $metaType, $metaId ) use ( $router ) {

    // Get a meta instance
    $atom = Atom::where( 'isotope', 'C' )
        ->where( 'meta_type', $metaType )
        ->where( 'meta_id', $metaId )
        ->first();
    if ( !$atom ) {
        throw new \Exception( 'Meta instance does not found.' );
    }

    // Get metas
    $metas = Meta::aggregateMeta($atom->metas);
    if ( !array_has( $metas, 'context' ) ) {
        throw new \Exception( 'Instance does not have a context.' );
    }

    // Get a model: @todo change this code to use resolver classes
    $model = null;
    switch( $atom->meta_type ) {
        case 'token':
            $model = Token::where( 'slug', $atom->meta_id )->first();
            break;
        case 'wallet':
            $model = \WishKnish\KnishIO\Models\Wallet::where( 'address', $atom->meta_id )->first();
            $model->token = $model->token_slug;
            $model->bundle = $model->bundle_hash;
            break;
        default:
            throw new \Exception( 'Unsupported meta type '. $atom->meta_type );
            break;
    }
    if ( !$model ) {
        throw new \Exception( 'Model does not found.' );
    }

    // All metas to output
    $metas = array_merge( $model->getAttributes(), $metas );

    // Get a meta context & filter aggregated metas
    header( 'application/ld+json' );
    $metaContext = \WishKnish\KnishIO\Models\Meta\MetaContext::find( array_get( $metas, 'context' ) );
    echo $metaContext->filter( $metas );

});


// @todo: temp routes - DELETD IT AFTER PEERING STABILIZATION
$router->get( '/peer/{type}', function ( $type ) use ( $router ) {

    if ( !env('KNISHIO_PEERING') ) {
        throw new \Exception( 'Peering does not enabled' );
    }

    $peers = [
        'frontrow.knish.io' => 'frontrow1',
        'frontrow2.knish.io' => 'frontrow2',
        'frontrow3.knish.io' => 'frontrow3',
        'frontrow4.knish.io' => 'frontrow4',
    ];

    // Peer host & slug
    $peer_host = request()->getHost();
    if ( !isset( $peers[ $peer_host ] ) ) {
        throw new \Exception( 'Undefined peer host.' );
    }
    $peer_slug = $peers[ $peer_host ];
    $target_peer = array_key_first( $peers );


    switch( $type ) {
        case 'create-molecule':

            $secret = \WishKnish\KnishIO\Client\Libraries\Crypto::generateSecret();

            // Defining client and authenticating the session
            $client = new \WishKnish\KnishIO\Client\KnishIOClient( url() . '/graphql' );
            $client->authentication( $secret );

            // Defining signing parameters
            $molecule = $client->createMolecule($client->secret());

            $metas = [];
            for ($meta_num = 0; $meta_num < 2; $meta_num++) {
                $metas['meta_' . $meta_num] = \WishKnish\KnishIO\Client\Libraries\Crypto::generateSecret(null, 64);
            }

            // Initializing molecule content
            $molecule->initMeta($metas, 'metaType', 'metaId' . random_int(0, 100));
            $molecule->sign();

            $query = $client->createMoleculeQuery( \WishKnish\KnishIO\Client\Query\QueryMoleculePropose::class, $molecule );

            $response = $query->execute();
            echo 'Molecule ['. $molecule->molecularHash .']: ' . ($response->success() ? 'success' : 'failure');

            break;
        case 'log':

            $log_file = storage_path( '/logs/lumen-' . date('Y-m-d') .'.log' );
            if ( !file_exists( $log_file ) ) {
                file_put_contents( $log_file, '' );
            }

            // Clear parameter
            if (\request()->exists('clear')) {
                file_put_contents( $log_file, '' );
                return redirect( 'peer/log' );
            }

            $logs = explode( "\n", file_get_contents( $log_file ) );

            $clear_link = '<a href="/peer/log?clear" onclick="return confirm(\'Are you really want to clear the log?\')">Clear</a>';

            die (
                $clear_link.
                '<pre>' . implode("\n", $logs) . '</pre>'
            );

            break;
        case 'clean':
            \WishKnish\KnishIO\Helpers\Cleaner::byPeer('node.knishio', false);
            echo 'Cleaned.';
            break;
        case 'bootstrap':

            $peerDB = new \WishKnish\KnishIO\Helpers\PeerDB( $peer_slug );
            echo '<pre>' . $peerDB->bootstrap( $target_peer, $peer_host, $peer_slug ) . '</pre>';

            break;
        case 'bootstrap-all':

            set_time_limit( 9999 );

            $scheme = array_get( parse_url( url() ), 'scheme', 'https' );

            foreach ( $peers as $peer_host => $peer_slug ) {
                echo '<pre>'
                    . 'Trying to clean ' . $peer_host . ' => ' . $scheme . '://' . $peer_host . '/peer/clean-db' .' => '
                    . file_get_contents( $scheme . '://' . $peer_host . '/peer/clean-db'  )
                    . '</pre>';
            }

            foreach ( $peers as $peer_host => $peer_slug ) {
                echo '<pre>'
                    . 'Trying to bootstrap ' . $peer_host . "\r\n"
                    . file_get_contents( $scheme . '://' . $peer_host . '/peer/bootstrap'  ) .
                    '</pre>';
            }

            break;
        case 'clean-db':

            \DB::delete('DELETE FROM knishio_access_tokens');
            \DB::delete('DELETE FROM knishio_atoms;');
            \DB::delete('DELETE FROM knishio_bonds;');
            \DB::delete('DELETE FROM knishio_bundles;');
            \DB::delete('DELETE FROM knishio_cells;');
            \DB::delete('DELETE FROM knishio_peers;');
            \DB::delete('DELETE FROM knishio_logs;');
            \DB::delete('DELETE FROM knishio_identifiers;');
            \DB::delete('DELETE FROM knishio_metas;');
            \DB::delete('DELETE FROM knishio_molecules;');
            \DB::delete('DELETE FROM knishio_tokens;');
            \DB::delete('DELETE FROM knishio_wallets;');
            \DB::delete('DELETE FROM knishio_wallet_bundles;');
            \DB::delete('DELETE FROM knishio_jobs;');
            \DB::delete('DELETE FROM knishio_failed_jobs;');

            /*

            // Remove by bundle hashes
            $bundle_hashes = \DB::table('knishio_molecules')
                ->get()
                ->pluck('bundle_hash');
            \WishKnish\KnishIO\Helpers\Cleaner::byBundleHash($bundle_hashes);

            */

            echo 'Cleaned.';
            break;
        case 'show':
            $molecules = \WishKnish\KnishIO\Models\Molecule::get();
            echo '<pre>';
            foreach ( $molecules as $molecule) {
                echo 'Peer slug: '. $molecule->peer_slug .
                    '; Molecular hash: '. $molecule->molecular_hash .
                    '; Status: '. $molecule->status . "\r\n";
            }
            echo '</pre>';
            break;
        default:
            abort(404);
    }
} );



$router->get( '/', function () use ( $router ) {
    return view( 'index' );
} );
