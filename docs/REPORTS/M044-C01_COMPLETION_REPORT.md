# M044-C01 completion report

## Verdict

`COMPLETED / BACKUP_ERP_DEPLOYED / AUTHENTICATED_UI_PASS / PRODUCTION_FORBIDDEN`.

The authenticated Shipping Prices import page loads again on canonical
`backup-erp`. The release added only the missing
`ShippingNoteTranslationService.class.php` dependency required by the already
deployed importer. It did not modify the importer, import page, database,
application configuration, shipping prices, documents, or business records.

## Release identity

- Mission: `M044-C01`.
- Source branch/PR: `codex/m044-c01-import-dependency` / PR `#12`.
- Preflight-correction branch/PR: `codex/m044-c01-prerequisite-correction` / PR `#13`.
- Runtime commit: `dc6387f41611f2bec24c5a96eb8aacc252f019ce`.
- Helper correction commit: `bebd550`.
- Main merge head after correction: `6294a198a6ea25964fd6aeca0e858668c45e6f55`.
- Artifact: `m044-c01-dc6387f.tar.gz`.
- Artifact SHA-256: `946479da0e9072ff83e2145ac5a82e75e2109459e262e449ef7375a53446a847`.
- Installed helper SHA-256: `f0d84b4781f32ee863f5899de1ef7ea3ea9b030fe7b952f87af2fc13a4b53f71`.
- Successful GitHub Actions run: `31221891055`.

## Approval and target

- Ahmed approved: “Approve installing the restricted M044-C01 staging sudo
  rule and deploying to backup-erp.visiontrading.biz”.
- Target: canonical `backup-erp.visiontrading.biz` only.
- Compose project: `erp-staging`.
- ERP container: `erp-staging-dolibarr-1`.
- Custom volume: `erp-staging_erp-staging_custom`.
- Production authority: none.

## Changed resources

### Staging runtime

- Added:
  `custom/chinaplanetshippingprices/class/ShippingNoteTranslationService.class.php`.

### Staging release controls

- Installed `/usr/local/sbin/chinaplanetos-backup-erp-m044-c01-deploy` as
  `root:root 0755`.
- Installed one staging-only sudoers file granting `codex-deploy` exactly:
  `compare`, `preflight`, `apply`, `verify`, `rollback`, and `status` for the
  fixed helper path.
- No shell, Docker, file-copy, wildcard, environment-preservation, or arbitrary
  argument grant was added.

### Repository

- Added the correction Mission, one-file runtime manifest, support manifest,
  dependency-contract test, staging helper, installer, sudoers contract, and
  manual GitHub deployment workflow.
- Corrected the read-only prerequisite pin to the verified M037 `import.php`
  hash already active on staging while retaining the exact M044 importer hash.

## Database, configuration, permissions, and visibility

- Database/schema: unchanged.
- Dolibarr module state and application configuration: unchanged.
- Business/customer/shipping-rate/document records: unchanged.
- Customer permissions and visibility: unchanged.
- Host permission impact: one narrowly scoped staging sudo rule with six exact
  helper commands, as separately approved by Ahmed.
- Customer Portal and Mobile App: `NOT_APPLICABLE` and unchanged.

## Tests and results

- PHP lint for the service and dependency test: `PASS`.
- M044-C01 source/dependency closure test: `PASS`.
- Helper, installer, sudoers, and workflow contract test: `PASS`.
- Deterministic artifact rebuild: `PASS`; both builds produced the pinned SHA.
- First workflow run `31221565605`: stopped at read-only compare with
  `IMPORT_PAGE_PREREQUISITE_DRIFT`; apply, verify, and rollback were not run and
  no runtime file changed.
- The drift was traced to the verified staging M037 import-page version. The
  correction pinned SHA-256
  `35261cc40213fab3988cb32a85eae170a1239799eaef8b0b573ab1da1245584c`.
- Successful run `31221891055`: compare `PASS`, preflight `PASS`, apply `PASS`,
  workflow verify `PASS`.
- Independent restricted status: `DEPLOYED`.
- Independent restricted verify: `PASS`.
- Authenticated desktop page: title, workbook form, shipping-agent selector,
  import controls, and batch history rendered without HTTP 500 or fatal error.
- Authenticated `390×844`: page title, workbook control, draft-action button,
  and batch history present; `innerWidth=390`, `scrollWidth=390`, fatal error
  absent.

## Backup and rollback

- Pre-change state: service file absent.
- Code backup:
  `/root/backup-erp-code-backups/m044-c01-before-dc6387f-20260807T215534Z.tar.gz`.
- Code backup SHA-256:
  `85cea451eec057fa7e734548ca3ba6d779ed5836a3f9de14b8394575ef0d7d8e`.
- No rollback ran because apply and both verification passes succeeded.
- If Ahmed separately approves a staging rollback, use exactly:
  `sudo -n /usr/local/sbin/chinaplanetos-backup-erp-m044-c01-deploy rollback`.
  It removes the newly added service because the recorded previous state is
  `ABSENT` and retains a reapply backup.

## Remaining boundaries and risk

- No workbook was uploaded during acceptance because that would create or
  modify shipping-rate records outside this correction Mission. Page loading
  and dependency closure are verified; a future data-safe upload acceptance is
  separate work if requested.
- Production still has the diagnosed missing dependency. This staging approval
  and successful artifact do not authorize production deployment.
- Production ERP, database, containers, volumes, routes, documents, helpers,
  and configuration were not accessed or changed by this Mission.

## Parity

- Admin ERP: `BACKUP_ERP_AUTHENTICATED_DESKTOP_AND_390_VERIFIED`.
- Customer Portal: `NOT_APPLICABLE`, unchanged.
- Mobile App: `NOT_APPLICABLE`, unchanged.
- Public React: `NOT_APPLICABLE`, unchanged.
