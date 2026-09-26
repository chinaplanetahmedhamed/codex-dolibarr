# M092 direct production release

Prepared runtime source: `c3f47f110c12d64cb3d2bcec3c561ea95d6d80e5`.
Only 13 task-control files change. The manifest pins the final verified M091-C02 predecessor and 16 unchanged prerequisites. No schema, hook, permission-grant or business-record migration.

Reproduce with `python3 deploy/releases/m092/build.py /private/tmp/m092-artifact`.
Run `python3 deploy/tests/test_m092_release.py` for integrity and recovery tests.

The production root console executes the verified helper bytes with `production preflight`, `production apply`, then `production verify`. Verify helper SHA-256 before each invocation; the helper verifies manifest, payload, baseline, dependencies, container/volume identity and file metadata. No staging or generic privileged wrapper.

Private recovery state: `/root/chinaplanetos-m092-production/`. Code and database backups are root-only, checksummed, and created before replacement. Apply failures restore predecessor code automatically. Explicit `production rollback` refuses post-release drift and restores only the 13 code files; it never replaces business records from the database backup.

Authenticated browser acceptance is required after helper verification. The helper performs additional read-only runtime checks using the existing staff identity and Agenda service, without modifying tasks.
