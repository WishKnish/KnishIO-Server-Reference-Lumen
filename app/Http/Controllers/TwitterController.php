<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TwitterController extends Controller {

    /**
     * @param Request $request
     *
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function token ( Request $request ): JsonResponse {
        $config = config( 'oauth' );
        $parameters = $request->all();
        $driver = array_get( $parameters, 'driver' );
        $method = array_get( $parameters, 'request' );
        $data = array_get( $parameters, 'data' );
        $callback = array_get( $parameters, 'callback', '' );

        if ( !in_array( null, [
            $driver,
            $method
        ], true ) ) {
            $driver = strtolower( $driver );
            $API_KEY = array_get( $config, 'key.' . $driver . '.key' );
            $API_SECRET = array_get( $config, 'key.' . $driver . '.secret' );
            $errorMessage = [];
            $status = 200;
            $service = array_get( $config, 'driver.' . $driver );

            if ( $service === null ) {
                $status = 404;
                $errorMessage[] = 'The requested service for driver ' . $driver . ' is missing.';
            }
            else {
                if ( !in_array( $method, $service[ 'method' ], true ) ) {
                    $status = 405;
                    $errorMessage[] = 'The requested method is missing.';
                }
            }

            if ( count( $errorMessage ) > 0 ) {
                return new JsonResponse( [ 'error' => [ 'messages' => $errorMessage ] ], $status );
            }

            $oAuth = new $service[ 'class' ]( $API_KEY, $API_SECRET, $callback );
            $response = $data === null ? $oAuth->{$method}() : $oAuth->{$method}( $data );

            return new JsonResponse( $response, $status );
        }

        return new JsonResponse( [ 'error' => [ 'messages' => [ 'Bad Request' ] ] ], 400 );
    }
}
