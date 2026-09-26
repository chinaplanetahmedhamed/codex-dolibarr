# M094 production release

Includes M093 task controls/identity plus 200 MB internal task uploads and native global-administrator membership repair. Source and baseline SHA are pinned in manifest.json. Exactly 19 module files; no schema, account, native membership, proxy or global PHP changes.

Run build.py with an output directory to reproduce artifacts. Execute only the checksum-reviewed helper in the approved production root console, with exact arguments `production preflight`, then `production apply`, then `production verify`. The helper pins archive/manifest/content hashes, baseline/prerequisites and runtime identity; takes protected code/database backups; verifies actual Apache upload overrides with a temporary loopback-only probe; automatically restores code on failure. Explicit `production rollback` restores code only and preserves business records/files. Later release drift stops rollback.

Authenticated live UI acceptance remains required after runtime checks.
