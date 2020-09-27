<?php

namespace App\Console\Commands\Benchmark;

use Exception;
use Psr\Http\Message\RequestInterface;
use ReflectionException;
use WishKnish\KnishIO\Client\KnishIOClient;
use WishKnish\KnishIO\Client\Libraries\Crypto;
use WishKnish\KnishIO\Client\Query\QueryMoleculePropose;
use WishKnish\KnishIO\Client\Wallet;

/**
 * Class BenchmarkMoleculeRequestFactory
 */
class BenchmarkMoleculeRequestFactory
{
    /**
     * @param KnishIOClient $client
     * @param int $metas_count
     * @return RequestInterface
     * @throws Exception|ReflectionException
     */
    public static function create ( KnishIOClient $client, int $metas_count ): RequestInterface
    {
        // Defining signing parameters
        $source_wallet = Wallet::create( $client->secret(), 'USER' );
        $remainder_wallet = Wallet::create( $client->secret(), 'USER' );
        $molecule = $client->createMolecule( $client->secret(), $source_wallet, $remainder_wallet );

        $metas = [];
        for ( $meta_num = 0; $meta_num < $metas_count; $meta_num++ ) {
            $metas[ 'meta_' . $meta_num ] = Crypto::generateSecret( null, 64 );
        }

        // Initializing molecule content
        $molecule->initMeta( $metas, 'benchmarkType', 'benchmarkId' . random_int( 0, 100 ) );
        $molecule->sign();

        // Preparing Guzzle request
        $query = $client->createMoleculeQuery( QueryMoleculePropose::class, $molecule );

        return $query->createRequest();
    }
}
