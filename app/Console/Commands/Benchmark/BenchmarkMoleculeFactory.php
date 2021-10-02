<?php

namespace App\Console\Commands\Benchmark;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\RequestInterface;
use ReflectionException;
use WishKnish\KnishIO\Client\KnishIOClient;
use WishKnish\KnishIO\Client\Libraries\Crypto;
use WishKnish\KnishIO\Client\Molecule;
use WishKnish\KnishIO\Client\Wallet;

/**
 * Class BenchmarkMoleculeFactory
 * @package App\Console\Commands\Benchmark
 */
class BenchmarkMoleculeFactory {
    /**
     * @param KnishIOClient $client
     * @param int $metas_count
     *
     * @return RequestInterface
     * @throws Exception|ReflectionException
     * @throws GuzzleException
     */
    public static function create ( KnishIOClient $client, int $metas_count ): Molecule {
        // Defining signing parameters
        $source_wallet = Wallet::create( $client->getSecret(), 'USER' );
        $remainder_wallet = Wallet::create( $client->getSecret(), 'USER' );
        $molecule = $client->createMolecule( $client->getSecret(), $source_wallet, $remainder_wallet );

        $metas = [];
        for ( $meta_num = 0; $meta_num < $metas_count; $meta_num++ ) {
            $metas[ 'meta_' . $meta_num ] = Crypto::generateSecret( null, 64 );
        }

        // Initializing molecule content
        $molecule->initMeta( $metas, 'benchmarkType', 'benchmarkId' . random_int( 0, 100 ) );
        $molecule->sign();

        return $molecule;
    }
}
