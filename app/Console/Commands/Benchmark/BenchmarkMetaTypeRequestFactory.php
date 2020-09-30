<?php

namespace App\Console\Commands\Benchmark;

use Exception;
use Psr\Http\Message\RequestInterface;
use ReflectionException;
use WishKnish\KnishIO\Client\KnishIOClient;
use WishKnish\KnishIO\Client\Libraries\Crypto;
use WishKnish\KnishIO\Client\Molecule;
use WishKnish\KnishIO\Client\Query\QueryMetaType;
use WishKnish\KnishIO\Client\Query\QueryMoleculePropose;
use WishKnish\KnishIO\Client\Wallet;

/**
 * Class BenchmarkMetaTypeRequestFactory
 * @package App\Console\Commands\Benchmark
 */
class BenchmarkMetaTypeRequestFactory
{
    /**
     * @param KnishIOClient $client
     * @param int $metas_count
     * @return RequestInterface
     * @throws Exception|ReflectionException
     */
    public static function create ( KnishIOClient $client, array $metaTypes, array $metaIds ): RequestInterface
    {
        // Preparing Guzzle request
        $query = $client->createQuery( QueryMetaType::class );

        return $query->createRequest( [ 'metaTypes' => $metaTypes, 'metaIds' => $metaIds ], [
            'metaType',
            'instances' => [
                'metaType',
                'metaId',
                'createdAt',
            ],
        ] );
    }
}
