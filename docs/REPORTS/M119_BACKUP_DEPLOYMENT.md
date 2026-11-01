# M119 Office Expenses — backup administrator pilot deployed

Verified 2026-11-01. Scope authorized by Ahmed: publish to **backup-erp first**, existing administrator only, Egypt/China, no automatic financial posting. Production was not inspected or changed. No native account, right, credential, proxy, DNS or public-access setting was changed.

## Delivered and verified

- URL: https://backup-erp.visiontrading.biz/custom/chinaplanetoperations/office_expenses/index.php
- [Successful Actions run 41310975615](https://github.com/chinaplanetahmedhamed/codex-dolibarr/actions/runs/41310975615), delivery `0163938ae71a512119edd1ee4c31633625f7de14`, branch `codex/m119-office-expenses-local`.
- Runtime source `8b14b9e9c609ed296cb18368f576bbf86fffadbb`; [private prerelease](https://github.com/chinaplanetahmedhamed/codex-dolibarr/releases/tag/m119-backup-pilot-8b14b9e).
- 31-file package: 29 runtime dependencies plus private native worker/release metadata. Artifact SHA256 `faa869ec8d05b6e631887bb1dbe0c403deb9d97909cea84ede492ee172bca698`, manifest SHA256 `19aa7af9ebc3f6f0dadb6708dec5b182f5dc55a884a42157abc79bb60ef329a7`. GitHub asset digests independently match. No original Finens DB, credentials, uploads, test writer or financial bridge is packaged.
- Actions preflight/apply/verify all returned `result=verified`, `financial_posting=false`. A separate post-UI restricted-SSH verify also passes. Anonymous public HTTPS returns200 with the native login form and exposes no synthetic draft.
- All automated checks pass:173 CLI cases, 9 synthetic HTTP, 7 actual Linux/www-data configuration, 18 actual MariaDB10.6.27 cases, 4 immutable-artifact, 9 bounded-backup/source and 29 fixed-helper tests. Two identity-suite auth/session cases remain explicitly skipped; do not count them as passed native login tests.

## Runtime, storage and scope

Canonical app `erp-staging-dolibarr-1`, current container `ed2c8761e65a0f9a7747eec302823e4e4dec9e2c769fd01db7b2fd6872bd3fed`, image `sha256:36e95f5a6ae4308c3fda7ea7b1b93bb845ce45cbdd0ddd0a990131bc6ffadcb9`. PHP 8.2.32/Dolibarr 24.0.1, BCMath, GD/JPEG and mysqli verified under UID/GID 33. Existing native configuration bytes, environment key/value map, documents/custom mounts, required network aliases and loopback ports were preserved. DB and Mailpit IDs, images and start times did not change.

The authoritative Compose source is stack 2 inside Portainer, not a host `/data/compose/2` directory. Only non-secret app image/private-volume lines were changed in the editor, with image re-pulling disabled. Portainer left the prepared app Created under a temporary name; a fixed-ID guarded finalization renamed/started only that existing container. Read-only settled runtime acceptance passes HTTP 200. The old app rootfs is retained as private image `sha256:9d364c8e1103a87062dbdce864584c1f16f68ad4480d5534430ccb222356cd73`.

Dedicated persistent volume `erp-staging_m119_office_expenses_private` is mounted at `/var/lib/chinaplanetos-office-expenses`, outside both web root and native documents. Parent root:33/0750; pilot configuration root:33/0640; receipts 33:33/0700, marker0600. JPEG/PNG only, 5 MiB per upload, 10 attachments and 25 MiB per draft; no automatic receipt deletion. Five owned operational schema tables were installed and independently verified; existing accounting tables were not repurposed.

Trusted binding restricts entry to existing active/valid internal administrator 1, entity 1, existing expense rights and enabled native module. Egypt/EGP and China/CNY preserve original money; **native entity base is USD**. Foreign originals require explicit quote/date/source, retained as estimates. Another existing native identity is denied by the actual worker. No native expense, payment, bank or ledger posting is enabled.

## Authenticated acceptance and exact limitations

The existing administrator session opens the deployed interface. Real native browser POST saves the clearly marked fixture `M119-UAT-EG-20261101`, amount **123.45 EGP**, office Egypt, synthetic quote 0.02 USD/EGP and explicitly synthetic rate source. POST/redirect displays exactly one draft and the message that nothing was posted to accounting. Record ID `621df42fb3b28cfc15cfe88741308763`. This is an operational draft, not a financial entry. It remains intentionally identifiable for audit; no cleanup deletes records.

**Receipt browser acceptance is blocked**, not passed: the extension's `fileChooser.setFiles` refused the generated synthetic PNG because Chrome's ChatGPT extension lacks `Allow access to file URLs`. No receipt upload POST or file transfer occurred, and the permission was not changed. The generated local fixture is `/Users/mac/Documents/Codex/2026-11-01/task/m119-uat/m119-synthetic-receipt.png`, SHA256 `df70f253a92276bfd2f5d275e65e02fb20cfd238a05f1960289a0fda40da24c1`. Manual upload through the normal app remains available; automated current original/preview download-byte verification remains pending.

The browser transport subsequently closed. The attempted China advance acceptance did not execute. Current live China-write, Arabic/mobile visual/screenshot and binary receipt acceptance are therefore **unverified**; automated currency/authorization/storage/HTTP fixtures do not replace those claims. No deployment operation is still running. Existing page copy still calls the interface a local/synthetic preview; this is a cosmetic follow-up, not a claim that the deployed persistence is local. The approved deployment remains an administrator-only backup pilot, not a production/accounting release.

## Recovery and operator instructions

The existing five-command helper `/usr/local/sbin/chinaplanetos-backup-erp-deploy` now pins this M119 release; SHA256 `845e497f2828df927ce14e5431d180a747c62bae264fbcee244183696cb967d9`. Existing sudo grants are unchanged; isolated system Python `-I`, exact request/action, package hashes and fixed staging resources are enforced. No arbitrary paths, commands or images are accepted. Actual Linux no-replace publication and UID33 preflight passed before installation.

Private state/audit/library/original-helper backup: `/var/lib/chinaplanetos-backup-erp-deploy/m119`. Additional original service/Compose/consistent DB backup: `/root/backup-erp-code-backups/m119-8b14b9e9c609`; DB gzip SHA256 `d8361ff032f970f8e5ef9acdced45f47159939b7d8121f68a52d715a4689bc2b` (363155 bytes), original definition SHA256 `12d6a52be23ebfe759e9e1f37ebc65a54bdaa7a57b095c642c0c9c4ca9d756a3`. These and the preserved images stay private on the VPS.

Rollback requires the exact matching root-owned deployment record and fixed request with action`rollback`. It removes only the recorded module's entrypoint through held directory descriptors and sets that owned inode mode000, retaining code, all subsequent DB rows/audits and private receipts. **Never restore the pre-release DB over later user records; never remove the private volume.** Replayed/partial apply is refused. After a killed process, timeout or lost SSH, inspect status and use explicit rollback/reconciliation; do not infer cleanup or retry apply. Older M014/M043/M045 deployers are not M119 verifiers.

Before wider or production use: complete the blocked browser/receipt/visual checks, verify ongoing backup coverage for the new private volume, review employee/office assignments and native currency/tax/reconciliation behavior, and obtain the relevant wider rollout/accounting authorization.
