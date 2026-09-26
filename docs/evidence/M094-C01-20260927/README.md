# M094-C01 - Office metadata attachment rejection

Status: implemented and tested locally; immutable production release prepared. NOT DEPLOYED.

Ahmed reported 7621.xlsx failing with HTTP 422 at tasks/asset.php after requesting 200 MB task attachments. Reproduction against the released validator rejected the standard OPC core-properties relationship as "Embedded Office relationships are not allowed." The metadata relationship URI includes the word package in its namespace. This affects ordinary XLSX and DOCX files even when small. The exact Moaz file has not been received and production log correlation is not yet available; additional file-specific rejection reasons remain possible.

The fix accepts only the exact standard core-properties relationship. Existing checks still reject external links, embedded packages/OLE/templates, macros, unsafe paths, XML DTDs, malformed XML, excessive expansion/compression and oversized uploads. Known validation errors now map to localized English/Arabic/Chinese instructions; arbitrary exception messages are never sent to the user. HTTP status/authorization/CSRF/version handling and all quotas remain unchanged.

Verification: 46 focused metadata/message assertions, 25 existing asset checks, 7 large-file checks (including exact 200 MB acceptance and one byte over rejection), 10 existing task runtime suites, PHP lint and diff check passed. All 17 immutable-release/failure/rollback tests passed. Five legacy source-text/UI-contract suites have identical failures on the untouched baseline, proved using a separate git-archive checkout; these are obsolete version/menu/old limit and source-location expectations, not introduced changes. The focused Office tests exercise the actual validation service, including rejected external core-property links and spoofed metadata URI suffixes.

Runtime and release boundaries: no production code, records, settings, roles or credentials were changed. A read-only log-diagnostic helper was uploaded to the private deployment inbox, but execution could not be verified: native Termius clipboard input timed out, keyboard input made no visible change, and raising the window returned noWindowsAvailable. Restricted SSH remains usable but has no suitable current helper grant for this new scope. Do not bypass that boundary. User has been asked to make the Termius window available. Live preflight, deployment, authenticated upload/download/reload acceptance and the original workbook test remain pending.

Prepared source commit: 1c2a99ebc7c32872d18dbd15ea72511690254399.
Artifact SHA-256: 31572e5f574f1e49907d1fa40bff9ab40d489763e9d84f48148627fc375df72a.
Manifest SHA-256: 13c9eed4ad8d3948ab74a3fe2d117e984ee26d33f823f7ed75c7ff626a262674.
Helper SHA-256: 9eca04d73b736e55257706eb836bf7e644bf5da6269108714cdb9b85445c7185.

Use deploy/releases/m094-c01/build.py to reproduce the artifact. Fixed production preflight must pass before apply. The helper backs up exact code plus the database and automatically restores code on verification failure. Explicit rollback restores only those five code files and preserves task and financial data. Native authenticated acceptance is separate from CLI runtime verification.
