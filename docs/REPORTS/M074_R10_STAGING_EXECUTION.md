# M074-R10 Staging Execution Record

## Scope and approval

- **Target:** canonical `backup-erp` staging only
- **Approved action:** restricted R10 `compare` only after exact pin verification
- **Not approved or run:** `preflight`, `apply`, `verify`, rollback, Production, DNS, proxy, email, or business-record operations
- **Result:** `COMPARE_FAILED_CLOSED / TABLE_CONTRACT_INVALID / DIAGNOSIS_REQUIRED`

## Pin verification

Immediately before execution, restricted checks confirmed the installed helper SHA-256 `7960d6e19a64855fa3cafa617630471df91401c9f194ae34f590345422066494` and all five remote R10 release inputs matched their frozen SHA-256 pins. Only then was the fixed sudo command `compare` invoked.

## Compare result

Restricted R10 `compare` exited non-zero with:

`M074_STAGING_FAIL=SCHEMA_CHECK_FAILED_TABLE_CONTRACT_INVALID`

The helper reached its read-only schema check after artifact, file and compatibility-layout comparison. The frozen migration contract returns this code when at least one of `cpo_qc_checkpoint`, `cpo_qc_evidence`, or `cpo_qc_publication` already exists but its InnoDB engine, complete column definition, or complete index definition differs from the exact R10 contract. The bounded failure does not disclose which table or structural difference caused the collision.

The controller stopped as designed. `preflight` did not run, no deployment plan was created, and `apply` did not run. Runtime files, database/schema, documents, containers, configuration, customer data, email and business records were not changed. Production was not accessed.

The next safe action is a separately approved, bounded read-only diagnosis limited to the three M074 QC table contracts. R10 must not proceed to `preflight` or `apply` until that diagnosis identifies the exact collision and a reviewed correction path exists.
