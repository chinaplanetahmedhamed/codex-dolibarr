# ChinaPlanetOS Dolibarr agent instructions

This is the sole active source repository for Dolibarr/ChinaPlanetOS custom code. Extend the existing Dolibarr installation; do not build a replacement ERP or edit Dolibarr core without an approved architecture decision.

## Mandatory governance

Before scoped work, read and follow:

1. `/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/AGENTS.md`
2. `/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/docs/AGENTS.md`
3. `/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/.agent-workflow/WORKFLOW_RULES.md`
4. `/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/.agent-workflow/ROLE_REGISTRY.md`
5. The assigned approved Mission and only the evidence it routes.

Agent 007 is the central coordinator and default implementation owner. Other agents remain idle unless Ahmed explicitly requests delegation or the review-routing matrix requires an independent review. Keep one writer at a time.

## Mission and scope gate

- Read-only discovery and documentation are allowed before approval.
- Feature implementation requires an `APPROVED` numbered Mission or an explicit narrowly scoped Ahmed correction approval recorded in project documentation.
- Never absorb scope changes silently. Return material business, accounting, permission, schema, integration, or production-impacting decisions for approval.
- Mission approval never authorizes production.

## Engineering rules

- Confirm the Dolibarr version, repository, branch, runtime, enabled-module/database state, existing custom modules, hooks, triggers, permissions, routes, schema, and tests relevant to the task.
- Prefer, in order: standard Dolibarr behavior, configuration, extra fields, existing modules, then a small custom extension. Reuse core objects and services.
- Keep changes modular, reversible, entity-scoped, permission-aware, CSRF-protected, validated, escaped, upgrade-safe, and compatible with the installed PHP/Dolibarr versions.
- Never delete existing data or overwrite a custom module without inspection, approved backup, test, and rollback.
- External customers must be restricted by authenticated ownership such as `user->socid` / `fk_soc` and `entity`, never by URL parameters alone.
- Never expose supplier identity, cost, margin, profit, bank data, private notes, staff-only comments, internal metadata, private documents, credentials, or secrets.
- Separate customer-visible notes/files from internal notes/files.
- Assess every affected change across Admin ERP, Customer Center, and Mobile App. Record parity as implemented, pending, or not applicable; verify affected web UI at desktop and phone widths.

## Verification and reporting

- Run `php -l` on every changed PHP file.
- Run the smallest relevant module or Mission tests during development, then the full Mission test set and `git diff --check` before handoff.
- Verify authenticated runtime behavior, permissions, entity/customer isolation, direct-route access, negative cases, rendered UI, and real workflow outcome when applicable.
- Create the required completion report with files, data/config impact, security/visibility impact, tests, risks, rollback, branch, and commit evidence.

Before any VPS, staging, deployment, Docker, Compose, SSH, Portainer, Nginx, container, volume, backup, rollback, or production task, read `/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/skills/chinaplanetos-vps-operations/SKILL.md` and its routed current-state references. Production requires Ahmed's separate exact approval.

## Verified runtime status — 2026-08-13

This is a handoff snapshot. Re-run restricted compare/status, authenticated UI, schema, and real workflow checks before the next change.

- Production `https://erp.visiontrading.biz` and staging/backup `https://backup-erp.visiontrading.biz` returned HTTP 200.
- Restricted compare checks passed in both environments for the exact M046 six-file shipping-note runtime set.
- Both environments use runtime commit `15c19dda5842be992e6926db473878042a0a96d5` and artifact SHA-256 `79982ab606c958da9c7193c08464156c4e7e18f07d4ce5b65ba7b5af39164596`.
- The six verified files are `admin/batch.php`, `admin/setup.php`, `class/ShippingNoteReviewService.class.php`, `class/ShippingNoteTranslationService.class.php`, `class/ShippingPriceImporter.class.php`, and `class/ShippingPriceService.class.php` under `custom/chinaplanetshippingprices/`.
- Automatic carrier/source-note translation is intentionally disabled: stored/effective enablement `0`, provider calls `0`. Source notes remain internal. No translation approval is required and translation must not block publication.
- The production M041 additive shipping-note schema was applied and verified after backup. A real synthetic workbook upload then succeeded as unpublished Draft Batch `11`, reference `SPB-20260811-0ca924da`, with one accepted price and zero warnings; published prices were unchanged. Never publish this test batch.
- Production M046 code backup: `/root/chinaplanetos-production-code-backups/m046-c01-r1-before-15c19dd-m046-c01-20260811T132105Z-2483e7ed8f08.tar.gz`, SHA-256 `7c83469ecdb6353bf0d262dccb259ec730e5eb7998efbc4f375b0663064d6ab4`.
- Staging M046 code backup: `/root/backup-erp-code-backups/m046-c01-r1-before-15c19dd-m046-c01-20260810T133116Z-00074f2dbc14.tar.gz`, SHA-256 `a5731bd1d1374f0f8318fca98025b9ac1726c205ab8225bca0b3be16f9f0bb9f`.
- Repository baseline: `origin/main` was `d5c08831b3390cdb125e8fb503d55ceb581fef31`. The permanent local `main` remains preserved at `63a310c150b80087764605223e584b6e8b571cb9` and remains `ahead 1, behind 47`; never reset or clean it. M047 created clean branch/worktree `codex/m047-clean-baseline` from current `origin/main` and preserved the one local canonical-path documentation commit there. New refactoring work must start from the reviewed clean branch or a later current `origin/main`, not the diverged permanent worktree.

## What is available and where work stopped

