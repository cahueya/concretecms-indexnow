# Changelog

## 1.0.1 - 2026-08-30

- Fixed install/upgrade fatal error on Concrete CMS 9.x caused by calling the nonexistent `Concrete\Core\Page\Single::getByPath()` method.
- Dashboard single-page existence is now checked with `Concrete\Core\Page\Page::getByPath()` and `isError()` before `Single::add()`.
- This release is intentionally version-bumped so sites where the 1.0.0 upgrade partially ran can safely retry with 1.0.1.

## 1.0.0 - 2026-08-30

- Replaced synchronous `on_page_version_approve` submission with a persistent debounce queue.
- Fixed page URL generation by using Concrete's `PageUrlResolver`.
- Added move, trash and delete lifecycle handling with prior-URL state.
- Added host grouping and configurable IndexNow batching up to 10,000 URLs.
- Added retry/backoff and inspectable failed queue rows; HTTP 429 honors `Retry-After` with a 10-minute minimum.
- Added queue reconciliation task.
- Replaced the old task implementation, eliminating its recursive `executeTask()` path.
- Added explicit HTTP connect/overall timeouts and response classification.
- Added optional detailed local Concrete logging for queue/API activity; API key is redacted and never logged.
- Added concurrency-safe queue claiming so overlapping task runs cannot submit the same claimed row, while newer page events are not lost.
- Added dashboard queue stats, recent queue inspection, manual processing/reconciliation and failed-row requeue actions.
