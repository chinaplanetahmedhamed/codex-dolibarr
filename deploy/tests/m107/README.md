# M107 validation

Run the SQLite persistence and DOM renderer tests directly with PHP and Node. They do not connect to ERP.

Native/HTTP tests require the existing isolated synthetic M097 runtime, its original fixture accounts and schema, and M107_QA_ROOT pointing at the private QA folder. Native tests wrap business writes in rollback; HTTP uses the already established M097 client helper and persisted synthetic UI fixture IDs. No accounts, passwords or native permission grants are created. Never point these tests at production.

Native 24.0.1: workspace 25, legacy M097 58, authenticated HTTP 20. Native 24.0.2: same suites passed, plus physical compatible code/flag rollback preserving protected data/security digests. Browser acceptance: real customer/project/board navigation, desktop and mobile Arabic, English, and empty search; new-customer/project links remain hidden for accounts lacking native create rights. No stored rights were granted to add positive native-create coverage.
