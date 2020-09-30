<?php

namespace App\Console\Commands\Benchmark;

use Exception;
use Psr\Http\Message\RequestInterface;
use ReflectionException;
use WishKnish\KnishIO\Client\KnishIOClient;
use WishKnish\KnishIO\Client\Libraries\Crypto;
use WishKnish\KnishIO\Client\Molecule;
use WishKnish\KnishIO\Client\Query\QueryMoleculePropose;
use WishKnish\KnishIO\Client\Wallet;

/**
 * Class BenchmarkMoleculeRequestFactory
 */
class BenchmarkMoleculeRequestFactory
{



    /**
     * @param KnishIOClient $client
     * @param Molecule $molecule
     * @return RequestInterface
     * @throws Exception
     */
    public static function create ( KnishIOClient $client, Molecule $molecule ): RequestInterface
    {
        // Preparing Guzzle request
        $query = $client->createMoleculeQuery( QueryMoleculePropose::class, $molecule );

        return $query->createRequest();
    }
}
