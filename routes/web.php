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

    // Get a model
    $model = null;
    switch( $atom->meta_type ) {
        case 'token':
            $model = Token::where( 'slug', $atom->meta_id )->first();
            break;
    }
    if ( !$model ) {
        throw new \Exception( 'Model does not found.' );
    }

    // All metas to output
    $metas = array_merge( $model->getAttributes(), $metas );

    // Get a meta context & filter aggregated metas
    header( 'application/ld+json' );
    $metaContext = new \WishKnish\KnishIO\Models\Meta\MetaContext( array_get( $metas, 'context' ) );
    echo $metaContext->filter( $metas );

});


$router->get( '/', function () use ( $router ) {
    return view( 'index' );
} );
