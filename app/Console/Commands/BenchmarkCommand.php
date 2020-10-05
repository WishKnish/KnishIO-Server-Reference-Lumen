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
use App\Console\Commands\Benchmark\BenchmarkMetaTypeRequestFactory;
use App\Console\Commands\Benchmark\BenchmarkMoleculeFactory;
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

    // Benchmarks to execute separated tasks
    protected $benchmarks = [
        'benchmark_write' => 'BENCHMARK WRITE',
        'benchmark_read' => 'BENCHMARK READ',
    ];

    // Requests for the Pool
    protected $requests = [];

    protected $graphql_url;
    protected $cell_count = 1;
    protected $thread_count;
    protected $metas_count;
    protected $molecules_count;
    protected $cells = [];
    protected $bundles = [];
    protected $molecules = [];


    /**
     * @param $all
     * @param $count
     */
    public static function metaIdAllCombinations ( $all, $count, $start_combination_length = 2 ) : array
    {
        $result = [];
        for ($length = $start_combination_length, $lengthMax = count( $all ); $length <= $lengthMax; $length++ ) {
            static::metaIdCombinations( $result, $count, $all, [], $length );
        }
        return $result;
    }


    /**
     * @param $result
     * @param $all
     * @param $custom
     * @param $length
     */
    public static function metaIdCombinations ( &$result, $count, $all, $custom, $length )
    {
        // Check total count
        if ( count($result) >= $count ) {
            return;
        }

        // Check length
        if ( count( $custom ) >= $length ) {
            $result[] = $custom;
            return;
        }

        // All combinations
        foreach ( $all as $item ) {
            if ( !in_array( $item, $custom ) ) {
                static::metaIdCombinations($result, $count, $all, array_merge($custom, [$item]), $length);
            }
        }
    }



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
            $this->graphql_url = url() . '/graphql';
            $this->info( 'GraphQL url: '. $this->graphql_url );
            $this->thread_count = $this->option( 'threads' );
            $this->info( 'Threads: ' . $this->thread_count );
            $this->metas_count = $this->option( 'metas' );
            $this->info( 'Metas per molecule: ' . $this->metas_count );
            $this->molecules_count = $this->option( 'molecules' );
            $this->info( 'Molecules per bundle: ' . $this->molecules_count );

            // Bootstrapping assets
            $this->commentTitle( 'BOOTSTRAPPING ASSETS' );
            $this->bootstrap();


            // Execute all benchmarks
            $all_results = [];  // All results
            $results = []; // Results from the previous call
            foreach ( $this->benchmarks as $key => $title ) {
                $this->commentTitle( 'STARTING ' . $title );

                // Execute benchmark callback with the previous results
                $results = $this->$key( array_get( $this->requests, $key ), $results );

                // Save results to the common list
                $all_results[$key] = $results;
            }

            // Cleaning up
            $this->commentTitle( 'CLEANING UP' );
            $this->cleanup();

            // Computing results
            foreach ( $all_results as $fn => $results ) {
                $this->commentTitle($this->benchmarks[$fn] . ' RESULTS');
                $this->info('Total queries: ' . ($results['success'] + $results['fail']));
                $this->info('Success: ' . $results['success'] . ' Failure: ' . $results['fail']);
                $this->info('Time Spent: ' . round($results['time'], 2) . ' TPS: ' . round(($results['success'] + $results['fail']) / $results['time'], 2) . ' Successful TPS: ' . round($results['success'] / $results['time'], 2));
            }

            return true;
        }
        catch ( Exception $e ) {
            $this->error( $e );
        }
        return false;
    }


    /**
     * @param string $title
     */
    protected function commentTitle ( string $title )
    {
        $title = '## '. $title .' ##';
        $delimiter = str_repeat('#', strlen($title));

        $this->comment( '' );
        $this->comment( $delimiter );
        $this->comment( $title );
        $this->comment( $delimiter );
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
            $client = new KnishIOClient( $this->graphql_url );
            $client->authentication( $secret );

            // $instance->info( 'Creating '. $this->molecules_count.' molecules for bundle ' . $bundle . '...' );

            // Creating Molecule Requests
            for ( $molecule_num = 0; $molecule_num < $this->molecules_count; $molecule_num++ ) {

                // Create a molecule
                $molecule = BenchmarkMoleculeFactory::create( $client, $this->metas_count );

                // Save molecule to the common list
                $this->molecules[ $molecule->molecularHash ] = $molecule;

                // Create a request
                $this->requests[ 'benchmark_write' ][] = BenchmarkMoleculeRequestFactory::create( $client, $molecule );

                $instance->info( 'Molecule ' . ( $molecule_num + 1 ) . ' has been created.' );
            }
        }


        // !!! @todo here used only single $client
        $self = $this;
        $this->requests[ 'benchmark_read' ] = static function ( $related_results ) use ( $self ) {

            // Defining client and authenticating the session
            $secret = Crypto::generateSecret();
            $client = new KnishIOClient( $self->graphql_url );
            $client->authentication( $secret );

            // Accumulate all meta types & ids
            $metaTypes = []; $metaIds = [];
            foreach ( $related_results[ 'accepted_molecules' ] as $molecule ) {
                $metaTypes[] = $molecule->atoms[0]->metaType;
                $metaTypes = array_unique( $metaTypes );
                $metaIds[] = $molecule->atoms[0]->metaId;
            }

            // Generate combinations: @todo add here custom random logic based on $metaTypes, $metaIds data
            $metaIdBunches = static::metaIdAllCombinations ( $metaIds, count($metaIds) );

            // Generate requests
            $requests = [];
            foreach ( $metaIdBunches as $metaIdBunch ) {
                $requests[] = BenchmarkMetaTypeRequestFactory::create(
                    $client, [ $metaTypes[0] ], $metaIdBunch
                );
            }

            return $requests;
        };

    }

    /**
     * @param $requests
     * @param array $previous_results
     * @return array
     */
    protected function benchmark_write ( $requests, array $related_results ): array
    {
        $this->info( 'Starting benchmark...' );
        $benchmark_result = [ 'success' => 0, 'fail' => 0, 'time' => 0, 'accepted_molecules' => [] ];
        $start_time = microtime( true );

        // Generic client for sending the requests
        $client = new KnishIOClient( $this->graphql_url );

        // Asynchronous broadcast of molecules
        $pool = new Pool( $client->client(), $requests, [ 'concurrency' => $this->thread_count, 'fulfilled' => function ( ResponseInterface $response, $index ) use ( &$benchmark_result ) {
            $data = json_decode( $response->getBody()->getContents(), true );
            $molecule = array_get( $data, 'data.ProposeMolecule' );

            $benchmark_result[ 'metas' ] = [];
            if ( $molecule && $molecule[ 'status' ] === 'accepted' ) {
                $this->info( 'Molecule ' . $index . ' has been accepted.' );
                $benchmark_result[ 'success' ]++;
                $benchmark_result[ 'metas' ] = '';
                $benchmark_result[ 'accepted_molecules' ][] = array_get( $this->molecules, $molecule[ 'molecularHash' ] );
            }
            else {
                $error = array_has( $data, 'message' ) ?
                    array_get( $data, 'message' ) : array_get( $molecule, 'reason' );
                $this->error( 'Molecule ' . $index . ' has been rejected due to: "' . $error . '"' );
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


    /**
     * @param $requests
     * @param array $results
     * @return array
     */
    protected function benchmark_read ( $requests, array $related_results ): array
    {
        $this->info( 'Starting benchmark...' );
        $benchmark_result = [ 'success' => 0, 'fail' => 0, 'time' => 0 ];
        $start_time = microtime( true );

        // Generic client for sending the requests
        $client = new KnishIOClient( $this->graphql_url );

        // Asynchronous broadcast of molecules
        $pool = new Pool( $client->client(), $requests( $related_results ), [ 'concurrency' => $this->thread_count, 'fulfilled' => function ( ResponseInterface $response, $index ) use ( &$benchmark_result ) {
            $data = json_decode( $response->getBody()->getContents(), true );
            $metaTypes = array_get( $data, 'data.MetaType' );

            if ( $metaTypes ) {
                $metaInstances = array_get( $metaTypes, '0.instances' );
                $benchmark_result[ 'success' ]++;
                $this->info( 'MetaTypeQuery ' . $index . ' has been executed correctly. Got '. count( $metaInstances ) .' record(s).' );
            }
            else {
                $benchmark_result[ 'fail' ]++;
                $this->error( 'MetaTypeQuery ' . $index . ' has errors.' );
            }

        }, 'rejected' => function ( $reason, $index ) use ( &$benchmark_result ) {
            $this->error( 'MetaType query ' . $index . ' has failed due to: ' . $reason );
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
