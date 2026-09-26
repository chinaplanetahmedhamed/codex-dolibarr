# Production read-only diagnosis — 2026-09-26

Explicit direct-live authorization continues from this task. Fixed-scope, checksum-pinned diagnostic ran in the existing authorized root console; no business writes.

Egypt Operation group 4 contains active internal users 1, 3, 9 and 11. The first two are native entity-zero administrators; group and membership entity are 1. Old query incorrectly required membership.entity=user.entity and excluded them. Corrected picker and server membership validation accept current-entity members plus native entity-zero administrators, with current-entity membership required.

Production PHP: upload_max_filesize=2M, post_max_size=8M, memory_limit=256M. Apache php_module loaded; docker-php.conf enables AllowOverride All under /var/www. No parent/task .htaccess overrides exist. ERP NPM route /data/nginx/proxy_host/2.conf has no smaller limit and inherits 2000m; the observed 50M setting belongs to a different route and is not changed. New task-folder .htaccess applies 200M file / 201M POST and bounded timeouts exclusively to asset.php.

M093 preflight stopped before writes: its smoke check assumed task 62 remained readable, but that task is now legitimately deleted. No M093 apply occurred. M094 includes prepared M093 code and replaces the fragile business-record smoke fixture with a synthetic in-memory task permission check against real active account/team membership. Deleted business task 62 is left untouched.
