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


$router->get( '/', function () use ( $router ) {
    return view( 'index' );
} );
