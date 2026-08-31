<?php
namespace Concrete\Package\Indexnow\Service;

use Concrete\Core\Support\Facade\Config;
use Concrete\Package\Indexnow\Http\IndexNowClient;
use Concrete\Package\Indexnow\Logging\IndexNowLogger;
use Concrete\Package\Indexnow\Queue\QueueRepository;
use Concrete\Package\Indexnow\State\PageStateRepository;

class QueueProcessor
{
    public function process()
    {
        $logger = new IndexNowLogger();
        $apiKey = trim((string) Config::get('indexnow.api_key'));
        if (!$apiKey) {
            $logger->debug('Queue processing skipped because no API key is configured.');
            return ['processed' => 0, 'submitted' => 0, 'failed' => 0, 'message' => t('No IndexNow API key configured.')];
        }
        $endpoint = (string) Config::get('indexnow.endpoint', 'https://api.indexnow.org/indexnow');
        $batchSize = max(1, min(10000, (int) Config::get('indexnow.batch_size', 500)));
        $maxAttempts = max(1, min(20, (int) Config::get('indexnow.max_attempts', 5)));
        $queue = new QueueRepository();
        // Bound each task run while still allowing several host batches.
        $rows = $queue->getReady(min(10000, $batchSize * 10));
        if (!$rows) {
            $logger->debug('Queue processor found no ready URLs.');
            return ['processed' => 0, 'submitted' => 0, 'failed' => 0, 'message' => t('No IndexNow URLs are ready to submit.')];
        }

        $groups = [];
        foreach ($rows as $row) {
            $groups[$row['host']][] = $row;
        }
        $client = new IndexNowClient($apiKey, $endpoint);
        $state = new PageStateRepository();
        $submitted = 0;
        $failed = 0;
        foreach ($groups as $host => $hostRows) {
            foreach (array_chunk($hostRows, $batchSize) as $chunk) {
                $urls = array_column($chunk, 'url');
                $result = $client->submitUrls($host, $urls);
                if ($result->success) {
                    foreach ($chunk as $row) {
                        try {
                            $state->markSubmitted(isset($row['cID']) ? (int) $row['cID'] : null, $row['url']);
                        } catch (\Throwable $e) {
                            // IndexNow already accepted this URL. Do not retain the
                            // queue row just because local bookkeeping failed, or
                            // the same URL would be submitted again on every run.
                            $logger->warning('IndexNow accepted URL but page-state bookkeeping failed.', [
                                'url' => $row['url'],
                                'cID' => isset($row['cID']) ? (int) $row['cID'] : null,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                    $queue->markSuccess($chunk);
                    $submitted += count($chunk);
                    $logger->debug('IndexNow batch accepted; queue rows removed.', ['host' => $host, 'count' => count($chunk), 'status' => $result->statusCode]);
                } elseif ($result->retryable) {
                    $queue->markRetry($chunk, $result->message, $maxAttempts, $result->retryAfterSeconds);
                    $failed += count($chunk);
                    $logger->warning('IndexNow batch failed temporarily; retry scheduled.', ['host' => $host, 'count' => count($chunk), 'status' => $result->statusCode, 'retryAfterSeconds' => $result->retryAfterSeconds, 'error' => $result->message]);
                } else {
                    $queue->markPermanentFailure($chunk, $result->message);
                    $failed += count($chunk);
                    $logger->error('IndexNow batch rejected; rows marked failed.', ['host' => $host, 'count' => count($chunk), 'status' => $result->statusCode, 'error' => $result->message]);
                }
            }
        }
        return [
            'processed' => count($rows),
            'submitted' => $submitted,
            'failed' => $failed,
            'message' => t('Processed %s URL(s): %s submitted, %s failed/retried.', count($rows), $submitted, $failed),
        ];
    }
}
