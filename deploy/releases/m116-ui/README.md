# Scoped M116 UI release

Runtime source f6fefab (935881f plus verified print contrast fix). Nine files only, six replacements/three additions. The pinned tar contains only the runtime allowlist and manifest; no fixtures/config/credentials. This record does not authorize any future deployment.

The helper was independently reviewed, its three important findings fixed, and10 local failure/recovery tests pass. Run `python3 deploy/releases/m116-ui/test_release.py` locally: it uses temporary files and mocks; it does not contact the VPS. Helper main actions are production operations and must not be run without new applicable approval and current-state verification.

Actual release applied2026-10-27T12:55:31Z, verified12:56:50Z, final readonly acceptance13:05:37Z. Root state already contains apply-started/applied markers; never repeat apply. Rollback is code only and never restores a database. See completion report for receipts and limits.
