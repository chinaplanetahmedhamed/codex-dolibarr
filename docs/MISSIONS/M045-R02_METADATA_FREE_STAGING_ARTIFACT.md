> **Governance supersession — 2026-11-03:** Legacy agent assignments and delegation in this record are inactive under [the Official AI Governance](https://github.com/chinaplanetahmedhamed/codex-dolibarr/blob/main/docs/governance/AI_GOVERNANCE_2026-11-03.md). Names and dated reviews below are retained as historical attribution only. They grant no current authority or reusable deployment approval. Work must record a new assignment before resumption. Technical/security/data/backup safeguards remain in force.

# M045-R02 — metadata-free staging artifact correction

## Mission control

- **Mission ID:** M045-R02
- **Type:** STAGING_RELEASE_CORRECTION
- **Status:** APPROVED
- **Owner:** Agent 007
- **Approved by:** Ahmed Hamed — staging deployment approval remains limited to `backup-erp`
- **Approval date:** 2026-08-07
- **Production:** forbidden

## Trigger

GitHub run `31192104807` stopped in artifact verification before SSH upload or apply because the macOS archive carried `LIBARCHIVE.xattr.com.apple.provenance` headers. Restricted staging status remained `NOT_DEPLOYED`.

## Authorized correction

1. Repackage the unchanged 13-file M045 artifact with GNU tar and no macOS extended attributes.
2. Replace only the `m045-8d919fd.tar.gz` prerelease asset and its pinned SHA-256.
3. Update and reinstall the M045-only helper so it accepts only the corrected checksum.
4. Re-run the existing M045-R01 GitHub staging flow.

## Evidence and acceptance

- Runtime commit remains `8d919fd9f2164bca6cfd859e89df338e985c486a`.
- Corrected artifact SHA-256: `2503f666d86c9422207295e4c21fa000bd725cd7060eb79ec86881652f6846cd`.
- Archive contains exactly 13 regular files and no `LIBARCHIVE`, `SCHILY.xattr`, or `com.apple` metadata strings.
- No production or business-record action is authorized.
