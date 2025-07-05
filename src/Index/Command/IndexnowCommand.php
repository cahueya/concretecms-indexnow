<?php
namespace Concrete\Package\Indexnow\Index\Command;

use Concrete\Core\Application\ApplicationAwareInterface;
use Concrete\Core\Application\ApplicationAwareTrait;
use \Concrete\Core\Url\Resolver\CanonicalUrlResolver;
use Concrete\Core\Foundation\Command\Command;
use Concrete\Core\Support\Facade\Config;
use Concrete\Core\Page\PageList;
use Log;

defined('C5_EXECUTE') or die("Access Denied.");

class IndexnowCommand extends Command implements ApplicationAwareInterface
{
    use ApplicationAwareTrait;

    public function run(){
        $apiKey = Config::get('indexnow.api_key');
        if (!$apiKey) {
            return t('IndexNow API key not set. Configure it in the dashboard.');
        }
        $endpoint = Config::get('indexnow.endpoint') ?: 'https://api.indexnow.org/indexnow';

        $pl = new PageList();
        $pl->ignorePermissions();
        $pl->filterByAttribute('exclude_search_index', false);
        $allPages = $pl->getResults();
        $pages = array_filter($allPages, function ($page) {
            return !$page->isSystemPage();
        });

        if (empty($pages)) {
            return t('No public/searchable pages found.');
        }

        $urlResolver = $this->app->make(CanonicalUrlResolver::class);
        $urls = [];
        foreach ($pages as $page) {
            $urls[] = (string) $urlResolver->resolve([$page]);
        }
        $host = parse_url($urls[0], PHP_URL_HOST);

        $client = new \Concrete\Package\Indexnow\IndexNowClient($apiKey, $endpoint);
        $client->submitUrls($host, $urls);

        return t('Attempted to submit %d URLs to IndexNow.', count($urls));
    }



}