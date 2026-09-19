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

## Staging acceptance and authorized production preparation — 2026-09-20

Customer and authenticated staff staging acceptance passed for English desktop and Arabic phone. Both archived staff downloads match customer SHA256/byte counts; records persist after reload, download request counts are recorded, the suggested native prospect is reused, and anonymous private PDF requests require login. Runtime acceptance remains separate from local tests. Evidence is private under the wordpress workspace outputs/b2b-pdf-leads/staging.

Ahmed then reported reference links appearing inert. Reproduction showed the correct selected record below the full queue at931px, outside919px viewport. Four-line correction7d0ca969a164867ff11fb0e8c034ffff02bfcf5b shows the selected detail immediately with localized BackToList. Native local desktop/phone click, reload, PDF-return and back navigation pass. Its pinned one-file staging helper has separate backup/state;13release tests and independent Security/DevOps+QA pass. Staging apply awaits root helper installation.

Ahmed explicitly authorized live deployment with “ok push to live”. Production builder pins the eight-file receiver plus navigation correction at7d0ca96 against previously supplied baseline1f4e2db. It validates production container/volume/compose identity, PHP/schema, code/dependency hashes, local-container HTTP health and exact candidate hashes on staging. It retains scoped backups and rollback without migrations, restarts or data writes. Production helperSHA2562ee4df2182edb27c650239aa18b73e076af40fbc1f965a720ef0e21fa5e4a059;16synthetic fault/isolation tests and independent Security/DevOps+QA pass. Both helper installations are pending privileged execution; no production ERP operation ran.

The registered deploy-backup-erp workflow now offers separate fixed staging and production jobs/environments. Production confirmation is DEPLOY_M085_P01_TO_PRODUCTION. Do not dispatch production until authenticated staging reference acceptance passes. Website staging/main6395800 and productiond41718c were verified through browserhealth; production website must follow ERP deployment. Root installation instructions and exact hashes are in wordpress/outputs/b2b-pdf-leads/production-release/install-tools.md. No permission expansion beyond the fixed helper actions is requested.


## Production health guard correction — 2026-09-20
Ahmed's root diagnostic passed environment, comparison, schema, lint and staging-candidate checks. Only health failed: PHP allow_url_fopen=false prevents the old HTTP file_get_contents probe. Fresh restricted status confirms NOT_APPLIED with original helper 2ee4df2182edb27c650239aa18b73e076af40fbc1f965a720ef0e21fa5e4a059; no production application change.

Corrected helper SHA256 c34e2562d154df2ba44592e182609e74efb0984ac8506589a6a55f8df867da58 uses a fixed container-loopback PHP socket with timeout, response cap, HTTP200 and login-content checks. It does not change PHP settings. Exact predecessor root patcher SHA256 b0d2352e01e5211ddc54fbcf5a98a93bbb0767f4d6939073623ba41e98cb7d10 validates environment and the new health probe before replacing the helper; refuses started deployments, preserves a private backup and atomically installs. Application payload remains eight files at7d0ca96. Independent Security/DevOps and QA PASS;18 tests pass, including real PHP with URL fetching disabled and redirect/error rejection. Workflow pins corrected helper.

Corrected artifacts are in wordpress/outputs/b2b-pdf-leads/production-release/health-fix. Parent production-release files are historical original installation artifacts. Root helper replacement is pending; afterward re-run production preflight, deploy ERP through GitHub, promote the exact accepted website staging SHA, and verify native customer/staff PDF persistence. Existing live authorization persists.
