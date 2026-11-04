# China Planet Documents — local 0.3.1 increment

Read-only native Dolibarr staff workspace that opens the existing editor. The editor and its eight templates stay in the separate `shipping-doc-generator-main` source. No renderer/template fork, database, migration, posting or publication action is added.

Native entry: `/custom/chinaplanetdocuments/index.php`, optionally `?socid=7&projectid=9&shipmentid=3&returnto=shipment`. Internal identity, module read permission (or internal admin), native customer access, project read membership, active entity and matching project/customer are required. Shipment selection additionally requires Logistics read permission, matching entity/customer and a matching validated project (when linked). A shipment-only entry derives and validates its existing customer/project; no record is relinked. Three staged GET selectors reuse native customer selection and project authorization; read-only projections list project titles and shipment container/vessel names before references. Changing customer resets project/shipment, and changing project resets shipment. Lists are bounded to 100 choices; already-selected older records remain available. Query failure shows an explicit generic error. Private-project shipments are excluded from customer-wide choices. Native module enablement and editor authentication are separate; this increment performs neither.

Configure `CHINAPLANETDOCUMENTS_EDITOR_URL` through existing administrator constants only after verifying the intended editor/ERP target isolation. It defaults to empty; the launch stays disabled. HTTPS or HTTP loopback URLs are accepted; userinfo, query strings, fragments, whitespace and backslashes are rejected. No credential, customer/project ID or document contents are sent in the link. The existing editor opens in its own tab with its existing design and sign-in. Context is displayed in ERP with native return links, **not automatically loaded into an editor draft**. This is an entry-module increment, not completed embedded editing/SSO/save-back.

Reserved Documents module ID is 500700; permission is 500701/workspace_read, default off. Office retains 500600 and 500601–500604. A local source inventory covered 1,166 descriptors; runtime definitions and grants remain unverified. The read-only lifecycle guard blocks legacy Documents definitions, a foreign new-ID definition, orphan user/group grants at the new ID, or failed inventory queries before native activation/removal. No legacy grant is copied/deleted/remapped. Review any legacy 500601 provenance manually; numeric grants cannot establish whether Office or Documents was intended. Use native role assignment for the new explicit permission only after approved reconciliation and scoped backup/recovery checks. Designed for Dolibarr 24 / PHP 8.1+; no runtime install performed.

Run local checks from this worktree:

```sh
php deploy/tests/test_m121_documents_module_ids.php
php deploy/tests/test_m121_documents_workspace.php
python3 deploy/tests/test_m121_documents_entry.py
php deploy/tests/test_m121_documents_context.php
node --test deploy/tests/test_m121_documents_adapter.mjs
```

The shared-view fixture can generate synthetic HTML through PHP CLI without a server:

```sh
php deploy/tests/fixtures/m121_documents/index.php > /tmp/m121-documents-en.html
php -r '$_GET["lang"]="ar"; include "deploy/tests/fixtures/m121_documents/index.php";' > /tmp/m121-documents-ar.html
```

Safe return uses only `returnto=workspace|customer|project|shipment`. URLs, paths and unknown targets are rejected. A return link is constructed from validated record IDs. Missing target context falls back to the Documents workspace with its remaining validated selection. No arbitrary `backtopage` or task link is accepted; shared card adapter wiring needs parent coordination. The existing editor supports its own customer/shipment selection but has no observed URL-based project/return intake, so no unsupported handoff parameters are sent.

## Optional same-origin adapter (default off)

The source now includes portable `js/editor-context.mjs` sender/intake functions and `js/launch-context.mjs` click integration. The paired, uninstalled Docs candidate contains a staff-authenticated receiver and controlled selection hook, both default off. No current cross-origin Docs service can receive context through this adapter. Before enabling `CHINAPLANETDOCUMENTS_CONTEXT_ADAPTER=1`, the separately reviewed editor candidate must be integrated on the same origin, staff identity/session isolation verified, and native server-side selection revalidation accepted. This task performed no deployment/configuration change.

After explicit enablement, an ordinary primary click on a same-origin editor stores one selection envelope in the current tab's sessionStorage and navigates that tab with a random UUID fragment. No customer/project/shipment identifier is placed in the editor URL. Different scheme/host/port, credentials, query strings, fragments, invalid data or storage failure keep the original noopener/noreferrer link without handoff. Modified clicks retain the original link and carry no context.

The receiver consumes once after the caller confirms staff authentication, enforces origin and a two-minute logical TTL, removes storage on consumption, strips the pointer from history and returns selection hints plus a canonical ERP return path. Expired storage is rejected/removed on intake; without a receiver, bytes can remain until overwritten or tab/session closure. This is not a timed background deletion mechanism. Hints confer no access and never select a Docs draft by an ERP shipment ID. The paired candidate supplies the controlled selection hook and native server-side revalidation; authenticated target acceptance is still required; no draft, upload or publication is automatically performed.

Current status: all source remains an uninstalled, uncommitted candidate. The synthetic entry-page harness passes 26 route cases and rejects a project-permission bypass mutation; 29 service/view, 38 contextual SQL/navigation/payload checks and 33 Node adapter tests pass. Read-only projections were executed against synthetic in-memory SQLite fixtures with a nonstandard table prefix; total_changes stayed unchanged. The paired Docs candidate passes local receiver/selection tests; native authenticated-runtime and browser acceptance remain pending.

The loopback demo server was attempted once and denied by the sandbox while listening on `127.0.0.1:18776` (`Operation not permitted`); no alternate binding or retry was attempted. CLI-generated 0.2 context previews are in `docs/evidence/m121-documents-local/workspace-context-en.html` and `workspace-context-ar.html`; earlier 0.1 previews are retained. Browser inspection of the Arabic file was denied because the browser allows only HTTP/HTTPS and prohibits `file:` URLs. No alternate browser surface or preview workaround was attempted. Desktop/phone visual acceptance remains pending; the artifacts are not screenshots or native ERP acceptance.
