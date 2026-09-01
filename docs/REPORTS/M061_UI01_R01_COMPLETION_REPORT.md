# M061-UI01-R01 staging gate report

## Current verdict

`DEPLOYED_VERIFIED` on canonical `backup-erp` only.

## Frozen identities

- Runtime source commit: `6032aae58d2067bff761b8556585f78f096e275f`.
- Release tooling commit: `f2d9ba9`.
- Artifact: `m061-ui01-r01-email-action-6032aae.tar.gz`.
- Artifact SHA-256: `50c217c3c2d25e688a71ffcf1eef821b70903ab93eee8e6f8a6bbd52890c0e4a`.
- Installed runner: `/root/m061-ui01-r01/chinaplanetos-backup-erp-m061-ui01-r01`.
- Runner SHA-256: `11b8daa990edfee816d3b4b557ec695219e305158ba62bb451eb7e2383f38e4b`.
- Runner ownership/mode: `root:root 700`.

## Completed gates

- The deterministic metadata-free artifact rebuilt twice with the same SHA-256 and contained exactly the expected 11 regular members.
- Focused PHP/source/responsive/email-service tests, release/package contracts, PHP lint, shell syntax and `git diff --check` passed locally.
- The uploaded artifact, runner and installer matched their pinned SHA-256 values on `backup-erp`; server archive inspection returned the exact allowlist without warnings.
- The installer returned `M061_UI01_RUNNER_INSTALL=PASS`, `DEPLOY_EXECUTED=NO`, `DATABASE_CHANGED=NO`, and `PRODUCTION_ACCESSED=NO`.
- `compare` returned `M061_UI01_COMPARE=PASS`; all four runtime files matched the frozen C03 baseline and `DRIFT_COUNT=0`.
- `preflight` returned `M061_UI01_PREFLIGHT=PASS` with `FILES=4`, `SUPPORT=4`, the pinned artifact SHA-256, `DATABASE_CHANGED=NO`, `EMAIL_SENT=NO`, and `PRODUCTION_ACCESSED=NO`.
- The temporary Termius execution snippet was neutralized after the gate completed.
- After Ahmed's separate apply/verify approval, `apply` repeated the frozen-baseline and candidate checks, replaced exactly the four approved runtime files, and returned `M061_UI01_APPLY=PASS`.
- Code backup: `/root/backup-erp-code-backups/m061-ui01-before-6032aae-20260824T193609Z.tar.gz`; SHA-256 `bfed76fda6646c1b142fd5fd743b70d94990a7e98cfcee0048d1d9eefc6a365f`.
- Prestate SHA-256: `dcd0092b99f76ddf7d39d6ac20b145af220dd35f41ace9e534c04eac20c366cd`.
- `verify` returned `M061_UI01_VERIFY=PASS`, state `DEPLOYED`, runtime commit `6032aae58d2067bff761b8556585f78f096e275f`, and the pinned artifact SHA-256.
- `status` independently reported `STATE_STATUS=DEPLOYED`, the same release/runtime/artifact, backup path/hash and prestate hash.

## Authenticated no-send visual acceptance

- Staff session: existing staging `admin` session; test customer/project/invoice fixture only.
- Financial statement, invoice and project actions passed at `1280×720` and `390×844`.
- Every action rendered as a high-contrast button with at least `44px` height. The phone buttons fit within the 390px viewport at `273px` width.
- For all three pages, `documentElement.scrollWidth` equalled `clientWidth` at both viewports; no page-wide horizontal overflow occurred.
- Browser warning/error log was empty.
- No email action was clicked and no form was submitted.
- Read-only Mailpit verification returned `Messages=0`, `Unread=0`, `SMTPAccepted=0`, and `SMTPRejected=0`.

## Boundaries and remaining work

- No email was sent, no business or database data changed, and production was not accessed.
- `rollback` remains available and was not invoked because apply and verification passed.
- This visual correction does not resolve or reclassify the separate M061-R02-C03 Mobile API HTTP 500 blocker or other outstanding external-customer/payment-note/document evidence.
- Production promotion is not authorized.

## Rollback

Only after a separate exact rollback approval, run `/root/m061-ui01-r01/chinaplanetos-backup-erp-m061-ui01-r01 rollback`. The runner validates the recorded root-owned state and backup hash, creates a reapply safety backup, restores the exact prior four-file state atomically, and verifies it. Do not delete either backup.
