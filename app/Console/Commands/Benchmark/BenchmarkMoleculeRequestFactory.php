<?php

namespace App\Console\Commands\Benchmark;

use Exception;
use Psr\Http\Message\RequestInterface;
use WishKnish\KnishIO\Client\KnishIOClient;
use WishKnish\KnishIO\Client\Molecule;
use WishKnish\KnishIO\Client\Mutation\MutationProposeMolecule;

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
        $query = $client->createMoleculeMutation( MutationProposeMolecule::class, $molecule );

        return $query->createRequest();
    }
}
