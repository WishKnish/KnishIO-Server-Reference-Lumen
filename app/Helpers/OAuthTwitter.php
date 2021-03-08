<?php
namespace App\Helpers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Psr7\Request;

/**
 * Class OAuthTwitter
 * @package WishKnish\KnishIO\Helpers
 */
class OAuthTwitter
{
    protected const URL_GET_TOKEN = 'https://api.twitter.com/oauth2/token';
    protected const URL_REQUEST_TOKEN = 'https://api.twitter.com/oauth/request_token';
    protected const URL_AUTHORIZE = 'https://api.twitter.com/oauth/authorize';
    protected const URL_ACCESS_TOKEN = 'https://api.twitter.com/oauth/access_token';
    protected const URL_USER_DATA = 'https://api.twitter.com/1.1/users/show.json';

    protected string $consumerKey;
    protected string $consumerSecret;
    protected string $urlCallback;
    protected Client $client;

    /**
     * OAuthTwitter constructor.
     *
     * @param string $key
     * @param string $secret
     * @param string $urlCallback
     */
    public function __construct ( string $key, string $secret, string $urlCallback = '' ) {
        $this->consumerKey = $key;
        $this->consumerSecret = $secret;
        $this->client = new Client();
        $this->urlCallback = $urlCallback;
    }

    /**
     * @return array
     * @throws GuzzleException
     */
    public function getAuthUrl(): array {
        $oauthNonce = static::getNonce();
        $oauthTimestamp = time();

        $oauthBaseText = "GET&" .
            urlencode( static::URL_REQUEST_TOKEN ) . "&" .
            urlencode(
                "oauth_callback=" . urlencode( $this->urlCallback ) . "&" .
                "oauth_consumer_key=" . $this->consumerKey . "&" .
                "oauth_nonce=" . $oauthNonce . "&" .
                "oauth_signature_method=HMAC-SHA1&" .
                "oauth_timestamp=" . $oauthTimestamp . "&" .
                "oauth_version=1.0"
            );

        $key = $this->consumerSecret . "&";
        $oauthSignature = static::encode( $oauthBaseText, $key );

        $url = '?oauth_callback=' . urlencode($this->urlCallback) .
            '&oauth_consumer_key=' . $this->consumerKey .
            '&oauth_nonce=' . $oauthNonce .
            '&oauth_signature=' . urlencode($oauthSignature) .
            '&oauth_signature_method=HMAC-SHA1' .
            '&oauth_timestamp=' . $oauthTimestamp .
            '&oauth_version=1.0';

        $response = $this->client->send(
            new Request( 'GET', static::URL_REQUEST_TOKEN . $url ), [
            RequestOptions::HTTP_ERRORS => false,
        ] );

        $contents = $response->getBody()->getContents();

        parse_str( $contents, $result );

        return [
            'url' => static::URL_AUTHORIZE . '?oauth_token=' . array_get( $result, 'oauth_token', '' ),
            'session' => array_get( $result, 'oauth_token_secret', '' ),
        ];
    }

    /**
     * @param array $data
     *
     * @return array|null
     * @throws GuzzleException
     */
    public function getUserData( array $data = [] ): ?array {
        $oauthToken = array_get( $data, 'oauthToken' );
        $oauthVerifier = array_get( $data, 'oauthVerifier' );
        $session = array_get( $data, 'session' );
        $userId = null;

        if ( ! in_array( null, [ $oauthToken, $oauthVerifier, $session ], true ) ) {
            $oauthNonce = static::getNonce();
            $oauthTimestamp = time();

            $oauthBaseText = "GET&" .
                urlencode( static::URL_ACCESS_TOKEN ) . "&" .
                urlencode(
                    "oauth_consumer_key=" . $this->consumerKey . "&" .
                    "oauth_nonce=" . $oauthNonce . "&" .
                    "oauth_signature_method=HMAC-SHA1&" .
                    "oauth_token=" . $oauthToken . "&" .
                    "oauth_timestamp=" . $oauthTimestamp . "&" .
                    "oauth_verifier=" . $oauthVerifier . "&" .
                    "oauth_version=1.0"
                );

            $key = $this->consumerSecret . "&" . $session;
            $oauthSignature = static::encode( $oauthBaseText, $key );

            $url = '?oauth_consumer_key=' . $this->consumerKey .
                '&oauth_nonce=' . $oauthNonce .
                '&oauth_signature_method=HMAC-SHA1' .
                '&oauth_token=' . urlencode( $oauthToken ) .
                '&oauth_timestamp=' . $oauthTimestamp .
                '&oauth_verifier=' . urlencode( $oauthVerifier ) .
                '&oauth_signature=' . urlencode( $oauthSignature ) .
                '&oauth_version=1.0';

            $response = $this->client->send( new Request( 'GET', static::URL_ACCESS_TOKEN . $url ), [
                RequestOptions::HTTP_ERRORS => false,
            ] );

            $contents = $response->getBody()->getContents();

            parse_str( $contents, $result );

            $userId = array_get( $result, 'user_id' );

            return $this->getUser( $userId );
        }

        return null;
    }

    /**
     * @return array|null
     * @throws GuzzleException
     */
    protected function getToken(): ?array {

        $response = $this->client->send(
            new Request( 'POST', static::URL_GET_TOKEN, [
                'Content-type' => 'application/x-www-form-urlencoded;charset=UTF-8',
                'Authorization' => 'Basic ' . base64_encode($this->consumerKey . ':' . $this->consumerSecret ),
            ] ),
            [ RequestOptions::FORM_PARAMS => [ 'grant_type' => 'client_credentials', ], ]
        );

        return json_decode( $response->getBody()->getContents(), true );
    }

    /**
     * @param string|null $userId
     *
     * @return array|null
     * @throws GuzzleException
     */
    protected function getUser( ?string $userId = null ): ?array {

        if ( $userId && $token = $this->getToken() ) {
            $tokenType = array_get( $token, 'token_type' );
            $accessToken = array_get( $token, 'access_token' );

            $response = $this->client->send(
                new Request( 'GET', static::URL_USER_DATA, [
                    'Content-type' => 'application/x-www-form-urlencoded;charset=UTF-8',
                    'Authorization' => $tokenType . ' ' . $accessToken
                ] ),
                [ RequestOptions::QUERY => [ 'user_id' => $userId ], ]
            );

            return json_decode( $response->getBody()->getContents(), true );
        }

        return null;
    }

    /**
     * @param string $string
     * @param string $key
     *
     * @return string
     */
    protected static function encode ( string $string, string $key ): string {
        return base64_encode( hash_hmac( 'sha1', $string, $key, true ) );
    }

    /**
     * @return string
     */
    protected static function getNonce(): string {
        return md5( uniqid( mt_rand(), true ) );
    }
}
