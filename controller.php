<?php
namespace Concrete\Package\Indexnow;

defined('C5_EXECUTE') or die('Access Denied.');

use Concrete\Core\Command\Task\Manager;
use Concrete\Core\Package\Package;
use Concrete\Core\Page\Single;
use Concrete\Core\Support\Facade\Config;
use Concrete\Core\Support\Facade\Events;
use Concrete\Package\Indexnow\Command\Task\Controller\IndexnowController;
use Concrete\Package\Indexnow\Command\Task\Controller\IndexnowReconcileController;
use Concrete\Package\Indexnow\Event\PageEventSubscriber;
use Concrete\Package\Indexnow\Persistence\SchemaInstaller;

class Controller extends Package
{
    protected $pkgHandle = 'indexnow';
    protected $appVersionRequired = '9.0.0';
    protected $pkgVersion = '1.0.0';

    protected $pkgAutoloaderRegistries = [
        'src/' => 'Concrete\\Package\\Indexnow',
    ];

    public function getPackageDescription()
    {
        return t('Efficiently notifies search engines of changed URLs via a debounced IndexNow queue.');
    }

    public function getPackageName()
    {
        return t('IndexNow Integration');
    }

    public function on_start()
    {
        $subscriber = new PageEventSubscriber($this->app);
        Events::addListener('on_page_version_approve', [$subscriber, 'onPageVersionApprove']);
        Events::addListener('on_page_move', [$subscriber, 'onPageMove']);
        Events::addListener('on_page_move_to_trash', [$subscriber, 'onPageMoveToTrash']);
        Events::addListener('on_page_delete', [$subscriber, 'onPageDelete']);

        $manager = $this->app->make(Manager::class);
        $app = $this->app;
        $manager->extend('indexnow', static function () use ($app) {
            return $app->make(IndexnowController::class);
        });
        $manager->extend('indexnow_reconcile', static function () use ($app) {
            return $app->make(IndexnowReconcileController::class);
        });
    }

    public function install()
    {
        $pkg = parent::install();
        (new SchemaInstaller())->install();
        $this->installDefaults();
        $this->installContentFile('tasks.xml');
        if (!Single::getByPath('/dashboard/system/seo/indexnow')) {
            Single::add('/dashboard/system/seo/indexnow', $pkg);
        }
        return $pkg;
    }

    public function upgrade()
    {
        parent::upgrade();
        $pkg = Package::getByHandle($this->pkgHandle);
        (new SchemaInstaller())->install();
        $this->installDefaults();
        $this->installContentFile('tasks.xml');
        if (!Single::getByPath('/dashboard/system/seo/indexnow')) {
            Single::add('/dashboard/system/seo/indexnow', $pkg);
        }
    }

    public function uninstall()
    {
        (new SchemaInstaller())->uninstall();
        parent::uninstall();
    }

    protected function installDefaults()
    {
        $defaults = [
            'indexnow.endpoint' => 'https://api.indexnow.org/indexnow',
            'indexnow.debounce_minutes' => 5,
            'indexnow.batch_size' => 500,
            'indexnow.max_attempts' => 5,
            'indexnow.debug_logging' => false,
        ];
        foreach ($defaults as $key => $value) {
            if (Config::get($key) === null) {
                Config::save($key, $value);
            }
        }
    }
}
