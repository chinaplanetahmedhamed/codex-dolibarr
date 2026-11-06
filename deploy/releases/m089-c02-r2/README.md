# M089 C02 R2 — personal worker dashboard

Application commit: `5f6dcbe49c52562505275e0913fc5e203e66a396`.

Ahmed authorized staging and production deployment. Each fixed helper embeds exactly ten committed runtime files and pins the current twelve dependencies. The helpers accept only compare, preflight, apply, verify, rollback and status after root installation. No migration, configuration, core, document or business-record mutation is included.

The environments have different existing interfaces. Production uses `custom/`; staging preserves the six existing M084 interface/translation files using `deploy/releases/m089-staging-overlay/custom/`. Task authorization, task service, JavaScript and board bytes are identical. Production preflight requires the exact accepted staging variant and matching application commit. This is not an identical-ten-file promotion.

Staging is Dolibarr 24.0.1; production is 23.0.3. Both helpers validate PHP 8.2, existing task schema, module state, fixed containers and volumes, baseline and dependency hashes, file ownership/modes, and local login health. The staging helper additionally checks the canonical loopback ERP and Mailpit bindings.

Private ten-file backups and state are retained under `/var/lib/chinaplanetos-m089-c02-r2-{staging,production}/`. Apply is single use. Failed post-apply checks restore all ten prior files. Failed recovery is recorded as `RECOVERY_REQUIRED`. Rollback rejects changed code or backup content and does not alter records.

Helpers were independently checksum/ownership verified after Ahmed's installation. Staging compare and preflight passed on 2026-09-23. Application deployment and authenticated acceptance were pending at this tooling commit.

Validation: both environment payloads match the application commit; 14 local integrity/recovery tests pass, including tampering, file metadata changes, symlinks/hardlinks, replay, failed apply, failed recovery and failed rollback. Local task authorization tests and M058/M060 source contracts pass; both PHP variants and shared JavaScript pass syntax checks. Arabic/English personal dashboard and forms were checked at phone and desktop sizes. Native staging and production employee acceptance remains a separate gate after deployment.

Use the `m089-c02-r2` option on the existing registered staging/production workflows. Production dispatch follows authenticated staging acceptance. The immutable helper SHA-256 pins are in those jobs.
