# M091 production release — 2026-09-26

Final outcome: DEPLOYED AND VERIFIED. Source `6e5dd5604d089cbb6e7390e684bf2bd604a55ab3`. Authenticated staff month/day/calendar-to-card acceptance passed after correcting the production collation and clock differences below. No business records were modified for acceptance.

Ahmed authorized direct production: “ok . push to live , live directly”, later reaffirmed “ok go on”. No staging actions occurred.

Production-only pinned helper executed through the existing authorized Termius server console; uploads/checksums used restricted codex-deploy SSH. No access grants were installed. Base source: `67597c4e56b7febaaeb77ff8929a6acdba8e920a`; final source after the two corrections below: `6e5dd5604d089cbb6e7390e684bf2bd604a55ab3`. Fifteen custom module files and one hook context were deployed; no core, schema, business-record, credential, customer or financial changes were part of deployment.

Pins: payload `d129aed9947b69376801c78878d9e7065e00e923b275beeae4bd1f9ab830aebf`; manifest `8d4c8ce7f83b6326e2597ffec2a8cc9e2eb7bc5482fdaf420b444a611a833afe`; helper `fcd594997e2d768307ed4da43e94c64d84e600f425f3ab3e6d82ce83bc48f495`.

The first two preflights stopped before writes on the descriptor overlay and workspace ownership. Read-only comparison preserved production's 2.9.0 descriptor/dependency list; the existing workspace owner 1002:1002/0644 is pinned. All other target bytes and all 13 unchanged dependency pins passed. Final preflight: PASS, files 15. PHP/runtime/module/schema prerequisites and local native login HTTP health passed.

Console output independently observed after apply and after separate verify:

```json
{"environment":"production","action":"apply","result":"PASS","status":"DEPLOYED","code_files":15,"code_backup":"5c50563fdec74bd8cac3c199a88f614b1417c3dcdd138cebefffe361f36d1234","database_backup":"776eb64f77adaa25068cdab3dcebad3ebb4d195f0debc46eda61c42eb85a9797","authenticated_acceptance":"PENDING"}
{"environment":"production","action":"verify","result":"PASS","status":"DEPLOYED","code_files":15,"code_backup":"5c50563fdec74bd8cac3c199a88f614b1417c3dcdd138cebefffe361f36d1234","database_backup":"776eb64f77adaa25068cdab3dcebad3ebb4d195f0debc46eda61c42eb85a9797","authenticated_acceptance":"PENDING"}
```

Backup/state is private `/root/chinaplanetos-m091-production/`. Do not export or restore the full database over newer writes. Rollback order is the pinned C02 helper, C01 helper, then base production helper. This restores code and the prior hook without database restoration. No rollback was required.

Computer control temporarily returned stale terminal screenshots after apply. Refreshing the native window revealed successful apply and verify. A duplicated malformed command was rejected by exact argument validation before another action. The base state was DEPLOYED; acceptance continued with the existing authenticated Salma session and the following bounded corrections.


## Corrections discovered by live acceptance

The first authenticated calendar load showed the safe task-calendar warning. Read-only diagnostic execution as the existing staff user identified MariaDB's `Illegal mix of collations ... for operation nullif`. C01 casts the ASCII date expression to binary before the empty-date comparison. The initial local runtime was MySQL 5.7; its passing tests did not establish MariaDB compatibility. C01 candidate execution on production succeeded before writing, finding one authorized scheduled task. Preflight/apply passed; the native month view then displayed the task.

The native display then revealed an eight-hour shift compared with the task card. Task forms store datetime-local strings; treating those as database UTC dates shifted the Agenda labels and final day. C02 interprets those existing wall-clock values using the native user timezone when projecting calendar timestamps, preserving the stored data. Local tests now cover UTC server plus Shanghai/Los Angeles users, and both collation variants (47 integration assertions passed). The C02 production candidate and installed service also compare each projected endpoint to its task's unchanged values inside a read-only transaction. Preflight, apply and independent verify all passed.

| Correction | Source | Service SHA-256 | Helper SHA-256 |
|---|---|---|---|
| C01 collation | db746106ef5fbe6a62db61ccac70064390fa3fd7 | 859e45babae5ba010acb6fb3bf94938f0a551ef37bc82a75368f602c3f15406e | 508812fcf78f9f1c434d8655a11ade314eff975751b30aef73bc732e25134b83 |
| C02 clock | 6e5dd5604d089cbb6e7390e684bf2bd604a55ab3 | 9f67788044be4e5c5567f60a630c4062e97bbf1a4bbc7ff69d17b19a91f615f5 | ab26f6c84befbf8cdefc7d8b86feee6fdfc9943662263610a5d5ebc4a6f65396 |

The final fifteen-file inventory is `deploy/releases/m091/final-manifest.json`, SHA-256 `b172c402d35783eec8493bcdffc7e7adcd795c4a9d14375de514c1e6267e29c1`. C02 verify checks all fifteen final files and thirteen unchanged dependencies, plus the native login health and actual staff calendar query. Both correction helpers save private one-file backups under `c01/` and `c02/` inside the original private state directory; they do not write task/database records.

Independent final result retrieved over restricted SSH:

```json
{"result":"PASS","scheduled_tasks":1,"release":"M091-C02","environment":"production","action":"verify","code_files":1}
```

## Authenticated production acceptance

The existing staff session (user 11, Salma) opened the new My day Agenda shortcut and the normal top-navigation Agenda link. The month calendar displayed existing task 62 on its scheduled days, September 26–27, with To do status. Its end is September 27 at 19:14, matching the task card exactly; it no longer extends into September 28. Opening September 27 showed the same task/time range in native day view. Navigating from the calendar task opened the authorized task card, whose new Start time field and Agenda explanation were present. Returning to Agenda loaded the same persisted schedule.

No test tasks, customer records, comments or status changes were written to production. Create/edit/status/reassignment persistence, negative permission cases, week/per-user views and Arabic/English phone layouts were exercised with synthetic local records as documented in the local evidence; those write flows were not repeated against business records. Live acceptance used the existing record and authenticated desktop UI.
