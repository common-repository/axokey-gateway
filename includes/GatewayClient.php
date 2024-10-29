<?php
/**
 * Client for the Axokey Payment Gateway
 *
 * PHP dependencies:
 * PHP >= 5.2.0
 * PHP JSON extension
 *
 * @author François-Marie Faure <francois-marie.faure@axokey.com>
 * @copyright Digital Media World 2012-2014
 * @package GatewayClient
 *
 */

/**
 * Custom class for the Gateway client Exceptions
 *
 * This class doesn't have any specific method or property.
 * We only use this class for its name, to separate between PHP Exceptions and our own exception.
 *
 * @author François-Marie Faure <francois-marie.faure@axokey.com>
 */
class AxokeyGatewayException extends Exception{}

/**
 * Transaction class for the Axokey Gateway
 *
 * This class allows a client to easily build any request for the Axokey Gateway.
 *
 * @author Francois Bard <fba@Axokey.com>
 */
class AxokeyGatewayTransaction {

    private $request;
    private $response;
    private $requestObject;

    private $URL;

    private $method;
    private $proxy;


    private $originatorID;
    private $password;

    /**
     * Constructor
     *
     * @param string $url
     * @param string $clientId
     * @param string $clientSecret
     */
    
    public function __construct($url, $clientId, $clientSecret)
    {
        $this->url          = $url;
        $this->clientId     = $clientId;
        $this->clientSecret = $clientSecret;

        if(!$this->url) {
            throw new AxokeyGatewayException("Cannot communicate with the Axokey API");
        }
        if(defined('WP_PROXY_HOST')) {
            $this->proxy = new stdClass();
            $this->proxy->host = WP_PROXY_HOST;
            $this->proxy->port = WP_PROXY_PORT;

            $this->proxy->username = WP_PROXY_USERNAME;
            $this->proxy->password = WP_PROXY_PASSWORD;
        }
        $this->initBearer();
    }

    private function initBearer()
    {
        $body = [
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret
        ];
        $response = $this->postData('oauth/token', $body, false);
        $this->bearer = $response->access_token;
    }

    private function postData($url, $body, $authorization = true)
    {
        $headers = array();
        $headers['Content-Type'] = 'application/json';
        $headers['Accept'] = "application/json";

        if($authorization) {
            $headers['Authorization'] = 'Bearer ' . $this->bearer;
        }

        $args = array(
            'method' => 'POST',
            'body'        => json_encode($body),
            'timeout'     => '5',
            'redirection' => '5',
            'httpversion' => '1.0',
            'blocking'    => true,
            'headers'     => $headers,
            'cookies'     => array(),
        );
        $response = wp_remote_post($this->url . $url, $args);
        if (!is_array($response) || is_wp_error($response)) {
            error_log($response);
            throw new \Exception('An error occured during the payment process, please contact us or try again in a few moments');
        }
        $response_body = wp_remote_retrieve_body($response);
        return json_decode($response_body);
    }

    private function getData($url, $authorization = true)
    {
        $headers = array();
        $headers['Content-Type'] = 'application/json';
        $headers['Accept'] = "application/json";

        if($authorization) {
            $headers['Authorization'] = 'Bearer ' . $this->bearer;
        }

        $args = array(
            'method' => 'GET',
            'timeout'     => '5',
            'redirection' => '5',
            'httpversion' => '1.0',
            'blocking'    => true,
            'headers'     => $headers,
            'cookies'     => array(),
        );
        $response = wp_remote_get($this->url . $url, $args);
        if (!is_array($response) || is_wp_error($response)) {
            error_log($response);
            throw new \Exception('An error occured during the payment process, please contact us or try again in a few moments');
        }
        $response_body = wp_remote_retrieve_body($response);
        return json_decode($response_body);
    }

    public function makeDirectPayment($products, $options = [])
    {
        $products = [
            'products' => $products
        ];
        $body = array_merge($products, $options);
        $response = $this->postData('api/checkout/direct-payment', $body, true);
        return $response;
    }

    public function getCartDetails($orderId)
    {
        $response = $this->getData('api/carts/' . $orderId);
        return $response;
    }

    public function getCompanyDetails()
    {
        $response = $this->getData('api/company');
        return $response;
    }
}
