<?php
namespace Concrete\Package\Indexnow;

use Concrete\Core\Page\Page;
use Concrete\Core\Package\Package;
use Concrete\Core\Support\Facade\Log;
use Concrete\Core\Command\Task\Manager;
use Concrete\Core\Support\Facade\Events;
use Concrete\Core\Support\Facade\Config;
use Concrete\Core\Application\Application;
use Concrete\Core\Command\Task\TaskService;
use Concrete\Package\Indexnow\IndexNowClient;
use Concrete\Package\Indexnow\Command\Task\Controller\IndexnowController;

class Controller extends Package
{
    protected $pkgHandle = 'indexnow';
    protected $appVersionRequired = '9.0.0';
    protected $pkgVersion = '0.9.1';
    protected $pkgAutoloaderRegistries = [
        'src/' => 'Concrete\Package\Indexnow',
    ];


    public function getPackageDescription()
    {
        return t('Instantly notifies search engines of page changes via IndexNow.');
    }

    public function getPackageName()
    {
        return t('IndexNow Integration');
    }

    public function on_start()
    {
        Events::addListener('on_page_version_approve', function($event) {
            $page = $event->getPageObject();
            if ($page && !$page->isSystemPage() && !$page->isPageDraft()) {
                $this->submitToIndexNow($page);
            }
        });

        $manager = $this->app->make(Manager::class);
        $manager->extend('indexnow', static function () {
            return new Command\Task\Controller\IndexnowController();
        });
    }

    public function submitToIndexNow(Page $page)
    {
        try {
            $apiKey = Config::get('indexnow.api_key');
            if (!$apiKey) {
                Log::addEntry('[IndexNow] No API key set. Skipping.');
                return;
            }

            $urlResolver = $this->app->make(\Concrete\Core\Url\Resolver\CanonicalUrlResolver::class);
            $url = (string) $urlResolver->resolve([$page]);
            $host = parse_url($url, PHP_URL_HOST);

            $endpoint = Config::get('indexnow.endpoint') ?: 'https://api.indexnow.org/indexnow';

            $client = new \Concrete\Package\Indexnow\IndexNowClient($apiKey, $endpoint);
            $client->submitUrls($host, [$url]);
        } catch (\Exception $e) {
            Log::addEntry('[IndexNow] Exception while submitting page: ' . $e->getMessage());
        }
    }

    public function install()
    {
        $pkg = parent::install();
        $this->installContentFile('tasks.xml');
        \Concrete\Core\Page\Single::add('/dashboard/system/seo/indexnow', $pkg);

    }

    public function update()
    {
        $pkg = parent::update();
    }

    public function delete()
    {
        $pkg = parent::delete();
    }

}
