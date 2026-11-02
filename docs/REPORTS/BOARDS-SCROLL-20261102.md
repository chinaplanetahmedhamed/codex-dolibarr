# Boards natural scrolling and visible menus

Status: implemented and verified locally; not deployed. Isolated branch `codex/boards-natural-scroll`, based on sound release `c2ad887`. This change excludes the pending task hub (`1bad0cc`) and Office Expenses work.

## Problem and change

The live board constrained the canvas and lists to the remaining viewport height. At small sizes, native ERP navigation and Boards controls left a cramped scrolling list. Embedded task details also had both a fixed-height scrolling iframe and an outer scrolling dialog. Menus chose a direction using a fixed 160px threshold instead of their natural height.

The board now uses normal document vertical scrolling; long columns grow with their cards. Horizontal lane scrolling remains. Cards support ordinary panning, while dedicated Move handles retain drag ownership and the existing keyboard alternative. Opening a task keeps the existing same-origin iframe and authorization boundary, sizes it to its natural content, and gives its outer dialog one scrolling surface with a sticky close control. Closing restores the board position and replacement card focus after refresh.

A shared request-free geometry helper measures the actual menu and visible viewport. Menus flip upward when appropriate. Embedded native child dialogs, shipment/attachment menus and CKEditor dialogs use the visible part of the outer card instead of centering against a many-thousand-pixel iframe. CKEditor core/library files were not changed.

Runtime files (seven modified, one new):

- `custom/chinaplanetoperations/tasks/boards.php`
- `custom/chinaplanetoperations/tasks/card.php`
- `custom/chinaplanetoperations/js/boards.js`
- `custom/chinaplanetoperations/js/card_board.js`
- `custom/chinaplanetoperations/js/card_operation.js`
- `custom/chinaplanetoperations/css/boards.css`
- `custom/chinaplanetoperations/css/card_board.css`
- `custom/chinaplanetoperations/js/boards_geometry.js` (new)

No schema, financial logic, permissions, account, grants or business-record changes. Existing sound, polling and task mutations remain in place. No production writes occurred.

## Verification

Public assets fetched read-only from live matched all eight inspected baseline JS/CSS pins, including callers, sound and polling runtime (`../evidence/live-assets-compare.json` outside the repository). Live PHP source has not been re-reconciled for a release.

All 20 final command checks exited zero: new geometry/frame/integration tests; existing sound controller and integration; live-sync and pointer regressions; rich-text regression; full M116 suite; changed JavaScript and PHP syntax; `git diff --check`. Detailed commands and logs are in the sibling `evidence/final-checks.json` and `final-check-*.log` files. Geometry, frame sizing and integration tests first failed before their fixes. Independent read-only review identified the CKEditor div-dialog placement gap; it was reproduced and corrected before final checks.

Rendered acceptance used an overlay on the existing isolated Dolibarr 24.0.2/PHP 8.3.21 runtime and a separate synthetic HTTP fixture. Shared native source/configuration was not edited. Existing authenticated test access was reused without credential or rights changes. No task/customer/finance form was saved. Tests covered:

- Native Arabic 390x844 task: frame grew from 742px to 1184px when expanding the existing editor, then shrank after cancellation; the outer card scrolled, Add menu stayed usable, Dates remained inside the visible clip, and Escape closed only the child dialog.
- Synthetic 55-card Arabic board at 820x819 and 390x844: vertical wheel scrolled the document (819px/844px) with lane/list scrollTop zero. Horizontal scrolling remained within the lanes; page horizontal offset stayed zero.
- A 417px menu with about 208px below its trigger opened fully above it; natural menu height, not the previous 160px heuristic, determined placement.
- Closing a card after board refresh restored page Y182 to Y182 and focused its replacement title.
- A very long synthetic card (approximately 8880px desktop) kept its CKEditor Link dialog visible, including its three tabs, fields, Cancel/OK and close control on a 390px phone viewport. No editor submission was made.
- Existing synthetic pointer and keyboard tests passed. Physical touchscreen testing was unavailable; mouse simulation and viewport tests are not physical-touch certification.

Screenshots, all synthetic, in sibling `evidence/`:

- `full-menu-above-ar-820.jpg`
- `natural-board-mobile-scrolled.jpg`
- `long-card-editor-mobile.jpg`

Impeccable context and Operate/craft guidance informed the refinement. Final static detection reported two inherited warnings: the existing Arial family and an existing description blockquote border. These were outside the scrolling fix and retained; no new detector findings were reported. Dolibarr, systematic-debugging, TDD and verification guidance informed implementation and checks.

## Limits and release boundary

Last observed live runtime is Dolibarr 24.0.1/PHP 8.2.7; native rendered acceptance was 24.0.2/PHP 8.3.21. PHP syntax was additionally checked with installed PHP 8.2.0. This is not matching-version production acceptance. Browser automation may center a tall iframe when targeting child-frame locators; long-card acceptance therefore used screenshots, normal coordinate input and parent DOM geometry rather than mistaking that harness behavior for product scrolling.

Before any live release, obtain the separately required scoped authorization, reconcile exact current PHP/source changes and other writers, back up the seven replaced files and record the new helper's prior absence. Deploy only the eight runtime files, verify helper loading and desktop/mobile behavior on the intended live target, preserving customer data and unrelated owner changes. Code-only rollback restores the exact seven saved files and removes the new helper only if it was absent before this release. No database restore or migration is needed.
