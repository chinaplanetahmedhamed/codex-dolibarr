# Boards release 2026-10-28

Ahmed explicitly requested publication after reviewing the existing general-board visual design and local customer/project trial. Four runtime files only: boards.php, boards.css, boards.js, new boards_live_runtime.js. No core/schema/rights changes or fixture data. Existing M116 UI f6fefab is preserved.

Selected boards poll the existing authorized API every 2 seconds after the last request completes, hold updates during drafts/scroll/drag, back off offline and clear cached content on access loss. This is polling, not push; galleries and other dashboards are outside this slice. Dedicated move handles preserve body scrolling and the existing keyboard Move dialog. Shared incumbent gallery/board renderers remain in place; amount/currency fragments on cards are isolated for Arabic RTL without arithmetic changes.

Prior native independent-session move/save/reopen acceptance measured 2219ms from Save to the observer and preserved task/native/financial fingerprints. New release-name controller/pointer tests and native browser acceptance must pass before apply. Physical touch and office-scale load remain limitations, not asserted acceptance.

Production preflight: correct srv1011043/root; runtime a714a9c; all 11 live UI/statement hashes matched the last approved release. Initial project-label assumption rejected; reconciled with the pinned m107 topology contract allowing the documented empty ERP label while requiring exact DB project, image and custom volume. No production files changed by this preflight.

Release requires a pinned artifact, fresh backup, shared release lock, before-hash checks and code-only rollback retaining native and financial writes. Final receipts will be added after execution; no live deployment claimed by this source commit.
