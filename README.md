# ConcreteCMS IndexNow Integration 1.0.1

This is an upgrade-compatible rewrite of the `indexnow` ConcreteCMS package. It keeps the existing package handle and existing API-key/endpoint configuration while changing normal page events from synchronous HTTP requests to a local, deduplicating queue.

## What changed in 1.0.x

- Uses Concrete's `PageUrlResolver`, so the actual page path is submitted instead of only the canonical site base URL.
- Page approvals, moves, trashing and deletion are tracked.
- Publishing no longer waits for IndexNow HTTP requests.
- Repeated changes to the same URL are debounced (default: 5 minutes).
- Requests are grouped by hostname and sent in configurable batches (default: 500; maximum: 10,000).
- Temporary errors use exponential retry; HTTP 429 responses respect `Retry-After` (with a 10-minute minimum); permanent/exhausted failures are retained for inspection/requeue.
- A reconciliation task can rebuild the queue from searchable pages.
- Optional debug logging records queue events, exact URL batches, endpoint/host, response status, retry decisions and bounded error/response text. **The API key is never logged.**

## Install / upgrade

Copy the `indexnow` directory into `packages/indexnow`.

For an existing 0.9.x installation, visit **Dashboard → Extend Concrete** and run the package upgrade (or use `concrete/bin/concrete c5:package-update indexnow`). The package handle remains `indexnow`, so the existing API key and endpoint are retained.

For a new installation, install **IndexNow Integration** normally and configure it under:

`Dashboard → System & Settings → SEO & Statistics → IndexNow`

## Tasks

Two Concrete tasks are registered:

- **IndexNow: Process Queue** (`indexnow:submit`) — submits URLs whose debounce timer has expired.
- **IndexNow: Reconcile Searchable Pages** (`indexnow:reconcile`) — scans searchable pages and queues their current URLs. Use this after installation/migration or as a recovery operation, not as the normal submission mechanism.

Schedule **Process Queue** regularly. A five-minute cadence is a sensible default when using the default five-minute debounce window. From Concrete's CLI, the task commands are normally invoked as `concrete/bin/concrete tasks:indexnow:submit` and `concrete/bin/concrete tasks:indexnow:reconcile`.

As required by IndexNow, the configured key must also be publicly verifiable using the appropriate key text file on the site's host (normally `/<API-KEY>.txt`, containing the key).

## Debug logging

Enable **Log IndexNow queue and API queries to the Concrete logs** in the package settings. Entries are visible under **Dashboard → Reports → Logs**. This records:

- URL queued/refreshed and why
- cID and ready time
- outgoing endpoint, host and exact `urlList`
- response HTTP status and bounded response text
- temporary retry scheduling
- permanent/exhausted failures

The IndexNow API key is explicitly removed from logger context and is never included in request debug entries.

## Event behavior

- `on_page_version_approve`: queue the current URL.
- `on_page_move`: if the URL changed, queue both the old URL and the new URL.
- `on_page_move_to_trash`: queue the last known URL once, then remove its tracked state.
- `on_page_delete`: queue the last known URL once, then remove its tracked state.
- Pages that are system pages, drafts, external pointer links, or carry `exclude_sitemapxml` are not treated as current searchable URLs. If a previously tracked page becomes non-trackable, its old URL is queued once so crawlers can discover the change.

## Notes

The reconciliation scan intentionally ignores editor permissions, like the old bulk task, and uses Concrete's sitemap exclusion attribute as the package's searchability signal. Sites with custom access/robots rules should review reconciliation results before relying on them.

## License

MIT. Based on the original ConcreteCMS IndexNow package by cahueya; see `LICENSE`.

## 1.0.1 compatibility fix

Version 1.0.1 fixes the package install/upgrade Dashboard-page existence check for Concrete CMS 9.x. `Concrete\Core\Page\Single` creates single pages but has no `getByPath()` method; the package now checks the path with `Concrete\Core\Page\Page::getByPath()` and `isError()` before calling `Single::add()`.
