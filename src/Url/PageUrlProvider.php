<?php
namespace Concrete\Package\Indexnow\Url;

use Concrete\Core\Application\Application;
use Concrete\Core\Page\Page;
use Concrete\Core\Permission\Access\Entity\GroupEntity;
use Concrete\Core\Permission\Key\Key;
use Concrete\Core\Url\Resolver\PageUrlResolver;
use Concrete\Core\User\Group\Group;

class PageUrlProvider
{
    protected $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function isTrackable(Page $page)
    {
        return $this->getExclusionReason($page) === null;
    }

    /**
     * Returns null for a public/searchable page, otherwise a diagnostic reason.
     */
    public function getExclusionReason(Page $page)
    {
        if (!$page->getCollectionID()) {
            return 'missing_collection_id';
        }
        if ($page->isSystemPage()) {
            return 'system_page';
        }
        if ($page->isPageDraft()) {
            return 'draft';
        }
        if ($page->getCollectionPointerExternalLink()) {
            return 'external_pointer';
        }
        if ($page->getAttribute('exclude_sitemapxml')) {
            return 'excluded_from_sitemap';
        }
        if (!$this->canGuestView($page)) {
            return 'not_guest_viewable';
        }
        return null;
    }

    public function resolve(Page $page)
    {
        $resolver = $this->app->make(PageUrlResolver::class);
        $resolved = $resolver->resolve([$page]);
        if (!$resolved) return null;
        $url = (string) $resolved;
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = (string) parse_url($url, PHP_URL_HOST);
        if (!$host || !in_array($scheme, ['http', 'https'], true)) {
            return null;
        }
        return $url;
    }

    /**
     * Evaluate the page's View permission specifically for Concrete's Guest
     * group. Checking the current user here would be unsafe because page events
     * and manual reconciliation are commonly run by an administrator.
     */
    protected function canGuestView(Page $page)
    {
        try {
            $key = Key::getByHandle('view_page');
            if (!$key) {
                return false;
            }
            $key->setPermissionObject($page);
            $access = $key->getPermissionAccessObject();
            if (!$access) {
                return false;
            }
            $guestGroup = Group::getByID(GUEST_GROUP_ID);
            if (!$guestGroup) {
                return false;
            }
            $entity = GroupEntity::getOrCreate($guestGroup);
            return (bool) $access->validateAccessEntities([$entity]);
        } catch (\Throwable $e) {
            // It is safer to omit a URL from search-engine notifications when
            // public visibility cannot be established conclusively.
            return false;
        }
    }
}
