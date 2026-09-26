# Local authenticated UI acceptance — 2026-09-26

Dolibarr 23.0.3 isolated synthetic runtime; m089_worker, localhost:8102. Composer created task 188 with three files: a 209,715,200-byte TXT, PDF and XLSX. All three appeared on the task card after sequential upload and reload. Arabic mobile 390x844 displayed file cards, download/replacement controls and the 200 MB / 25 files / 500 MB help without clipping. Viewport reset.

A separate localhost:8103 test router returned HTML/502 for asset uploads. Composer saved synthetic task 205, displayed a readable upload failure and retained the link to that saved task; no JSON.parse error or duplicate task submission.

Large-file service test accepted exact 200 MB TXT/PDF and a large Office XML package under a 128 MB PHP memory limit; peak measured 6,291,456 bytes. Over-limit, split escaped PDF active action and Office DTD were rejected. Original 25 asset validation cases pass.

Native membership tests: 17 cases including entity-zero administrator in current-entity group, real assignment persistence and My Tasks visibility, foreign membership, external/inactive/foreign user denial, non-admin entity-zero denial and staff assignment-list denial. No production memberships or rights changed.
