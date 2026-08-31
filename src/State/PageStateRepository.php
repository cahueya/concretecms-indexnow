<?php
namespace Concrete\Package\Indexnow\State;

use Concrete\Core\Support\Facade\Database;
use Concrete\Package\Indexnow\Support\DbCompat;

class PageStateRepository
{
    use DbCompat;

    public function find($cID)
    {
        return $this->fetchAssoc(Database::connection(), 'SELECT * FROM IndexNowPageState WHERE cID = ?', [(int) $cID]) ?: null;
    }

    public function getAll()
    {
        return $this->fetchAllAssoc(Database::connection(), 'SELECT * FROM IndexNowPageState ORDER BY cID');
    }

    public function save($cID, $url)
    {
        $db = Database::connection();
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $existing = $this->find($cID);
        if ($existing) {
            $this->executeStatement($db, 'UPDATE IndexNowPageState SET url=?, host=?, lastSeenAt=? WHERE cID=?', [$url, $host, date('Y-m-d H:i:s'), (int) $cID]);
        } else {
            $this->executeStatement($db, 'INSERT INTO IndexNowPageState (cID, url, host, lastSeenAt, lastSubmittedAt) VALUES (?, ?, ?, ?, NULL)', [(int) $cID, $url, $host, date('Y-m-d H:i:s')]);
        }
    }

    public function markSubmitted($cID, $url)
    {
        if (!$cID) return;
        $state = $this->find($cID);
        if ($state && hash_equals((string) $state['url'], (string) $url)) {
            $this->executeStatement(Database::connection(), 'UPDATE IndexNowPageState SET lastSubmittedAt=? WHERE cID=?', [date('Y-m-d H:i:s'), (int) $cID]);
        }
    }

    public function delete($cID)
    {
        $this->executeStatement(Database::connection(), 'DELETE FROM IndexNowPageState WHERE cID=?', [(int) $cID]);
    }
}
