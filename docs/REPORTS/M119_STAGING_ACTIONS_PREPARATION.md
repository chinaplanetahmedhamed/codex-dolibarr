# M119 — Administrator pilot and GitHub Actions preparation

2026-11-01, Asia/Shanghai. Ahmed reiterated deployment to canonical `backup-erp` and asked why deployment was not using GitHub Actions directly. Existing authorization remains current-administrator-only, Egypt/China, operational drafts/receipts and disabled financial posting.

## Prepared application binding

`OfficeExpensePilotConfiguration` accepts only the exact administrator ID/entity, native internal active/valid administrator and expense rights/module, separately verified entity base currency, Egypt/EGP and China/CNY offices, two-decimal EGP/CNY/USD precision and a fixed private root. Ordinary requests cannot select or broaden configuration. A missing/invalid root-owned private configuration remains503; an unapproved identity receives403. The trusted existing native-factory injection contract is preserved.

Seven new native-library synthetic configuration cases pass; all13 CLI suites now total **173 passed, 0 failed**. Native identity still skips two auth/session scenarios with `--without-auth`. The changed HTTP entrypoint, actual Linux private-config read and real MariaDB tests remain separate release checks; local SQLite simulation is not promoted to deployment acceptance.

Independent review found no concrete pilot authorization/path bypass. It identified two verification improvements: exercise successful first-access reading as actual `www-data` and meaningful ownership/mode denials; make compose image/environment mismatch and missing service/environment fail preflight. The read-only probe now refuses those comparisons instead of merely reporting booleans.

## Fresh server and image observations

Current read-only checks pin `erp-staging-dolibarr-1` to image `sha256:56e459f0d2e7be2c9d809015b922624234f2899cc9f36e74fab1c5254913e482`, project/service `erp-staging`/`dolibarr`, and loopback18088. Existing database and Mailpit identities/ports match the canonical map. Current browser session is native internal administrator `admin`, ID1. No grant/account change was made.

The isolated PHP dependency image built successfully through the existing authenticated Portainer UI: `local/dolibarr:m119-bcmath-3fcaf460`, immutable ID **sha256:f4bb140e6a0ab98aeff0a3701b8f83d9ad28f3305a17bb604c4c320280a50b13**. It uses the verified current base, PHP8.2.32 and Dolibarr24.0.1, and passes BCMath/GD/mysqli and decimal/maximum-amount checks. Dockerfile SHA256 is `3fcaf460f8975313d590a86b57504c00869c0e32a0308353d708677bbabf8b09`. A first build compiled BCMath but its version probe unnecessarily loaded native configuration and failed; the corrected build reads only `version.inc.php`. Neither build replaced the current application. The mutable base tag must be reverified before any future rebuild; deploy only a reverified immutable candidate ID.

Before replacing a container, preserve and verify existing runtime configuration and filesystem overlays in addition to named volumes. A bare base-image replacement must not discard generated native configuration or later core changes. A new dedicated private root/mount and its backup remain unprovisioned.

## Actions route and pending gates

GitHub lists an active registered `deploy-backup-erp.yml`, but its existing deploy options pin older M043/M045 payloads. Repository-level secret names are empty; the existing protected **backup-erp** environment contains `DEPLOY_SSH_KEY` and `DEPLOY_KNOWN_HOSTS` (names only inspected; no values retrieved or changed). Reuse those for the eventual scoped deployment. No existing generic/fixed release helper was executed for M119.

The additive `m119-check` option runs on the selected immutable workflow revision without connecting to the VPS or consuming its SSH secrets. It checks official Dolibarr24.0.1 source commit `b7958385f00a92219f76a4dfea67dae93df97a25`, all safe CLI/HTTP fixtures, actual root-owned configuration reads as `www-data`, and a loopback-only disposable MariaDB10.6.27 service. Its public synthetic CI password is fixture data, not a VPS/application credential. Its newly generated database has an ownership nonce, contains no native business/identity tables, and is removed only after that nonce is verified.

`m119-check` is verification preparation, **not an application deployment**. A scoped immutable artifact, reviewed mission-specific server helper, initial dependency/private-storage/schema setup, consistent backup/rollback and current authenticated transport/UI acceptance remain necessary before the pilot can be marked usable. A native read-only preflight reached compose validation and stopped with exit1; host-side access to `/data/compose/2/docker-compose.yml` must be reconciled against the actual Portainer-managed source before any service replacement. An old Compose label alone is not an executable release definition.

First Actions run `41301497937` correctly stopped during the native SQLite rollback fixture with `Cannot create dynamic property SQLite3Exception::$xdebug_message`; no VPS step ran. The workflow now pins PHP8.2.32 and required extensions through immutable setup-php commit `eb7c497e18156a6bbabfef1d3a82760b9eda3962`, with no coverage/debug driver, and explicitly checks `PHP_VERSION`. All failure/rollback tests remain enabled. This is runner alignment; it is not accepted as a successful rerun until fresh evidence exists.

No application files, existing database/schema, service/container configuration, proxy/DNS, security permissions, SSH credentials or accounting records were changed. Changes on the VPS so far are a separate unused dependency image/build intermediates and private non-secret M119 probe/receipt files under the existing inbox. Production resources were not inspected or modified. Runtime deployment and actual activation remain pending.
