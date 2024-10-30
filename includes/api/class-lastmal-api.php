<?php

if (!defined('ABSPATH')) {
    exit();
}

class LastmalAPI
{
    private $URL = "https://app.lastmal.com/api";
    private $token = null;

    public function __construct($config)
    {
        $this->token = $config['api_key'];
    }

    public function getRate($params)
    {
        if (!isset($params['sku'])) {
            return;
        }

        $args = array(
            'method'    => 'POST',
            'body'      => http_build_query($params),
            'timeout'   => 90,
            'sslverify' => false,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->token,
                "Cache-Control: no-cache",
                "Content-Type: application/json",
                "Accept: application/json"
            )
        );

        $response = wp_remote_post($this->URL . "/stores/get-rate", $args);
        $responseBody = json_decode(wp_remote_retrieve_body($response));

        return $responseBody;
    }

    public function getConfig()
    {
        $args = array(
            'method'    => 'POST',
            'timeout'   => 90,
            'sslverify' => false,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->token,
                "Cache-Control: no-cache",
                "Content-Type: application/json",
                "Accept: application/json"
            )
        );

        $response = wp_remote_post($this->URL . "/stores/get-config", $args);
        $responseBody = json_decode(wp_remote_retrieve_body($response));

        return $responseBody;
    }

    public function connectWC($params)
    {
        $args = array(
            'method'    => 'POST',
            'body'      => http_build_query($params),
            'timeout'   => 90,
            'sslverify' => false,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->token,
                "Cache-Control: no-cache",
                "Content-Type: application/json",
                "Accept: application/json"
            )
        );

        $response = wp_remote_post($this->URL . "/stores/connect-wc", $args);
        $responseBody = json_decode(wp_remote_retrieve_body($response));

        return $responseBody;
    }
}
