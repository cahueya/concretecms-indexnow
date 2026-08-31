<?php
namespace Concrete\Package\Indexnow\Support;

trait DbCompat
{
    protected function fetchAssoc($db, $sql, array $params = [])
    {
        if (method_exists($db, 'fetchAssociative')) {
            return $db->fetchAssociative($sql, $params);
        }
        return $db->fetchAssoc($sql, $params);
    }

    protected function fetchAllAssoc($db, $sql, array $params = [])
    {
        if (method_exists($db, 'fetchAllAssociative')) {
            return $db->fetchAllAssociative($sql, $params);
        }
        return $db->fetchAll($sql, $params);
    }

    protected function executeStatement($db, $sql, array $params = [])
    {
        if (method_exists($db, 'executeStatement')) {
            return $db->executeStatement($sql, $params);
        }
        return $db->executeUpdate($sql, $params);
    }
}
