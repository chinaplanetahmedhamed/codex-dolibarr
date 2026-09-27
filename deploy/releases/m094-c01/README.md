# M094-C01 Office upload correction

Five-file immutable code release over M095, source 1c2a99ebc7c32872d18dbd15ea72511690254399. Accept the exact standard OPC core-properties relationship and show allowlisted localized upload errors. No task/data/schema/rights/size-limit changes. Prior M094 direct-live authorization covers this correction.

Production deployed on 2026-09-27; preflight, apply and verify passed. Recorded results and remaining browser acceptance limits: `docs/evidence/M094-C01-20260927/README.md`.

Preflight must verify live baseline and prerequisite hashes; do not bypass drift. The helper backs up code and database, deploys exact files, checks runtime Office metadata acceptance/external-link rejection and inherited 200M upload settings, and automatically rolls code back on failure. Explicit rollback restores code only and preserves subsequent business records. Live authenticated acceptance and the original Moaz workbook remain pending.
