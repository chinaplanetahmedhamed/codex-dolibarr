# M068 Local Completion Report

Date: 2026-08-30

Mission: Website Lead, Quotation Request, and Customer Center Lifecycle

Contract: `cpo.catalog-order.v2` / `schema_version=2`
Approval used: `APPROVE M068 AS WRITTEN FOR LOCAL DOLIBARR IMPLEMENTATION ONLY.`

## Control record

- Repository baseline: `origin/main` at `057af0f205f819c685869b3ee6aa1aea31ea549e` when the clean worktree was created.
- Branch: `codex/m068-website-lead-quotation-portal`.
- Worktree: `/Users/mac/Documents/dev/chinaplanet-OS/.worktrees/m068-website-lifecycle`.
- Agent 007 remained the sole Dolibarr code-writing owner. Independent agents were read-only reviewers.
- The dirty/diverged permanent checkout was not switched, pulled, merged, reset, cleaned, or edited.
- No push, deployment, staging/production mutation, real-customer contact, or real-customer test data was used.
- The implementation commit is the local commit containing this report; its exact hash is recorded in the final handoff.

## Delivered locally

- The existing secure website intake now accepts the frozen v2 contract while preserving v1 behavior and strict idempotency. It retains every submitted line and the immutable customer-safe price/packing snapshot.
- Qualification provides normalized, same-entity contact and Third Party email/phone suggestions. Exact or ambiguous matches persist an audited stopping state; staff must explicitly link an active Third Party/contact. Reviewed lead creation creates a Prospect/Lead plus primary contact only when no exact match exists.
- Working quotation lines are separate from immutable intake. Staff can map products and record only complete, approved-source packing. No missing packing, price, supplier cost, or margin is guessed.
- The standard draft `Propal` flow uses the exact qualified customer/contact, selling prices only, explicit currency, separate private staff notes and approved public descriptions, and remains staff-owned for validation, sending, acceptance, and order conversion.
- Customer Center adds separate Orders and Quotation Requests journeys. Request references, submitted lines, approved replies/files, and explicitly customer-visible proposals are scoped by `entity + authenticated user fk_soc`. Public URLs use the website reference rather than a database row ID.
- Invitation uses an expiring, hashed, one-time token; checks email/login conflicts; issues at most 3 per request/email per hour; and limits activation to 5 attempts per token/IP-HMAC in a rolling 15-minute window. Dolibarr authenticates the normalized email through its native email branch while retaining a framework-safe internal login that is never customer-facing.
- Password reset uses the same one-time password-set mechanism and a non-enumerating public response. It never grants Core reset right 343, never emails a plaintext password, and cannot create or restore portal membership: the exact active external user must already have current Customers Portal membership and right 500201.
- Provisioning of rights, membership, landing page, user state, and invitation is fail-closed and transaction-safe. Failed new-user provisioning removes the incomplete user.
- Approved publications use allowlisted formats, bounded size, randomized customer tokens, canonical path containment, private filesystem modes, and tenant checks.
- Arabic RTL and English LTR labels, logical navigation arrows, responsive 360/390px source rules, 44px touch targets, 16px form inputs, and visible keyboard focus are present. Customer Center French remains explicitly out of M068 scope; public React French remains owned by the React mission.
- Mobile portal parity is recorded as pending/out of scope; React remains public discovery/intake and authenticated operations remain in Dolibarr Customer Center.

## Local verification evidence

- PHP syntax: all changed and new PHP files passed.
- `M068_SCHEMA_CHECK=PASS`; the additive local schema was previously applied with `GRANTS=EXPLICIT BUSINESS_RECORDS=NONE`.
- `M068_LIFECYCLE_SOURCE=PASS READY_UNITS_KEY=unitsPerCarton`.
- `M068_INVITATION_SECURITY_SOURCE=PASS CUSTOMER_LOGIN=email CORE_LOGIN=internal ISSUE_LIMIT=3/hour ATTEMPT_LIMIT=5/15m STORAGE=hash-only`.
- `M068_PORTAL_CURRENCY=PASS FOREIGN=multicurrency_total BASE=total_ht ISO=explicit`.
- Full synthetic runtime passed intake/dedupe, ready and incomplete lines, safe stop/link/create, packing completion, draft proposal, native proposal acceptance and draft-order conversion, tenant/file denial, invite, email authentication, activation, password reset, no-regrant, and cleanup:
  - `M068_RUNTIME=PASS ... RESET=one-time-email+no-regrant ...`
  - `M068_RUNTIME_CLEANUP=PASS SYNTHETIC_ONLY=YES`
- Customer portal scope integration passed, including forged-customer denial.
- Backward/regression checks passed for M003, M031, M032, M043 source/runtime, M045, M053, and M055 contact/reply behavior.
- `git diff --check` passed.

## Independent local reviews

- Security: PASS, including the final non-provisioning reset path.
- Customer Center/tenant lifecycle: PASS.
- UI/UX and Arabic/English source acceptance: PASS.
- Accounting/commercial proposal handling: PASS.
- QA/backward compatibility: PASS.

Earlier correction-required findings were resolved before these final verdicts: the v2 `unitsPerCarton` key, durable stopping state, company identity matching, non-secret invitation result, email authentication, rolling limits, transaction-safe provisioning, private/public proposal text separation, multicurrency totals, v2 promotion guards, visibility invariant, public-reference URLs, translated UI, and reset no-regrant control.

## Remaining gates and stopping conditions

Local source and synthetic runtime acceptance are complete. The following remain deliberately untested until a separate staging approval:

- real browser rendering and keyboard/touch behavior in authenticated Arabic and English sessions at desktop, 390px, and 360px;
- staging mail transport and receipt of invitation/reset links by a disposable test mailbox;
- logout, email login, reset, resend/revoke, file/photo display/download, and cross-customer denial in the deployed staging runtime;
- deployed migration/preflight, active runtime SHA, and rollback rehearsal.

Stop if staging preflight finds a schema difference, missing HMAC secret, ambiguous existing email/login, missing portal group/right, mail failure, tenant leak, file containment failure, mobile/RTL blocker, or any mismatch with `cpo.catalog-order.v2`. Production remains a later, separate approval after staging acceptance.

Risk after local completion: **medium** until authenticated browser and mail delivery acceptance are proven on staging. The local security and data-integrity risk is reduced by fail-closed qualification, publication, provisioning, and reset controls.

Rollback is code-first: revert the local M068 commit or discard the clean worktree/branch. The local migration is additive; leaving unused columns/tables is the safe rollback. Destructive local schema removal requires a separate exact cleanup approval. No staging or production rollback action exists because neither environment was touched.

## Next exact approval gate

Use this phrase only when ready to begin the next read-only staging gate:

`APPROVE M068 FOR BACKUP-ERP STAGING PREPARATION AND READ-ONLY COMPARE/PREFLIGHT ONLY. NO STAGING APPLY AND NO PRODUCTION ACTION.`
