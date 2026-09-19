# Customer PDF enquiry capture — M085

Status: exact deployed baseline matched; corrected local acceptance passed; staging installation and runtime acceptance pending. No live website or ERP application change has been made.

## Customer and staff workflow
Customers browse and calculate freely. Download focuses the contact form; name, international WhatsApp, destination and privacy consent are required, email/company optional. Submission recalculates the catalogue snapshot on the server, prepares a Dolibarr enquiry/prospect, renders the approved pages, and archives a raster-only PDF before requesting download. The customer and ERP receive identical bytes. No confirmed order, invoice, account or outgoing email is created.

Native prospect/contact creation is a suggestion, recorded privately. The enquiry remains new/received with no fk_soc or fk_socpeople until staff qualify it through the existing V2 workflow. Ambiguous matches remain unresolved. Existing CRM records are not overwritten; ordinary V2 intake still requires email. No Customer Center/mobile publication occurs.

The session receipt survives reload and failed image/upload/download attempts. Retries reuse the request and immutable document; changed selection/contact/container/language creates a fresh reference. Activity is download_requested, not proof of opening/saving. Capabilities expire after seven days; renewal preserves the original request key and consent timestamp. Consent older than30days requires a fresh explicit choice. The existing enquiry anonymizer also purges the four new private artifacts, including retry cleanup of previously anonymized rows.

## Exact runtime compatibility
Ahmed supplied read-only staging and production fingerprints on2026-09-19. All eight baseline files match source1f4e2dbdc49e813bf0c268d8f9461ad04711b426 on codex/m074-qc-field-report-composite. New worktree codex/m085-pdf-v2 preserves that cumulative baseline; the older main-based PR50 is superseded and must not deploy. Lifecycle/quotation source is unchanged.

Staging PHP8.2.32 allows22M POST; production PHP8.2.7 allows8M. The6,000,000bytePDF becomes8,000,000base64characters plus a small fixed JSON envelope, within8MiB. The gateway permits up to16,010,000 response bytes to accommodate Restler slash-escaped JSON, while decodedPDF remains capped at6MB. Other ERP response limits are unchanged.

## Acceptance evidence
-157 application tests:80server and77UI/unit; lint and production build pass.
-Updated native Dolibarr23.0.3/PHP8.4 isolated database exercises strictV2, optionalemail only dedicated receiver, native prospect/contact, staff qualification, idempotency/newversion, immutable archive/hash, denied tokens/entities/traversal/anonymized requests, and physical artifact cleanup.
-English desktop and Arabicphone browser submissions preserve identical customer/staffPDFs, reload and repeat download. Staff/noaccess/externalcustomer permission cases verified. Native references WEB-20260919-121338-BF476A and WEB-20260919-121413-0520C5.
-Image failure, successful archive followed by failed download, retry/reload and quantityversion tests pass. Earlier eight browser PDF layout cases cover AR/EN/FR desktop/phone and9page largeorder; reviewed visually.
-Independent Security and QA reviews identified and verified correction of retention, expiry and max-response edges. Separate staging release review covers descriptor-based file IO, disabled redirects and unhealthy-container rollback recovery.
-Local evidence is under /Users/mac/Documents/wordpress/outputs/b2b-pdf-leads. CMS catalogue/plan responses are synthetic; native ERP persistence, sessions and actual photo requests are real. These are not staging/production acceptance.

## Release procedure and limits
Fixed staging-only helper embeds eight committed candidate files and expected predecessor hashes; unchanged APIshim/lifecycle/quotation hashes are prerequisites. It refuses drift, unexpected resources, symlinks and replay. No migrations, module configuration, container restarts, proxy/DNS changes, document/business-data writes are performed by deployment. It verifies existing V2 schema and HMAC readiness without printing values.

Root installation grants only preflight/apply/verify/rollback/status for this immutable staging candidate. It does not apply code. Apply backs up scoped code with ownership/mode and hashes, atomically writes it, and restores on failed checks. Interrupted APPLYING state can be rolled back. Recovery retains all business records and documents. Newly needed French language directory may remain empty after rollback. Synthetic release tests cover drift, replay, corrupt backup, failure rollback, interrupted recovery, symlinks and directory-swap safety.

The restricted account has noM085helper; a checksum-verified root installation is required before autonomous staging execution. No generic/root/Docker privileges are granted. ERP receiver must pass staging/native UI acceptance before the website caller is merged/deployed. Production promotion remains a separate final gate after staging; rollback website first, then only scoped ERP code. All products/prices/variants/packing remain unchanged.

## Final local review closure
Independent Security/DevOps source review: PASS; independent QA/customer workflow review: PASS. Ten deployment fault tests pass. Expired-success browser recovery preserved native enquiry WEB-20260919-122445-6F3F24. Initial local attempt hit bundled Node24.19 undici assertion; rerun under Node23.11 passed. Node22/PHP8.2 staging acceptance remains explicitly pending. Root installer must execute a SHA-verified copy in a fresh root-owned0700directory using Python isolated mode, never execute the mutable upload directly.

## Ownership guard correction and GitHub route — 2026-09-20
The installed helper was independently verified at its pinned SHA and root:root0755. Its first preflight refused ROOT_OWNERSHIP_MISMATCH; no apply ran. Ahmed's root stat identified the existing application volume as www-data:www-data0755 and deployment state as root:root0700. The guard now requires these exact owners/groups/modes and continues rejecting symlinks or writable modes. No runtime ownership/permissions are changed.

The exact predecessor patcher is SHA2560e9b514136542f5dcd66cab323176b5b4bcf61c50df405a15801b1f7b3955ab6. It changes only installed helper97eaf56b… to f9b2db76010c710d81ee70c90cce52e260518bb230b9079f57e987d0683e4426, preserving the original embedded eight-file application payload1365666. A locked root-only backup is made; any started application deployment, unexpected predecessor or mismatch refuses the patch.12 regression tests and independent Security review pass.

At Ahmed's request to use GitHub, the existing registered deploy-backup-erp workflow gains a specific m085-r01 choice. Existing release jobs are preserved. The new job requires the exact feature branch and DEPLOY_M085_R01_TO_BACKUP_ERP confirmation, verifies installed helper hash/owner, and runs only scoped staging preflight/apply/verify with rollback after failed post-apply verification. Existing backup-erp SSH secrets are configured by name; no values were read. GitHub uses the same restricted account, so installing the root-owned helper correction remains a one-time root operation before dispatch. No GitHub deployment has been dispatched yet.
