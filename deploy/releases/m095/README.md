# M095 production staff dashboards

Immutable 11-file artifact from tested source, baseline M094. No schema, credential, grant or business-record mutation. Preserve task metadata and per-user preferences. Scope: employee dashboard presentation and All Tasks list only.

Build with `python3 deploy/releases/m095/build.py /private/tmp/m095-artifact`. Check helper hash before executing exact production preflight/apply/verify in the existing authorized root console. Root-only code/database backups; automatic rollback on failed acceptance probe. Explicit rollback restores code only and preserves later task/financial writes. Native authenticated UI acceptance remains separate.
