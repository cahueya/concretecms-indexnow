<?php
namespace Concrete\Package\Indexnow\Persistence;

use Concrete\Core\Support\Facade\Database;
use Doctrine\DBAL\Schema\Table;

class SchemaInstaller
{
    public function uninstall()
    {
        $db = Database::connection();
        $sm = method_exists($db, 'createSchemaManager') ? $db->createSchemaManager() : $db->getSchemaManager();
        foreach (['IndexNowQueue', 'IndexNowPageState'] as $name) {
            if ($sm->tablesExist([$name])) {
                $sm->dropTable($name);
            }
        }
    }

    public function install()
    {
        $db = Database::connection();
        $sm = method_exists($db, 'createSchemaManager') ? $db->createSchemaManager() : $db->getSchemaManager();

        if (!$sm->tablesExist(['IndexNowQueue'])) {
            $table = new Table('IndexNowQueue');
            $table->addColumn('iqID', 'integer', ['autoincrement' => true, 'unsigned' => true]);
            $table->addColumn('urlHash', 'string', ['length' => 64]);
            $table->addColumn('url', 'text');
            $table->addColumn('host', 'string', ['length' => 255]);
            $table->addColumn('cID', 'integer', ['notnull' => false, 'unsigned' => true]);
            $table->addColumn('reason', 'string', ['length' => 32]);
            $table->addColumn('status', 'string', ['length' => 16, 'default' => 'pending']);
            $table->addColumn('queuedAt', 'datetime');
            $table->addColumn('availableAt', 'datetime');
            $table->addColumn('attempts', 'integer', ['unsigned' => true, 'default' => 0]);
            $table->addColumn('claimToken', 'string', ['length' => 32, 'notnull' => false]);
            $table->addColumn('claimedAt', 'datetime', ['notnull' => false]);
            $table->addColumn('lastAttemptAt', 'datetime', ['notnull' => false]);
            $table->addColumn('lastError', 'text', ['notnull' => false]);
            $table->setPrimaryKey(['iqID']);
            $table->addUniqueIndex(['urlHash'], 'idx_indexnow_queue_urlhash');
            $table->addIndex(['status', 'availableAt'], 'idx_indexnow_queue_ready');
            $table->addIndex(['host'], 'idx_indexnow_queue_host');
            $sm->createTable($table);
        }

        if (!$sm->tablesExist(['IndexNowPageState'])) {
            $table = new Table('IndexNowPageState');
            $table->addColumn('cID', 'integer', ['unsigned' => true]);
            $table->addColumn('url', 'text');
            $table->addColumn('host', 'string', ['length' => 255]);
            $table->addColumn('lastSeenAt', 'datetime');
            $table->addColumn('lastSubmittedAt', 'datetime', ['notnull' => false]);
            $table->setPrimaryKey(['cID']);
            $sm->createTable($table);
        }
    }
}
