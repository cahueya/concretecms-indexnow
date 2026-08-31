<?php
namespace Concrete\Package\Indexnow\Service;

use Concrete\Core\Page\PageList;
use Concrete\Core\Support\Facade\Facade;
use Concrete\Package\Indexnow\Logging\IndexNowLogger;
use Concrete\Package\Indexnow\Queue\QueueRepository;
use Concrete\Package\Indexnow\State\PageStateRepository;
use Concrete\Package\Indexnow\Url\PageUrlProvider;

class Reconciler
{
    public function reconcile()
    {
        $app = Facade::getFacadeApplication();
        $list = new PageList();
        $list->ignorePermissions();
        if (method_exists($list, 'setSiteTreeToAll')) {
            $list->setSiteTreeToAll();
        }
        $list->setItemsPerPage(250);

        $urls = new PageUrlProvider($app);
        $queue = new QueueRepository();
        $state = new PageStateRepository();
        $logger = new IndexNowLogger();
        $queued = 0;
        $skipped = 0;
        $seen = [];

        $pagination = $list->getPagination();
        $totalPages = max(1, (int) $pagination->getTotalPages());
        for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++) {
            $pagination->setCurrentPage($pageNumber);
            foreach ($pagination->getCurrentPageResults() as $page) {
                $cID = (int) $page->getCollectionID();
                $seen[$cID] = true;
                if (!$urls->isTrackable($page)) {
                    $skipped++;
                    continue;
                }
                $url = $urls->resolve($page);
                if (!$url) {
                    $skipped++;
                    continue;
                }
                $previous = $state->find($cID);
                if ($previous && (string) $previous['url'] !== $url) {
                    $queue->enqueue($previous['url'], $cID, 'url_changed');
                }
                if ($queue->enqueue($url, $cID, 'reconcile')) {
                    $queued++;
                    $state->save($cID, $url);
                }
            }
        }

        // Recovery path: state entries no longer returned by the searchable-page scan
        // are queued once, allowing crawlers to discover a 404/redirect/exclusion.
        foreach ($state->getAll() as $oldState) {
            $cID = (int) $oldState['cID'];
            if (!isset($seen[$cID])) {
                $queue->enqueue($oldState['url'], $cID, 'missing');
                $state->delete($cID);
            }
        }

        $logger->debug('Reconciliation scan completed.', ['queued' => $queued, 'skipped' => $skipped]);
        return ['queued' => $queued, 'skipped' => $skipped, 'message' => t('Reconciliation queued %s current URL(s); %s page(s) skipped.', $queued, $skipped)];
    }
}