- Website inquiries are received in ChinaPlanetOS/Dolibarr as operational review work.
- The quotation workspace presents requested products as rows, supports product images/snapshots, Dolibarr product mapping, Dolibarr or manual selling price selection, and line notes.
- Product selling price is per piece, not per carton. Packing/quantity must show pieces per carton, requested cartons, and total/requested pieces separately.
- WordPress/WooCommerce remains the initial CMS/product/packing source; Dolibarr remains the operational source of truth after intake.
- Shipping workbook upload uses a safe draft/review/publish workflow. Uploading a workbook must not change published prices until an authorized user explicitly publishes the accepted batch.
- Translation experimentation stopped with translation disabled. Do not restart Google Translate, DeepSeek, or another provider without a new approved Mission and privacy/security review.
- No new production change is approved after this snapshot. Draft Batch `11` may be inspected but must not be published; deleting it is a business-data change requiring explicit approval.

## Dolibarr incident playbook

### Shipping-price upload page failed to load

- Symptom: authenticated import page returned a server error while the public route or login page still returned HTTP 200.
- Root cause: a partial production deployment installed a newer `ShippingPriceImporter.class.php` that required `ShippingNoteTranslationService.class.php`, but the required class was absent.
- Fix: deploy the dependency-closed, pinned M046 six-file runtime package; run compare, preflight, request-scoped backup, apply, verify, and authenticated page acceptance.
- Prevention: derive the release manifest from actual runtime dependencies. Never deploy one changed PHP file when it imports a new class or assumes a new schema.

### `Unknown column 'source_notes_raw' in 'INSERT INTO'`

- Root cause: the newer importer was active while the target database lacked the M041 additive note/translation schema.
- Fix: back up the complete target database, apply and verify `deploy/migrations/m041_shipping_note_translation_schema.php` with SHA-256 `bc75838ff8d8ba807da59e6e5514e88f1770c050430780fb88c7ae2b5d9606c9`, then perform a real unpublished workbook upload and retain rollback evidence.
- Required additive columns: `source_notes_raw`, `source_notes`, `notes_en`, `translation_status`, `translation_source_hash`, `translation_provider`, `translation_model`, `translation_error`, `fk_user_translation_review`, and `date_translation_review`.
- Required index: `idx_chinaplanetsp_rate_translation (entity, fk_batch, translation_status)`.
- Never delete a recent batch or record to hide this error. The mismatch is code versus schema and must be corrected at that boundary.

### Publication demanded manual approval of every carrier/source note

- Symptom: `Approve every imported carrier/source note before publishing this batch.` or `Automatic translation is still pending or failed...` blocked the workflow.
- Cause: the earlier M044 translation/review experiment made note-review state a publication gate.
- Current policy/fix: automatic translation is disabled, external provider calls are disabled, source notes remain internal, optional editing may be added later, and translation status must not block publication.
- Do not restore manual-per-note approval or auto-accept translation logic unless Ahmed approves a new Mission.

### Translation provider testing was unreliable or unwanted

- Google Translate testing was limited to carrier/source-note text on unpublished staging batches; no customer/contact data was authorized.
- Ahmed rejected the resulting workflow and requested translation to be disabled for now.
- Keep `CHINAPLANETSP_AUTO_TRANSLATION_ENABLED` effectively false/zero and verify provider calls remain zero. Do not silently substitute another free provider.

### Product price and packing were shown as cartons

- Symptom: quotation rows showed `EUR / carton` or treated requested cartons as the unit selling quantity.
- Cause: the public catalog snapshot did not keep selling unit and packing/request quantities distinct.
- Fix: sell and quote per piece. Persist/map pieces per carton, requested carton count, and requested/derived piece count as separate fields. Use Dolibarr price or a manual per-piece price, and make the chosen unit explicit on lines and customer documents.
- Never guess pieces per carton. If source packing data is absent or inconsistent, flag the row for review.

### Product inquiry layout was unreadable

- Symptom: raw nested order-line data displayed as a long list of product IDs with no practical quotation workflow.
- Fix pattern: render a row-based table with image, product name/reference, packing and quantities, Dolibarr product mapping, price source, per-piece selling price, and notes; save as a standard Dolibarr draft quotation before any customer send.
- Re-verify image availability, customer ownership, entity isolation, permissions, totals, taxes/currency, PDF/output, and outbound customer delivery before claiming end-to-end completion.

### GitHub versus Portainer deployment confusion

- GitHub is the source-of-truth release path. Portainer operates the existing runtime stack and is not a second source repository or a place for manual code drift.
- Do not create a new stack for a normal release. Use exact commit/artifact pins and the approved restricted helper/workflow.
- Manual Portainer/server intervention is an emergency exception requiring exact environment/action approval and must be reconciled back to Git immediately.

### Page returned HTTP 200 but feature was broken

- An unauthenticated request may return the Dolibarr login HTML with HTTP 200 even when the authenticated module page fails.
- Acceptance requires an authenticated import-page load, a safe real draft workbook upload, expected accepted/warning counts, unpublished status, and confirmation that published prices and unrelated business data did not change.

## Release safety learned from M044–M046

- Code and additive schema are one compatibility boundary even when approvals and rollback steps are separate.
- Compare both runtime files and required database columns/indexes before apply.
- Back up the exact affected files and full database before production schema work; record hashes and rollback commands.
- Prefer the GitHub workflow and restricted mission-specific helper. Never broaden sudo or reuse a staging approval for production.
- Translation-disabled is an explicit policy state, not a temporary error to “repair.”
- Keep workbook tests as unpublished drafts. Publishing imported shipping prices is a separate business action and requires explicit approval.
