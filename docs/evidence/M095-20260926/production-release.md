# M095 production release — 2026-09-26

Status: DEPLOYED, native live UI acceptance pending.

User's existing direct-live authorization reused for staff dashboards and all-task list. Runtime source b2cfa568e743e0ec98783238b64df82a76753982; delivery 3f23bd9. Artifact 11 files, no schema, grants, credentials or business-record migration.

- Payload 08810b0d3f45949e1be63fb7f0deed1521e5f7259375439e27b61dc582aa9885
- Manifest d18e8b1696109db094e3a9ba551eab906d47980c55c3850b2aa8f5b3df14b1dd
- Helper 86ef5761dafcc2dc09dff11df71be0c464be1937aa7b803eebde9e8131bb1ece
- Root recovery /root/chinaplanetos-m095-production/
- Code backup b6388c8e388fe953144e3a1cab703cbf18e02debcad3f340ac2c5aa2470ee90e
- Database backup 9ab3d03cd3ba8dd083f8bf87c63780d28c59be077981f828355e1097040b41c0

Pinned preflight, apply and independent verify returned PASS / DEPLOYED, read back independently through restricted SSH. Every deployed file and unchanged prerequisite matched. Native administrator service sees Salma's same column definitions and task order as her own authenticated-user service; staff authorization denies manager dashboard. All Tasks has no columns. M093 controls, M094 native team members and Apache upload200M/post201M still pass.

Local regression: all 12 suites passed, including 18 M095 permission/layout assertions; PHP/JS syntax and diff check passed. Release integrity and failure/rollback suite: 17 tests passed. Local authenticated administrator/worker UI verifies list, employee layout, independent own layout, filters, persistence, direct URL denial, Arabic/English and desktop/390px phone.

Native Chrome production inspection reached Salma's existing board, but the active window returned to a different user window during reload. Stopped repeated control attempts and asked for stable ERP window or user acceptance. No real task, file, user preference, native membership or right was modified for release testing. Live 200 MB upload and final production UI workflow remain unverified. Do not mark fully accepted based only on deployment/service probes.

Rollback: exact current helper, action production rollback, verifies current hashes and restores the scoped code backup only. Never restore database over later user writes. Older M094 verifier should refuse expected M095 drift.
