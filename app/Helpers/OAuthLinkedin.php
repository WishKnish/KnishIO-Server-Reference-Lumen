<?php
namespace App\Helpers;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Psr7\Request;


class OAuthLinkedin extends OAuth
{
    protected const URL_AUTHORIZE = 'https://www.linkedin.com/oauth/v2/authorization';
    protected const URL_ACCESS_TOKEN = 'https://www.linkedin.com/oauth/v2/accessToken';
    protected const URL_USER_DATA = 'https://api.linkedin.com/v2/me';

    /**
     * @return array
     */
    public function getAuthUrl(): array {

        $state = static::encode( $this->baseText(), $this->consumerSecret . "&" );
        $url = static::URL_AUTHORIZE .
            '?redirect_uri=' . urlencode($this->urlCallback) .
            '&client_id=' . $this->consumerKey .
            '&scope=' . urlencode( 'r_liteprofile r_emailaddress' ) .
            '&response_type=code' .
            '&state=' . $state;

        return [ 'url' => $url, 'state' => $state ];
    }

    /**
     * @param array $data
     * @return array|null
     * @throws GuzzleException
     */
    public function getUser( array $data = [] ): ?array {

        $findings = $this->getToken( array_get( $data, 'code' ), array_get( $data, 'state' ) ) ?? [];
        $error = array_get( $findings, 'error_description' );
        $accessToken = array_get( $findings, 'access_token' );
        $expiresIn = array_get( $findings, 'expires_in ' );

        if ( $error !== null ) {
            return [ 'error_description' => $error ];
        }

        $response = $this->client->send(
            new Request( 'GET', static::URL_USER_DATA, [
                'Authorization' => 'Bearer  ' . $accessToken,
            ] )
        );

        return json_decode( $response->getBody()->getContents(), true );
    }

    /**
     * @param string|null $code
     * @param string|null $state
     * @return string[]|null
     * @throws GuzzleException
     */
    protected function getToken( ?string $code, ?string $state ): ?array {

        if ( $state !== static::encode( $this->baseText(), $this->consumerSecret . "&" ) ) {
            return [ 'error_description' => 'Request substitution detected.' ];
        }

        $response = $this->client->send(
            new Request( 'POST', static::URL_ACCESS_TOKEN, [
                'Content-type' => 'application/x-www-form-urlencoded;charset=UTF-8'
            ] ),
            [
                RequestOptions::FORM_PARAMS => [
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => $this->urlCallback,
                    'client_id' => $this->consumerKey,
                    'client_secret' => $this->consumerSecret,
                    'code' => $code,
                ],
            ]
        );

        return json_decode( $response->getBody()->getContents(), true );
    }

    /**
     * @return string
     */
    protected function baseText (): string {
        return "GET&" .
        urlencode( static::URL_AUTHORIZE ) . "&" .
        urlencode(
            "redirect_uri=" . urlencode( $this->urlCallback ) . "&" .
            "client_id=" . $this->consumerKey . "&" .
            "scope=" . urlencode( 'r_liteprofile r_emailaddress' ) . "&" .
            "response_type=code"
        );
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
}
