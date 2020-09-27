<?php

namespace App\Console\Commands\Benchmark;

use Exception;
use WishKnish\KnishIO\Client\Libraries\Crypto;
use WishKnish\KnishIO\Helpers\Cleaner;

/**
 * Class BenchmarkClientFactory
 */
class BenchmarkBundleFactory
{
    /**
     * @param string $secret
     * @param string $cell_slug
     * @return string
     * @throws Exception
     */
    public static function create ( string $secret ): string
    {
        return Crypto::generateBundleHash( $secret );
    }

    /**
     * @param string $secret
     * @throws Exception
     */
    public static function destroy ( string $secret ): void
    {
        $bundle_hash = Crypto::generateBundleHash( $secret );
        Cleaner::byBundleHash( [ $bundle_hash ] );
    }
}
