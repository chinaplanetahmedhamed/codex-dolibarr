#!/bin/sh
set -eu
cd "$(dirname "$0")/../../.."
for test in amount service hardening concurrency export failure audit-scope ui-source rights summary view-shell; do
 php "deploy/tests/m116/$test.php"
done
php deploy/tests/m107/workspaces-persistence.php
node deploy/tests/m107/workspaces-ui.cjs
find custom/chinaplanetoperations/class -name 'CustomerAccount*.php' -exec php -l {} \;
for file in custom/chinaplanetoperations/lib/account_bootstrap.lib.php custom/chinaplanetoperations/tasks/account.php custom/chinaplanetoperations/tasks/account_api.php custom/chinaplanetoperations/tasks/boards.php custom/chinaplanetoperations/tasks/card.php custom/chinaplanetoperations/core/modules/modChinaPlanetOperations.class.php deploy/tools/m116_install_trial.php; do php -l "$file"; done
node deploy/tests/m116/presentations.cjs
node deploy/tests/m116/card-summary.cjs
php -l custom/chinaplanetoperations/tasks/account_summary.php
node --check custom/chinaplanetoperations/js/customer_account_presentations.js
node --check custom/chinaplanetoperations/js/customer_account.js
node --check custom/chinaplanetoperations/js/boards.js
git diff --check
