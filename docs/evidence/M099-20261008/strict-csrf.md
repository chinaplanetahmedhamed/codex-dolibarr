# M099 strict CSRF correction — 2026-10-07 UTC

Fresh authenticated staging evidence: Dolibarr24.0.1 security diagnostics `/admin/system/security.php` reports MAIN_SECURITY_CSRF_WITH_TOKEN=3. Existing synthetic card task117 opens but context and Attachment show Unable to load. No staging security setting, permissions or business records changed. Earlier direct ERR_BLOCKED_BY_CLIENT navigation is insufficient to explain the actual card fetch failure.

Cause established by source and reproduction: card_operation.js sends the session token only for POST. Dolibarr's native level3 guard requires a token for GET action=context/options/versions/download, emitting403 non-JSON before the endpoint executes. The card JSON parser maps this to Unable to load. Isolated synthetic runtime reproduced403/native CSRF rejection without a token and200 JSON with the valid token.

Fix: send the current card token on GET and POST; add it to same-origin document download/preview/history links and images. Reject foreign document URLs before token attachment. Server CSRF, native rights and ownership enforcement remain enabled. No PHP, schema, permissions or security configuration changes. Tokens are never included in diagnostics/reports.

Fresh checks: client regression15 assertions PASS; JS syntax and git diff check PASS; shared-document72, MyWork16, authenticated HTTP82 assertions PASS. HTTP acceptance ran with strict CSRF3 in the isolated synthetic runtime; missing-token POST remains denied. Rendered local linked card has0load alerts, canonical Shipment documents tab loaded, and Attachment opens with Shipment document selected and Private card attachment available. Synthetic CSRF value restored after tests. Local core23.0.3; staging24.0.1 acceptance still required.

Deployment status: correction source prepared; staging application not yet changed. Root execution blocked by supported Termius controls, and Portainer has no authenticated browser session. A one-file checksum-pinned staging-only correction helper is being prepared with exact before/dependency hashes, scoped code backup and code-only rollback. No production promotion until staging card/document/role acceptance passes.
