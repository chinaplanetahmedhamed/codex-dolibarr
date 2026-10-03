# Website quantity intake compatibility: ERP release accepted

Ahmed authorized the bounded ERP correction and full push. Website staging source is `17590abffcc977de769af523c503d9c33e222cea`. Synthetic enquiry `WEB-20261003-214731-7E6924`, record38, requested two machines with unknown packing and was initially misrepresented as one carton.

## Exact source reconciliation

Deployed ERP release source: `6e35e6f9224b621660a72178b365a8875314a915`. Read-only inspection and narrowly scoped export verified all ten existing source files before changes. Staging PHP/page match older `310deea` bytes; production PHP/page match deployed M085 `7d0ca969a164867ff11fb0e8c034ffff02bfcf5b`. Production language bytes match Git `1c2a99ebc7c32872d18dbd15ea72511690254399`; staging has existing language overlays. Separate five-file candidates preserve each environment's existing features and every translation byte, adding only four unique labels. Staging was not upgraded wholesale to M085. Both candidates and their actual before hashes are committed under `deploy/releases/web-quantity-20261004`.

Modern piece/machine/set requests retain selling quantities; unknown packing creates no carton count, volume or weight. Confirmed modern carton requests and legacy numeric cartons retain multiplication. Server and UI continue to require verified packing and positive cartons for quotation creation. Saved privacy acknowledgement and notice version display independently of freight pricing evidence. No schema, price, permission, stock, order or real customer changes.

## Verification and deployment

Both PHP files linted in each candidate. Shared pure normalization regression passed against both variants. Seven release guard tests passed, including post-apply failure restoring the exact backup. Helpers require pinned image IDs, custom volumes/mounts, unchanged port bindings, PHP8.2, and all five before/after hashes. They contain no database queries/writes, module migrations, persistent helper/sudo installation, or container recreation.

Canonical staging preflight/apply/independent verify: PASS. Source `6e35e6f9224b621660a72178b365a8875314a915`. Helper SHA256 `ea4e4abe813e0ab2f7d2662aadf52937f70ddd4c4ed84c9b64f4de24010f0375`; frozen administrative copy `/root/cp-quantity-stage.cKLYwG/release.py`; private backup `/root/chinaplanetos-web-quantity-staging/before.json`.

Authenticated staging administrator request38 showed **2 machines**, packing required, disabled quotation creation, consent Yes, scope `inquiry_follow_up`, notice `2026-10-04`. Reload preserved the display. Clearly marked synthetic QA status was saved as closed and remained closed after reload. Earlier identical request retry produced the same reference and one ERP record. No quotation or outbound message was created. Browser screenshot: `/tmp/chinaplanet-staging-quantity-accepted.png` (local, not committed).

Production preflight/apply/independent verify: PASS, same committed release and intact staging candidate hashes required. Helper SHA256 `29ff0adfa6695ab382ebc2c0e6d9f8a7cb32e972693d0dcec5813ef08876e665`; frozen administrative copy `/root/cp-quantity-prod.bFwk5f/release.py`; private backup `/root/chinaplanetos-web-quantity-production/before.json`. Pure PHP normalization, installed target checksums and login health passed. Authenticated production staff-browser verification remains pending because that browser requires sign-in; this limitation was not represented as completed native acceptance.

Website production workflow `37160452141` was dispatched for the exact accepted website SHA `17590abffcc977de769af523c503d9c33e222cea`; website deployment/browser verification is tracked by the parent release report and was still in progress when this ERP evidence was recorded.

## Recovery

Frozen helper files and manifests are committed under `deploy/releases/web-quantity-20261004/tooling`. Run the matching retained root script with `rollback` only within authorized recovery scope. Rollback validates current hashes, restores only the five backed-up code files with original owner/mode, and preserves later enquiries/business writes. If the website must also be rolled back, restore the website first, then the ERP receiver. Never restore an older database over current records.
