# M061-R02-C01 — Metadata-free artifact correction

## Mission control

- Type: local release-package correction
- Status: DEVELOPER_REPORTED — local correction complete
- Owner: Agent 007
- Approved by Ahmed Hamed: `إعداد M061-R02-C01 محليًا بأرشيف metadata-free وتحديث جميع الهاشات والاختبارات فقط.`
- Approval date: 2026-08-25 Asia/Shanghai
- Server access, upload, helper installation, compare, preflight, apply and production: prohibited

## Trigger

The frozen M061-R02 archive contained macOS `LIBARCHIVE.xattr.com.apple.provenance` PAX metadata. GNU tar on canonical `backup-erp` emitted warnings for all 30 members, and the restricted helper correctly stopped fail closed with `ARTIFACT_STRUCTURE_INVALID` before extraction or runtime/schema inspection.

## Authorized correction

- Preserve the exact 19 runtime files, 8 support files and 3 manifests from the frozen commits.
- Build a deterministic USTAR archive directly from checksum-pinned Git blobs.
- Normalize every member to regular file, mode `0644`, owner/group `0:0`, names `root:root` and timestamp `1980-01-01T00:00:00Z`.
- Emit deterministic stored-block gzip with no PAX headers, xattrs, resource forks or host filesystem metadata.
- Refresh the artifact, helper and transactional-upgrade installer SHA-256 pins.
- Add regression assertions for GNU-tar warning-free listing, exact allowlist, duplicate rejection, normalized metadata and byte equality with the frozen commits.

## Boundaries

Runtime and support content must remain byte-identical to the frozen M061-R02 inputs. The installed helper path and six-command sudo surface remain unchanged. This correction does not authorize any server or business-data mutation.

## Local result — 2026-08-25

- Local implementation commit: `e2a58636083c67c724e69296c03675b0d104cd10`
- Corrected artifact: `/private/tmp/m061-r02-c01-customer-center-487b61e.tar.gz`
- Artifact SHA-256: `3273970c7caad1a4580f662d003cabf5ef1274ce3149c6e9fb83f4670680a870`
- Builder SHA-256: `11e5c6a42c10cdf062852299e36ab8a4b5770a64013fe833d0ee827d317031df`
- Corrected helper SHA-256: `b93d60e91ec40e9ad57ebb79a49bf0531e9acfbca5107a49b426cdbd480a6ec7`
- Transactional upgrade installer SHA-256: `4630420585ab732b1783312c16b8acaebe528868c28c1c0b9422f6c4ba366f7c`
- Unchanged sudoers SHA-256: `95967d58463448006f7b9f31bf3b971ef5a5664cc3cde4fe6829c77702750671`
- Runtime/support/baseline manifest SHA-256 values remain `928f9050…1e64`, `3997ac41…bcdc` and `81d0a463…0629` respectively.
- Repeated builds produced the same artifact SHA. All 30 new payload hashes equal both the previous archive payloads and the frozen Git blobs.
- Local BSD tar listed exactly 30 members with zero warnings. GNU tar is not installed locally; the archive is standard USTAR and Python inspection confirmed no PAX headers on any member. The corrected server gate remains separately approval-bound.
- Full local M061 static test set, Mobile API contract, release-package test, helper contract, PHP lint, shell syntax and `git diff --check` passed.
- No upload, SSH, server helper installation, compare, preflight, apply, runtime/database change, email or production access occurred.

## Backup-ERP upload gate — 2026-08-25

- Ahmed separately approved `ok push to backup`; this gate was interpreted as upload to the canonical `backup-erp` inbox only.
- Read-only preflight confirmed restricted identity `codex-deploy`, inbox `codex-deploy:codex-deploy 0700`, installed old helper SHA-256 `738cca9f02a882063f0f5f29dc0045e173220ffb9a738f1ffc11a8e961754a23`, and the expected six-command sudo surface.
- The four C01 files were uploaded to `/home/codex-deploy/staging-inbox` and independently verified as regular `codex-deploy:codex-deploy 0644` files with the artifact/helper/installer/sudoers hashes recorded above.
- GNU tar on `backup-erp` listed exactly 30 regular members with zero warnings, resolving the C01 packaging objective.
- The old inbox helper and installer were preserved under checksum-suffixed filenames. The installed root helper remained byte-identical to the old SHA-256; no installer, helper command, compare, preflight, apply, database/schema action, email or production access occurred.
- Next gate requires Ahmed's separate exact approval to run the checksum-pinned installer and replace only the known old helper on canonical `backup-erp`.
