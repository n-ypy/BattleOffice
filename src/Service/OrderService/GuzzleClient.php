<?php

namespace Stripe\HttpClient;

use GuzzleHttp\Client as Guzzle;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\UnexpectedValueException;

class GuzzleClient implements ClientInterface
{
    private $guzzle;
    private $timeout = 80; // seconds
    private $connectTimeout = 30; // seconds

    public function __construct()
    {
        $this->guzzle = new Guzzle();
    }

    public function request($method, $absUrl, $headers, $params, $hasFile)
    {
        try {
            $options = [
                'headers' => array_merge($headers, ['Expect' => '']),
                'timeout' => $this->timeout,
                'connect_timeout' => $this->connectTimeout,
                'http_errors' => false,
            ];

            if ($hasFile) {
                $options['multipart'] = $this->prepareMultipartData($params);
            } else {
                if ($method === 'get') {
                    $options['query'] = $params;
                } else {
                    $options['form_params'] = $params;
                }
            }

            $response = $this->guzzle->request(strtoupper($method), $absUrl, $options);
            $rbody = (string) $response->getBody();
            $rcode = $response->getStatusCode();
            $rheaders = $response->getHeaders();

            return [$rbody, $rcode, $rheaders];
        } catch (\Exception $e) {
            throw new ApiConnectionException("Failed to connect to Stripe: {$e->getMessage()}");
        }
    }

    private function prepareMultipartData(array $params)
    {
        $multipart = [];
        foreach ($params as $name => $contents) {
            $multipart[] = ['name' => $name, 'contents' => $contents];
        }
        return $multipart;
    }
}