<?php
namespace Concrete\Package\Indexnow\Http;

use Concrete\Package\Indexnow\Logging\IndexNowLogger;
use GuzzleHttp\Client;

class IndexNowClient
{
    protected $apiKey;
    protected $endpoint;
    protected $client;
    protected $logger;

    public function __construct($apiKey, $endpoint)
    {
        $this->apiKey = trim((string) $apiKey);
        $this->endpoint = trim((string) $endpoint);
        $this->client = new Client([
            'connect_timeout' => 3.0,
            'timeout' => 10.0,
            'http_errors' => false,
        ]);
        $this->logger = new IndexNowLogger();
    }

    public function submitUrls($host, array $urls)
    {
        $host = strtolower(trim((string) $host));
        $urls = array_values(array_unique(array_filter(array_map('strval', $urls))));
        if (!$this->apiKey || !$host || !$urls) {
            return new SubmissionResult(false, false, null, 'Missing API key, host, or URL list.');
        }
        $payload = ['host' => $host, 'key' => $this->apiKey, 'urlList' => $urls];
        $this->logger->debug('Submitting IndexNow batch.', [
            'endpoint' => $this->endpoint,
            'host' => $host,
            'count' => count($urls),
            'urls' => $urls,
        ]);
        try {
            $response = $this->client->post($this->endpoint, ['json' => $payload]);
            $status = (int) $response->getStatusCode();
            $body = trim((string) $response->getBody());
            $excerpt = $this->bounded($body);
            $this->logger->debug('IndexNow response received.', ['host' => $host, 'status' => $status, 'response' => $excerpt]);
            if ($status === 200 || $status === 202) {
                return new SubmissionResult(true, false, $status, $excerpt ?: 'Accepted');
            }
            $retryable = $status === 429 || $status >= 500;
            $retryAfterSeconds = null;
            if ($status === 429) {
                $retryAfterSeconds = $this->getRetryAfterSeconds($response->getHeaderLine('Retry-After'));
                // IndexNow's guidance says to wait at least 10 minutes after
                // a 429 when no longer delay is supplied by the endpoint.
                $retryAfterSeconds = max(600, (int) $retryAfterSeconds);
            }
            return new SubmissionResult(
                false,
                $retryable,
                $status,
                'HTTP ' . $status . ($excerpt ? ': ' . $excerpt : ''),
                $retryAfterSeconds
            );
        } catch (\Throwable $e) {
            $this->logger->error('IndexNow transport exception.', ['host' => $host, 'error' => $this->bounded($e->getMessage())]);
            return new SubmissionResult(false, true, null, $e->getMessage());
        }
    }

    protected function getRetryAfterSeconds($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 0;
        }
        if (ctype_digit($value)) {
            return (int) $value;
        }
        $timestamp = strtotime($value);
        return $timestamp === false ? 0 : max(0, $timestamp - time());
    }

    protected function bounded($text)
    {
        $text = trim((string) $text);
        return function_exists('mb_substr') ? mb_substr($text, 0, 2000) : substr($text, 0, 2000);
    }
}
