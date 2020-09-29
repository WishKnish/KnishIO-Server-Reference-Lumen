<?php
/**
 *
 * PHP version >= 7.0
 *
 * @category Console_Command
 * @package  App\Console\Commands
 */

namespace App\Console\Commands;

use App\Console\Commands\Benchmark\BenchmarkBundleFactory;
use App\Console\Commands\Benchmark\BenchmarkMoleculeRequestFactory;
use App\Console\Commands\Benchmark\BenchmarkCellFactory;
use Exception;
use GuzzleHttp\Pool;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Console\Command;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReflectionException;
use WishKnish\KnishIO\Client\KnishIOClient;
use WishKnish\KnishIO\Client\Libraries\Crypto;
use WishKnish\KnishIO\Helpers\Cleaner;

/**
 * Class deletePostsCommand
 *
 * @category Console_Command
 * @package  App\Console\Commands
 */
class BenchmarkCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'molecule:benchmark {--threads=10} {--metas=3} {--molecules=10}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Benchmark molecule performance';

    protected $cell_count = 1;
    protected $thread_count;
    protected $metas_count;
    protected $molecules_count;
    protected $cells = [];
    protected $bundles = [];
    protected $molecules = [];

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle ()
    {
        set_time_limit( 9999 );
        try {
            // Options
            $this->info( 'SHA3 Native Extension: ' . function_exists( 'shake256' ) ? 'Yes' : 'No' );
            $this->thread_count = $this->option( 'threads' );
            $this->info( 'Threads: ' . $this->thread_count );
            $this->metas_count = $this->option( 'metas' );
            $this->info( 'Metas per molecule: ' . $this->metas_count );
            $this->molecules_count = $this->option( 'molecules' );
            $this->info( 'Molecules per bundle: ' . $this->molecules_count );

            // Bootstrapping assets
            $this->comment( '' );
            $this->comment( '##########################' );
            $this->comment( '## BOOTSTRAPPING ASSETS ##' );
            $this->comment( '##########################' );
            $this->bootstrap();

            // Starting benchmark
            $this->comment( '' );
            $this->comment( '########################' );
            $this->comment( '## STARTING BENCHMARK ##' );
            $this->comment( '########################' );
            $results = $this->benchmark();

            // Cleaning up
            $this->comment( '' );
            $this->comment( '#################' );
            $this->comment( '## CLEANING UP ##' );
            $this->comment( '#################' );
            $this->cleanup();

            // Computing results
            $this->comment( '' );
            $this->comment( '#######################' );
            $this->comment( '## BENCHMARK RESULTS ##' );
            $this->comment( '#######################' );
            $this->info( 'Total Molecules Sent: ' . ( $results[ 'success' ] + $results[ 'fail' ] ) );
            $this->info( 'Success: ' . $results[ 'success' ] . ' Failure: ' . $results[ 'fail' ] );
            $this->info( 'Time Spent: ' . round( $results[ 'time' ], 2 ) . ' TPS: ' . round( ( $results[ 'success' ] + $results[ 'fail' ] ) / $results[ 'time' ], 2 ) . ' Successful TPS: ' . round( $results[ 'success' ] / $results[ 'time' ], 2 ) );

            return true;
        }
        catch ( Exception $e ) {
            $this->error( $e );
        }
        return false;
    }

    /**
     * @throws Exception|ReflectionException
     */
    protected function bootstrap (): void
    {
        $instance = $this;

        // Method to trigger Cell creation
        $cell_producer = static function ( string $cell_slug ) use ( $instance ) {

            $instance->info( 'Cell Slug ' . $cell_slug . ' is being created...' );
            $cell = BenchmarkCellFactory::create( $cell_slug );
            $instance->info( 'Cell Slug ' . $cell_slug . ' has been created.' );

            return $cell;
        };

        // Method to trigger Bundle creation
        $bundle_producer = static function ( string $secret ) use ( $instance ) {

            $instance->info( 'Bundle is being created...' );
            $bundle = BenchmarkBundleFactory::create( $secret );
            $instance->info( 'Bundle ' . $bundle . ' has been created.' );

            return $bundle;
        };

        // Method to trigger Molecule Request creation
        $molecule_request_producer = static function ( KnishIOClient $client, int $metas_count ) {
            return BenchmarkMoleculeRequestFactory::create( $client, $metas_count );
        };

        // Creating Cells
        for ( $cell_num = 0; $cell_num < $this->cell_count; $cell_num++ ) {
            // $cells[] = \parallel\run( $producer, [ 'TEST' . $i ] )->value();
            $this->cells[] = $cell_producer( 'TEST' . $cell_num );
        }

        // Creating Bundles
        for ( $client_count = 1; $client_count <= $this->thread_count; $client_count++ ) {
            $secret = Crypto::generateSecret();
            $bundle = $bundle_producer( $secret );
            $this->bundles[ $bundle ] = [];

            // Defining client and authenticating the session
            $client = new KnishIOClient( url() . '/graphql' );
            $client->setSecret( $secret );
            $client->authentication( $secret );

            // $instance->info( 'Creating '. $this->molecules_count.' molecules for bundle ' . $bundle . '...' );

            // Creating Molecule Requests
            for ( $molecule_num = 0; $molecule_num < $this->molecules_count; $molecule_num++ ) {
                $this->molecules[] = $molecule_request_producer( $client, $this->metas_count );
                $instance->info( 'Molecule ' . ( $molecule_num + 1 ) . ' has been created.' );
            }
        }
    }

    /**
     * @return int[]
     */
    protected function benchmark (): array
    {
        $this->info( 'Starting benchmark...' );
        $benchmark_result = [ 'success' => 0, 'fail' => 0, 'time' => 0 ];
        $start_time = microtime( true );

        // Generic client for sending the requests
        $client = new KnishIOClient( url() . '/graphql' );

        // Asynchronous broadcast of molecules
        $pool = new Pool( $client->client(), $this->molecules, [ 'concurrency' => $this->thread_count, 'fulfilled' => function ( ResponseInterface $response, $index ) use ( &$benchmark_result ) {
            $data = json_decode( $response->getBody()->getContents(), true );
            $molecule = $data[ 'data' ][ 'ProposeMolecule' ];

            if ( $molecule[ 'status' ] === 'accepted' ) {
                $this->info( 'Molecule ' . $index . ' has been accepted.' );
                $benchmark_result[ 'success' ]++;
            }
            else {
                $this->error( 'Molecule ' . $index . ' has been rejected due to: ' . $molecule[ 'reason' ] );
                $benchmark_result[ 'fail' ]++;
            }
        }, 'rejected' => function ( $reason, $index ) use ( &$benchmark_result ) {
            $this->error( 'Molecule ' . $index . ' has failed due to: ' . $reason );
            $benchmark_result[ 'fail' ]++;
        }, ] );

        $promise = $pool->promise();  // Start transfers and create a promise
        $promise->wait();   // Force the pool of requests to complete.

        // $results = \GuzzleHttp\Promise\unwrap($promises);
        $end_time = microtime( true );
        $benchmark_result[ 'time' ] = $end_time - $start_time;
        $this->info( 'Benchmark complete.' );

        return $benchmark_result;
    }

    protected function cleanup (): void
    {
        $this->info( 'Starting cleanup...' );

        $instance = $this;

        $producer1 = static function ( string $cell_slug ) use ( $instance ) {
            $instance->info( 'Cell Slug ' . $cell_slug . ' is being cleaned...' );
            BenchmarkCellFactory::destroy( $cell_slug );
            $instance->info( 'Cell Slug ' . $cell_slug . ' has been cleaned.' );
        };

        $producer2 = static function ( string $bundleHash ) use ( $instance ) {
            $instance->info( 'Cleaning by bundle hash ' . $bundleHash . '...' );
            Cleaner::byBundleHash( [ $bundleHash ] );
            $instance->info( 'Bundle hash ' . $bundleHash . ' has been cleaned.' );
        };

        foreach ( $this->cells as $cell ) {
            $producer1( $cell->cell_slug );
        }

        // Clear by bundle hashes
        foreach ( $this->bundles as $bundle => $bundle_data ) {
            $producer2( $bundle );
            // \parallel\run( $producer2, [ $bundle->bundle_hash ] )->value();
        }

        $this->cells = [];
        $this->bundles = [];

        $this->info( 'Cleanup complete.' );
    }


    /**
     * @param KnishIOClient $client
     * @param RequestInterface $request
     * @return PromiseInterface
     */
    public static function sendAsync ( KnishIOClient $client, RequestInterface $request ): PromiseInterface
    {
        return $client->client()->sendAsync( $request );
    }

}
