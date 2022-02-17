<?php
namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use WishKnish\KnishIO\Client\Molecule;
use WishKnish\KnishIO\Client\Wallet;

class MoleculeMetaCommand extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'molecule:meta {--meta_type=} {--meta_id=} {--key=} {--value=} {--encrypt=false} {--from_file=true}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create and verify a simple meta molecule';

    protected string $secret;
    protected string $meta_type;
    protected string $meta_id;
    protected string $key;
    protected string $value;
    protected bool $from_file = true;
    protected bool $encrypt = false;

    /**
     * Execute the console command.
     *
     * @return bool
     */
    public function handle (): bool {
        set_time_limit( 9999 );

        try {
            $start_time = microtime( true );

            // Options
            $this->secret = env( 'SECRET_TOKEN_KNISH' );
            $this->info( 'Secret: ' . $this->secret );
            $this->meta_type = $this->option( 'meta_type' );
            $this->info( 'Meta Type: ' . $this->meta_type );
            $this->meta_id = $this->option( 'meta_id' );
            $this->info( 'Meta Type: ' . $this->meta_id );
            $this->key = $this->option( 'key' );
            $this->info( 'Key: ' . $this->key );
            $this->encrypt = $this->option( 'encrypt' );
            $this->info( 'Encrypt: ' . strlen( $this->encrypt ) );

            $this->value = $this->option( 'value' );
            $this->from_file = $this->option( 'from_file' );
            $this->info( 'From File: ' . $this->from_file );

            if( $this->from_file ) {
                $this->info( 'Reading Value from ' . $this->value );
                $this->value = file_get_contents( $this->value );
            }

            $end_time = microtime( true );
            $benchmark_result = round( $end_time - $start_time, 2 );
            $this->info( 'Benchmark: ' . $benchmark_result );
            $start_time = microtime( true );

            $this->commentTitle( 'CREATING MOLECULE' );
            $this->info( 'Initializing Wallet...' );
            $source_wallet = new Wallet( $this->secret );
            $this->info( 'Initializing Molecule...' );
            $molecule = new Molecule( $this->secret, $source_wallet );

            $end_time = microtime( true );
            $benchmark_result = round( $end_time - $start_time, 2 );
            $this->info( 'Benchmark: ' . $benchmark_result );
            $start_time = microtime( true );

            $original_value = $this->value;
            if ( $this->encrypt ) {
                $this->commentTitle( 'ENCRYPTING META VALUE' );
                // $this->info( 'Initial Value: ' . $this->value );
                $this->info( 'Initial Value Length: ' . strlen( $this->value ) );
                $this->value = base64_encode( $this->value );

                // $this->info( 'Base64 Value: ' . $this->value );
                $this->info( 'Base64 Value Length: ' . strlen( $this->value ) );
                $this->value = json_encode( $source_wallet->encryptMyMessage( $this->value, $source_wallet->getMyEncPublicKey() ), JSON_THROW_ON_ERROR );

                // $this->info( 'Encrypted Value: ' . $this->value );
                $this->info( 'Encrypted Value Length: ' . strlen( $this->value ) );

                $end_time = microtime( true );
                $benchmark_result = round( $end_time - $start_time, 2 );
                $this->info( 'Benchmark: ' . $benchmark_result );
                $start_time = microtime( true );
            }

            $this->info( 'Initializing Meta...' );

            $molecule->initMeta( [
                $this->key => $this->value
            ], $this->meta_type, $this->meta_id );

            $this->info( 'Signing Molecule...' );

            $molecule->sign();

            $end_time = microtime( true );
            $benchmark_result = round( $end_time - $start_time, 2 );
            $this->info( 'Benchmark: ' . $benchmark_result );
            $start_time = microtime( true );

            $this->commentTitle( 'VERIFYING MOLECULE' );

            $result = $molecule->check( $source_wallet );
            $this->info( 'Verification Result: ' . $result );

            if( $this->encrypt ) {
                $this->info( 'Decrypting Message...' );
                $message = $source_wallet->decryptMyMessage( json_decode( $this->value, true, 512, JSON_THROW_ON_ERROR ) );

                // $this->info( 'Base64 Value: ' . $message );
                $this->info( 'Base64 Value Length: ' . strlen( $message ) );

                $message = base64_decode( $message );

                // $this->info( 'Initial Value: ' . $message );
                $this->info( 'Initial Value Length: ' . strlen( $message ) );

                if( $original_value === $message ) {
                    $this->info( 'Encryption Success!' );
                }
                else {
                    $this->info( 'Encryption Failure!' );
                }
            }

            $end_time = microtime( true );
            $benchmark_result = round( $end_time - $start_time, 2 );
            $this->info( 'Benchmark: ' . $benchmark_result );
        }
        catch ( Exception $e ) {
            $this->error( $e );
        }
        return false;
    }

    /**
     * @param string $title
     */
    protected function commentTitle ( string $title ): void {
        $title = '## ' . $title . ' ##';
        $delimiter = str_repeat( '#', strlen( $title ) );

        $this->comment( '' );
        $this->comment( $delimiter );
        $this->comment( $title );
        $this->comment( $delimiter );
    }

}
