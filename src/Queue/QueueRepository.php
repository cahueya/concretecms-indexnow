<?php
namespace Concrete\Package\Indexnow\Queue;

use Concrete\Core\Support\Facade\Config;
use Concrete\Core\Support\Facade\Database;
use Concrete\Package\Indexnow\Logging\IndexNowLogger;
use Concrete\Package\Indexnow\Support\DbCompat;

class QueueRepository
{
    use DbCompat;

    protected $logger;

    public function __construct()
    {
        $this->logger = new IndexNowLogger();
    }

    public function enqueue($url, $cID = null, $reason = 'changed', $delayMinutes = null)
    {
        $url = trim((string) $url);
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!$url || !$host || !in_array($scheme, ['http', 'https'], true)) {
            $this->logger->warning('Refused to queue invalid URL.', ['url' => $url, 'reason' => $reason]);
            return false;
        }

        if ($delayMinutes === null) {
            $delayMinutes = (int) Config::get('indexnow.debounce_minutes', 5);
        }
        $delayMinutes = max(0, min(1440, (int) $delayMinutes));
        $now = new \DateTimeImmutable('now');
        $available = $now->modify('+' . $delayMinutes . ' minutes');
        $hash = hash('sha256', $url);
        $db = Database::connection();
        $existing = $this->fetchAssoc($db, 'SELECT iqID FROM IndexNowQueue WHERE urlHash = ?', [$hash]);
        $params = [
            (int) $cID ?: null,
            substr((string) $reason, 0, 32),
            'pending',
            $now->format('Y-m-d H:i:s'),
            $available->format('Y-m-d H:i:s'),
            0,
            null,
            null,
            null,
            null,
            $url,
            $host,
        ];
        if ($existing) {
            $params[] = (int) $existing['iqID'];
            $this->executeStatement($db, 'UPDATE IndexNowQueue SET cID=?, reason=?, status=?, queuedAt=?, availableAt=?, attempts=?, claimToken=?, claimedAt=?, lastAttemptAt=?, lastError=?, url=?, host=? WHERE iqID=?', $params);
            $action = 'refreshed';
        } else {
            try {
                $this->executeStatement($db, 'INSERT INTO IndexNowQueue (cID, reason, status, queuedAt, availableAt, attempts, claimToken, claimedAt, lastAttemptAt, lastError, url, host, urlHash) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', array_merge($params, [$hash]));
                $action = 'queued';
            } catch (\Throwable $e) {
                // Two page events can reach the same URL at nearly the same
                // time. If another request won the unique urlHash insert race,
                // turn this into the same debounce-refresh operation. Re-throw
                // unrelated database failures.
                $raced = $this->fetchAssoc($db, 'SELECT iqID FROM IndexNowQueue WHERE urlHash = ?', [$hash]);
                if (!$raced) {
                    throw $e;
                }
                $refresh = $params;
                $refresh[] = (int) $raced['iqID'];
                $this->executeStatement($db, 'UPDATE IndexNowQueue SET cID=?, reason=?, status=?, queuedAt=?, availableAt=?, attempts=?, claimToken=?, claimedAt=?, lastAttemptAt=?, lastError=?, url=?, host=? WHERE iqID=?', $refresh);
                $action = 'refreshed';
            }
        }
        $this->logger->debug('URL ' . $action . '.', [
            'url' => $url,
            'host' => $host,
            'cID' => (int) $cID ?: null,
            'reason' => $reason,
            'availableAt' => $available->format(DATE_ATOM),
        ]);
        return true;
    }

    /**
     * Atomically claims a bounded set of ready rows for this processor run.
     * Concurrent task invocations therefore cannot submit the same claimed row.
     */
    public function getReady($limit)
    {
        $limit = max(1, min(10000, (int) $limit));
        $db = Database::connection();
        $this->releaseStaleClaims();

        $candidates = $this->fetchAllAssoc($db, "SELECT iqID FROM IndexNowQueue WHERE status='pending' AND availableAt <= ? ORDER BY availableAt ASC, iqID ASC LIMIT " . $limit, [date('Y-m-d H:i:s')]);
        $ids = array_map('intval', array_column($candidates, 'iqID'));
        if (!$ids) {
            return [];
        }

        $token = bin2hex(random_bytes(16));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$token, date('Y-m-d H:i:s')], $ids);
        $this->executeStatement(
            $db,
            "UPDATE IndexNowQueue SET status='processing', claimToken=?, claimedAt=? WHERE status='pending' AND iqID IN (" . $placeholders . ')',
            $params
        );

        return $this->fetchAllAssoc(
            $db,
            "SELECT * FROM IndexNowQueue WHERE status='processing' AND claimToken=? ORDER BY availableAt ASC, iqID ASC",
            [$token]
        );
    }

    public function markSuccess(array $rows)
    {
        foreach ($rows as $row) {
            $this->deleteClaimedRow($row);
        }
    }

    public function markRetry(array $rows, $error, $maxAttempts, $retryAfterSeconds = null)
    {
        $db = Database::connection();
        $now = new \DateTimeImmutable('now');
        foreach ($rows as $row) {
            $attempt = ((int) $row['attempts']) + 1;
            $claimToken = isset($row['claimToken']) ? (string) $row['claimToken'] : '';
            if ($attempt >= $maxAttempts) {
                $this->executeStatement(
                    $db,
                    "UPDATE IndexNowQueue SET status='failed', attempts=?, claimToken=NULL, claimedAt=NULL, lastAttemptAt=?, lastError=? WHERE iqID=? AND status='processing' AND claimToken=?",
                    [$attempt, $now->format('Y-m-d H:i:s'), $this->bounded($error), (int) $row['iqID'], $claimToken]
                );
                continue;
            }
            $minutes = min(1440, 5 * (2 ** max(0, $attempt - 1)));
            if ($retryAfterSeconds !== null) {
                $minutes = max($minutes, (int) ceil(max(0, (int) $retryAfterSeconds) / 60));
                $minutes = min(1440, $minutes);
            }
            $available = $now->modify('+' . $minutes . ' minutes');
            $this->executeStatement(
                $db,
                "UPDATE IndexNowQueue SET status='pending', attempts=?, claimToken=NULL, claimedAt=NULL, lastAttemptAt=?, lastError=?, availableAt=? WHERE iqID=? AND status='processing' AND claimToken=?",
                [$attempt, $now->format('Y-m-d H:i:s'), $this->bounded($error), $available->format('Y-m-d H:i:s'), (int) $row['iqID'], $claimToken]
            );
        }
    }

    public function markPermanentFailure(array $rows, $error)
    {
        if (!$rows) return;
        $db = Database::connection();
        foreach ($rows as $row) {
            $claimToken = isset($row['claimToken']) ? (string) $row['claimToken'] : '';
            $this->executeStatement(
                $db,
                "UPDATE IndexNowQueue SET status='failed', attempts=attempts+1, claimToken=NULL, claimedAt=NULL, lastAttemptAt=?, lastError=? WHERE iqID=? AND status='processing' AND claimToken=?",
                [date('Y-m-d H:i:s'), $this->bounded($error), (int) $row['iqID'], $claimToken]
            );
        }
    }

    public function requeueFailed()
    {
        $delay = max(0, (int) Config::get('indexnow.debounce_minutes', 5));
        $available = (new \DateTimeImmutable('now'))->modify('+' . $delay . ' minutes')->format('Y-m-d H:i:s');
        return $this->executeStatement(Database::connection(), "UPDATE IndexNowQueue SET status='pending', attempts=0, claimToken=NULL, claimedAt=NULL, lastError=NULL, lastAttemptAt=NULL, availableAt=? WHERE status='failed'", [$available]);
    }

    public function getStats()
    {
        $db = Database::connection();
        $rows = $this->fetchAllAssoc($db, 'SELECT status, COUNT(*) AS total FROM IndexNowQueue GROUP BY status');
        $stats = ['pending' => 0, 'processing' => 0, 'failed' => 0, 'total' => 0];
        foreach ($rows as $row) {
            $status = (string) $row['status'];
            $count = (int) $row['total'];
            $stats[$status] = $count;
            $stats['total'] += $count;
        }
        return $stats;
    }

    public function getRecent($limit = 25)
    {
        $limit = max(1, min(100, (int) $limit));
        return $this->fetchAllAssoc(Database::connection(), 'SELECT iqID, cID, url, host, reason, status, queuedAt, availableAt, attempts, lastError FROM IndexNowQueue ORDER BY iqID DESC LIMIT ' . $limit);
    }

    protected function deleteClaimedRow(array $row)
    {
        $this->executeStatement(
            Database::connection(),
            "DELETE FROM IndexNowQueue WHERE iqID=? AND status='processing' AND claimToken=?",
            [(int) $row['iqID'], isset($row['claimToken']) ? (string) $row['claimToken'] : '']
        );
    }

    protected function releaseStaleClaims()
    {
        $cutoff = (new \DateTimeImmutable('now'))->modify('-30 minutes')->format('Y-m-d H:i:s');
        $count = $this->executeStatement(
            Database::connection(),
            "UPDATE IndexNowQueue SET status='pending', claimToken=NULL, claimedAt=NULL WHERE status='processing' AND (claimedAt IS NULL OR claimedAt < ?)",
            [$cutoff]
        );
        if ($count) {
            $this->logger->warning('Released stale IndexNow queue claim(s) after an interrupted processor run.', ['count' => (int) $count]);
        }
    }

    protected function bounded($value)
    {
        $value = trim((string) $value);
        return function_exists('mb_substr') ? mb_substr($value, 0, 4000) : substr($value, 0, 4000);
    }
}
