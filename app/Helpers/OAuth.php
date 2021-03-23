<?php

namespace App\Helpers;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

abstract class OAuth {
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
        $this->client = new Client( [ RequestOptions::HTTP_ERRORS => false ] );
        $this->urlCallback = $urlCallback;
    }
}
