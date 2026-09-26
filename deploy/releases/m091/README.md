# M091 direct production release

Ahmed authorized direct live deployment on 2026-09-26. Source `67597c4e56b7febaaeb77ff8929a6acdba8e920a` adds task scheduling to native Agenda and preserves the existing deployed module descriptor (2.9.0 and its dependencies). No staging action is part of this release.

The production-only helper accepts exactly `production preflight|apply|verify|rollback`. It pins the 15-file artifact and manifest, 13 unchanged dependencies, before hashes, production container/volume/Compose identities, file types/ownership/modes, native version/module readiness and database columns. It checks PHP syntax and native login health. Apply takes a private code backup and full database dump before replacing code and registering only the Agenda hook in entity 1. A failed apply restores code and the prior hook. Explicit rollback refuses later code drift and never restores the database over later business writes. New start-time JSON data remains safe after rollback.

The first preflight refused descriptor drift before any runtime write. Read-only hosted snapshot `8e50955880fafb5d6f8cc8ee2738d0e126e07480c7a5ad20101e4d6b92af059e` showed only the known deployed version/dependency overlay; the final source preserves it. A second preflight refused the existing workspace file owner (1002:1002, 0644); its exact observed metadata is pinned and preserved. Other code targets require owner/group root or www-data and 0644. These are reviewed baseline corrections, not bypasses of failed checks.

- Payload SHA-256: `d129aed9947b69376801c78878d9e7065e00e923b275beeae4bd1f9ab830aebf`
- Manifest SHA-256: `8d4c8ce7f83b6326e2597ffec2a8cc9e2eb7bc5482fdaf420b444a611a833afe`
- Helper SHA-256: `fcd594997e2d768307ed4da43e94c64d84e600f425f3ab3e6d82ce83bc48f495`
- Root-only backup/state: `/root/chinaplanetos-m091-production/`
- Reproduce: `python3 deploy/releases/m091/build.py /private/tmp/m091-artifact`
- Local integrity/recovery tests: `M091_RELEASE_DIR=/private/tmp/m091-artifact python3 deploy/tests/test_m091_release.py`

Upload uses restricted `codex-deploy`. There is no M091 sudo grant; execute the checksum-verified mission helper from the existing authorized server console. No persistent permission expansion or generic deployment helper is required. Runtime outcome and native acceptance belong in dated M091 evidence.
