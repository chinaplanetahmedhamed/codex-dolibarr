# M061-UI01-R01 — backup-erp email-action contrast release

## Authority

- Approved by Ahmed Hamed on 2026-08-25 with `ok do it` after the completed local M061-UI01 correction and the explicit notice that a new checksum-pinned staging correction was required.
- Target: canonical `backup-erp` only (`erp-staging`).
- Production access, real outbound email, business-data mutation, database/schema mutation, document changes and Mobile API correction are prohibited.
- Authorized outcome: deploy the local high-contrast staff email-action correction and verify it through a bounded no-send staging acceptance.

## Verified prestate

- Existing restricted M061-R02 helper `status` and `verify` passed immediately before preparation.
- Current release: `M061-R02-C03`.
- Current runtime commit: `487b61e690d7d030ec25d54a3b62afdb7e78fb55`.
- Current artifact SHA-256: `bf508b9e7b495092542160e4bb97ce1410c2013497d90bc6af70ff9a3498085f`.
- Current helper state: `DEPLOYED`; additive schema: `READY`.
- The existing Mobile API HTTP 500 acceptance blocker is outside this visual release and remains open.

## Exact runtime scope

Only these four staging files may change:

1. `custom/chinaplanetcustomercenter/balance.php`;
2. `custom/chinaplanetcustomercenter/css/customer_dashboard.css`;
3. `custom/chinaplanetcustomercenter/invoice.php`;
4. `custom/chinaplanetcustomercenter/project.php`.

The frozen baseline is the exact deployed C03 content for those paths. The candidate is local runtime commit `6032aae58d2067bff761b8556585f78f096e275f`.

## Release gates

1. Build a deterministic metadata-free artifact from the frozen runtime and support manifests.
2. Run PHP lint, focused source/service/responsive tests, artifact/package checks and helper contract tests locally.
3. Upload only the artifact, helper, sudoers source and checksum-pinned installer to the canonical staging inbox.
4. Install only the reviewed root-owned helper with exactly `compare`, `preflight`, `apply`, `verify`, `rollback`, and `status`.
5. Independently verify helper/sudoers hashes, modes, syntax and sudo surface.
6. Run `compare`; require zero drift against C03.
7. Run `preflight`; require canonical container/volume/localhost port, artifact, allowlist, candidate lint/test and adequate disk checks.
8. Run `apply`; create and hash an exact code backup before the four atomic replacements and automatically restore on failure.
9. Run `verify` and `status`.
10. Perform authenticated desktop and 390px visual acceptance of the button without submitting the email form.

Stop fail closed on the first identity, checksum, metadata, allowlist, baseline, environment, backup, lint, test, health or visual mismatch. Rollback must remain available.

## Evidence

Staging gate evidence is recorded in `docs/REPORTS/M061_UI01_R01_COMPLETION_REPORT.md`.

Current gate status on 2026-08-25: `DEPLOYED_VERIFIED` on canonical `backup-erp`.

- Artifact SHA-256: `50c217c3c2d25e688a71ffcf1eef821b70903ab93eee8e6f8a6bbd52890c0e4a`.
- Root runner SHA-256: `11b8daa990edfee816d3b4b557ec695219e305158ba62bb451eb7e2383f38e4b`; independently verified as `root:root` mode `700`.
- `compare`: `PASS`, four files at `BASELINE_C03`, `DRIFT_COUNT=0`.
- `preflight`: `PASS`, `FILES=4`, `SUPPORT=4`.
- `apply`: `PASS`; exact four-file code backup SHA-256 `bfed76fda6646c1b142fd5fd743b70d94990a7e98cfcee0048d1d9eefc6a365f`.
- `verify`: `PASS`, durable state `DEPLOYED`; `status` matched release `M061-UI01-R01`, runtime commit and artifact.
- Authenticated staff desktop `1280px` and phone `390px` visual acceptance passed for the financial statement, invoice and project email actions with no horizontal overflow or console warning/error.
- Mailpit remained at `Messages=0`, `SMTPAccepted=0`.
- `DATABASE_CHANGED=NO`, `EMAIL_SENT=NO`, `PRODUCTION_ACCESSED=NO`.
