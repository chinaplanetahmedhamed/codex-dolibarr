# codex-dolibarr

Private source-control repository for the Vision Trading Dolibarr 23.0.3 production customizations.

## Repository policy

- Track sanitized custom-module source and deployment manifests only.
- Production snapshots must exclude databases, documents, configuration files, credentials, keys, tokens, logs, sessions, caches, backups, and demo data.
- Never deploy automatically from `main`.
- Every release requires a verified allowlist, immutable source commit/tag, read-only production preflight, encrypted off-repository backup, explicit diff, rollback plan, and Ahmed's final deployment approval.
- The initial production baseline must be captured read-only and reviewed before local module changes are layered on top.

## Runtime target

- Dolibarr: 23.0.3
- Production PHP reported: 8.2.7
- Production database reported: MariaDB 10.6

Version metadata and production custom-module checksums must be recorded in a sanitized deployment manifest before the first code import.
