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

use App\Http\Middleware\CorsMiddleware;
use WishKnish\KnishIO\Client\Meta;
use WishKnish\KnishIO\Helpers\ServerControl;
use WishKnish\KnishIO\Lighthouse\ASTBuilder;
use WishKnish\KnishIO\Models\Atom;
use WishKnish\KnishIO\Models\Meta\MetaContext;
use WishKnish\KnishIO\Models\Token;
use WishKnish\KnishIO\Models\Wallet;

// Custom schema => graphql
$router->get( 'schema.graphql', function () use ( $router ) {
    echo app( ASTBuilder::class )->doPrintSchema();
} );

// Custom schema => json-ld
$router->get( 'schema.jsonld', function () use ( $router ) {

    $metaContext = new MetaContext( 'local' );

    header( 'application/ld+json' );
    echo $metaContext->getJsonldObject()
        ->toJsonldSchema();
} );

// Custom schema => json-ld
$router->get( 'schema.org.jsonld', function () use ( $router ) {

    $metaContext = new \WishKnish\KnishIO\Models\Meta\SchemaMetaContext( 'local' );

    header( 'application/ld+json' );
    echo $metaContext->getJsonldObject()
        ->toJsonldSchema();
} );

// Custom schema overview
$router->get( 'schema.org', function () use ( $router ) {

    $metaContext = new \WishKnish\KnishIO\Models\Meta\SchemaMetaContext( 'local' );
    $jsonldObject = $metaContext->getJsonldObject();

    // Get only parent jsonld types @todo add cascade check to disable child with childs
    $jsonldTypes = [];
    foreach ( $jsonldObject->graph() as $jsonldType ) {
        if ( $jsonldType->fields() ) {
            $jsonldTypes[] = $jsonldType;
        }
    }

    return view( 'schema/schema', [ 'jsonldTypes' => $jsonldTypes ] );
} );

// Fields
$router->get( 'schema.org/{type}', function ( $type ) use ( $router ) {

    $metaContext = new \WishKnish\KnishIO\Models\Meta\SchemaMetaContext( 'local' );
    $jsonldType = $metaContext->getJsonldObject()
        ->graphType( $type );
    return view( 'schema/schema_type', [ 'jsonldType' => $jsonldType, ] );
} );

// Custom schema overview
$router->get( 'schema', function () use ( $router ) {

    $metaContext = new MetaContext( 'local' );
    $jsonldObject = $metaContext->getJsonldObject();

    // Get only parent jsonld types @todo add cascade check to disable child with childs
    $jsonldTypes = [];
    foreach ( $jsonldObject->graph() as $jsonldType ) {
        if ( $jsonldType->fields() ) {
            $jsonldTypes[] = $jsonldType;
        }
    }

    return view( 'schema/index', [ 'jsonldTypes' => $jsonldTypes ] );
} );

// Fields
$router->get( 'schema/{type}', function ( $type ) use ( $router ) {

    $metaContext = new MetaContext( 'local' );
    $jsonldType = $metaContext->getJsonldObject()
        ->graphType( $type );
    return view( 'schema/type', [ 'jsonldType' => $jsonldType, ] );

} );

// Meta instance json-ld data
$router->get( 'scope/{metaType}/{metaId}.jsonld', function ( $metaType, $metaId ) use ( $router ) {

    $metas = \WishKnish\KnishIO\Models\Meta::whereInstance( null, null, [ $metaType ], [ $metaId ] )
        ->whereLatest()
        ->get();
    $metas = Meta::aggregateMeta( $metas );

    // Get a meta context
    $context = array_get( $metas, 'context', env( 'KNISHIO_SCHEMA' ) );
    if ( !$context ) {
        throw new \Exception( 'KNISHIO_SCHEMA does not initialized.' );
    }

    // Get a model: @todo change this code to use resolver classes
    $attributes = [];
    switch ( $metaType ) {
        case 'Token':
            $model = Token::where( 'slug', $metaId )
                ->first();
            if ( !$model ) {
                abort( 404 );
            }
            $attributes = $model->getAttributes();
            break;
        case 'Wallet':
            $model = \WishKnish\KnishIO\Models\Wallet::where( 'address', $metaId )
                ->first();
            if ( !$model ) {
                abort( 404 );
            }
            $model->token = $model->token_slug;
            $model->bundle = $model->bundle_hash;
            $attributes = $model->getAttributes();
            break;
    }

    // All metas to output
    $metas = array_merge( $attributes, $metas );

    // Get a meta context & filter aggregated metas
    header( 'application/ld+json' );

    // Find a context object
    $metaContext = MetaContext::find( $context );

    // Get a graphType
    $graphType = $metaContext->getJsonldObject()
        ->graphType( $metaType );

    // Filtering metas
    echo json_encode( $graphType->toJsonldDataArray( $metas ) );

} );

// @todo: DEBUG CODE FOR SERVER CONTROL
$router->get( '/peer/{action}', function ( $action ) use ( $router ) {

    // Execute server action
    $serverControl = new ServerControl();
    $serverControl->execute( $action, request()->has( 'all' ) );

} );

$router->options( '/knishio.oauth', [
    'middleware' => [ CorsMiddleware::class ],
    function () {
        return response( [ 'status' => 'success' ] );
    }
] );

$router->post( '/knishio.oauth', [
    'middleware' => [ CorsMiddleware::class ],
    'as' => 'knishio_oauth',
    'uses' => 'TwitterController@token',
] );

$router->get( '/', function () use ( $router ) {

    return view( 'index' );
} );
