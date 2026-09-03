# M074-R09 Staging Execution Record

## Scope and approval

- **Target:** canonical `backup-erp` staging only
- **Approved actions:** exact R09 upload, remote checksum verification, helper/sudoers installation through the approved root session, and restricted `compare` only
- **Not approved or run:** `preflight`, `apply`, `verify`, rollback, Production, DNS, proxy, email, or business-record operations
- **Result:** `COMPARE_FAILED_CLOSED / R09_RETRY_FORBIDDEN / R10_PREPARATION_REQUIRED`

## Immutable inputs and installation

The five R09 release inputs and three controller inputs were uploaded to the two private `codex-deploy` inboxes as regular single-link files owned by `codex-deploy:codex-deploy` with mode `0644`. Remote SHA-256 verification matched the frozen R09 pins. The root installer then reported:

- helper SHA-256 `4a44d556e57212bccce7b1f950565e0b5a23cd71592a3f30c512ca3b0eb7d912`;
- sudoers SHA-256 `3588366c71dfe7546c7ae58ff066a5b26e47d55645098d82034d628f149d4d99`;
- exact commands `compare,preflight,apply,verify,rollback,status`;
- `DEPLOY_EXECUTED=NO`, `DATABASE_CHANGED=NO`, and `PRODUCTION_ACCESSED=NO`.

Independent restricted verification confirmed the installed helper hash, `root:root` ownership, mode `0755`, and all six exact sudo commands.

## Compare result and correction boundary

Restricted R09 `compare` exited non-zero with:

`M074_STAGING_FAIL=SCHEMA_CHECK_FAILED_SCHEMA_DIAGNOSTIC_INVALID`

The file and compatibility-layout comparison ran before the schema gate, but the controller intentionally refused to classify a multi-line or otherwise non-contract stderr response. R08 had ignored schema stderr; R09 correctly stopped instead of exposing or accepting it. No preflight plan was created and no apply operation ran.

R10 may invoke the same candidate schema runner with PHP runtime display, logging, and warning channels disabled at process start. The runner's own single ASCII `M074_SCHEMA=FAIL REASON=<ALLOWLISTED_CODE>` line remains the only accepted stderr response; a non-zero exit or unexpected stdout/stderr remains fail closed. This correction changes no QC business rule, schema definition, permission definition, customer visibility, or runtime target.

R09 must not be retried. R10 requires fresh release/controller pins, renewed independent Security and Operations/QA PASS verdicts, and a new exact staging approval before upload or installation. Production, DNS, proxy, email, customer data, documents, and real business records were not accessed or changed.
