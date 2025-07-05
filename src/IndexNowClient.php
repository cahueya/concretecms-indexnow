<?php
namespace Concrete\Package\Indexnow;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Concrete\Core\Support\Facade\Log;

class IndexNowClient
{
    protected $endpoint;
    protected $apiKey;

    public function __construct($apiKey, $endpoint = 'https://api.indexnow.org/indexnow')
    {
        $this->apiKey = $apiKey;
        $this->endpoint = $endpoint;
    }

    public function submitUrls($host, array $urls)
    {
        $client = new Client();
        $body = [
            'host' => $host,
            'key' => $this->apiKey,
            'urlList' => $urls,
        ];

        try {
            $response = $client->post($this->endpoint, [
                'json' => $body,
                'headers' => [
                    'Content-Type' => 'application/json'
                ]
            ]);

            $httpCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($httpCode === 200 || $httpCode === 202) {
                Log::addEntry('[IndexNow] Submitted ' . count($urls) . ' URLs successfully (HTTP ' . $httpCode . ').');
            } else {
                Log::addEntry('[IndexNow] Submission failed! HTTP Code: ' . $httpCode . '. Response: ' . $response . ' CurlError: ' . $curlError);
            }
        } catch (RequestException $e) {
            $error = $e->getMessage();
            Log::addEntry('[IndexNow] GuzzleHttp error: ' . $error);
        }
    }
}
