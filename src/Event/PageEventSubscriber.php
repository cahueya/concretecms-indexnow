<?php
namespace Concrete\Package\Indexnow\Event;

use Concrete\Core\Application\Application;
use Concrete\Core\Page\Page;
use Concrete\Package\Indexnow\Logging\IndexNowLogger;
use Concrete\Package\Indexnow\Queue\QueueRepository;
use Concrete\Package\Indexnow\State\PageStateRepository;
use Concrete\Package\Indexnow\Url\PageUrlProvider;

class PageEventSubscriber
{
    protected $queue;
    protected $state;
    protected $urls;
    protected $logger;

    public function __construct(Application $app)
    {
        $this->queue = new QueueRepository();
        $this->state = new PageStateRepository();
        $this->urls = new PageUrlProvider($app);
        $this->logger = new IndexNowLogger();
    }

    public function onPageVersionApprove($event)
    {
        $this->safely(function () use ($event) {
            $this->sync($event->getPageObject(), 'approved');
        }, 'approve');
    }

    public function onPageMove($event)
    {
        $this->safely(function () use ($event) {
            $this->sync($event->getPageObject(), 'moved');
        }, 'move');
    }

    public function onPageMoveToTrash($event)
    {
        $this->safely(function () use ($event) {
            $this->remove($event->getPageObject(), 'trashed');
        }, 'trash');
    }

    public function onPageDelete($event)
    {
        $this->safely(function () use ($event) {
            $this->remove($event->getPageObject(), 'deleted');
        }, 'delete');
    }

    /**
     * IndexNow is advisory. A queue/database/logging failure must never make a
     * page publish, move, trash or delete operation fail.
     */
    protected function safely(callable $callback, $eventName)
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            try {
                $this->logger->error('Page event could not be queued; Concrete operation was allowed to continue.', [
                    'event' => (string) $eventName,
                    'error' => $e->getMessage(),
                ]);
            } catch (\Throwable $ignored) {
                // Logging itself must not turn an advisory IndexNow failure into
                // a content-management failure.
            }
        }
    }

    protected function sync($page, $reason)
    {
        if (!$page instanceof Page || !$page->getCollectionID()) return;
        $cID = (int) $page->getCollectionID();
        $previous = $this->state->find($cID);

        $exclusionReason = $this->urls->getExclusionReason($page);
        if ($exclusionReason !== null) {
            if ($previous) {
                $this->queue->enqueue($previous['url'], $cID, 'excluded');
                $this->state->delete($cID);
                $this->logger->debug('Previously tracked page became non-trackable; old URL queued once.', ['cID' => $cID, 'url' => $previous['url'], 'exclusionReason' => $exclusionReason]);
            } else {
                $this->logger->debug('Page event skipped because the page is not a public/searchable URL.', ['cID' => $cID, 'eventReason' => $reason, 'exclusionReason' => $exclusionReason]);
            }
            return;
        }

        $url = $this->urls->resolve($page);
        if (!$url) {
            $this->logger->warning('Could not resolve page URL.', ['cID' => $cID, 'reason' => $reason]);
            return;
        }
        if ($previous && (string) $previous['url'] !== $url) {
            $this->queue->enqueue($previous['url'], $cID, 'url_changed');
        }
        $this->queue->enqueue($url, $cID, $reason);
        $this->state->save($cID, $url);
    }

    protected function remove($page, $reason)
    {
        if (!$page instanceof Page || !$page->getCollectionID()) return;
        $cID = (int) $page->getCollectionID();
        $previous = $this->state->find($cID);
        if ($previous && !empty($previous['url'])) {
            $this->queue->enqueue($previous['url'], $cID, $reason);
        } elseif (!$page->getCollectionPointerExternalLink()) {
            $url = $this->urls->resolve($page);
            if ($url) $this->queue->enqueue($url, $cID, $reason);
        }
        $this->state->delete($cID);
    }
}
