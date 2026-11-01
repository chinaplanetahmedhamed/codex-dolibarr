# M119 — Mobile money readability

Outcome: the original amount and ISO currency remain readable in the Finens register at the verified phone sizes, including both horizontal-scroll ends. This resolves the previous checkpoint's clipped-money limitation. Work is local and synthetic only.

## Changes

- Preserve the original Finens shell, colors, table, desktop column order and quick-entry pattern. On phones, freeze the money column at the logical trailing edge. The complete decimal stays on one line; the ISO code can move to a second line.
- Add localized earlier/later-column buttons, a scroll hint and disabled end states. RTL and LTR use the corresponding logical scroll direction. Remaining non-money columns retain horizontal scrolling.
- Constrain both sticky edges from measured viewport/column widths. The initial one-edge approach passed normal phones but failed at the far end of a320px table; both-edge positioning fixes that case. A2px scroll-position tolerance handles fractional pixel rounding at browser zoom, not monetary calculations.
- Keep large summary totals as exact strings with a smaller mobile type size when needed; never split their numerals or combine currencies. Empty registers show a readable empty message without pointless horizontal controls.
- Version local CSS/JS URLs by content hash. Browser inspection reproduced an old cached stylesheet/script despite refreshed HTML; the hashed URLs load the actual correction without changing CSP or asset access rules.
- Add an explicit `--mobile-boundaries` option to the synthetic preview generator. It creates only in-memory drafts and static outputs, including maximum CNY, large USD, EGP0.01, mixed currencies, long descriptions and empty states. These are illustrative values/rates, not real office data.

Money/domain/store/native-writer files were hash-checked against the starting checkpoint and are unchanged. JavaScript numbers are used only for scroll geometry, not financial calculations. No native factory, financial posting, permissions, security or network settings were changed. Neither existing Colima profile was started; both were observed stopped. The loopback-only static preview on8819 and existing synthetic employee fixture on8849 were resumed for verification, serving only synthetic state and the local interface. No real ERP configuration was loaded.

## Evidence and checks

The before measurement at433 CSSpx showed both CNY/EGP originals outside the complete visible table bounds. Final browser checks use actual text ranges, including each numeric substring, rather than merely testing the cell rectangle.

| Check | Result |
| --- | --- |
| Arabic/English at320,375,433 CSSpx | Six cases pass at initial position, far end and return. Full original text fits; numerals stay on one line; no horizontal page overflow. |
| Maximum `999999999999.99 CNY`, large `9876543210.12 USD`, minimum `0.01 EGP` | Complete value/code preserved. Currency may wrap separately; no ellipsis, rounding or scientific notation. |
| Mixed-currency summaries | Separate exact totals; no split numerals or hidden overflow. |
| Expanded quote plus keyboard Enter on scroll control | Both languages pass at320px; original remains visible alongside disclosed estimate/provenance. |
| Empty state | Both languages pass at320px; scroll tools hidden and no page overflow. |
| Resize433→320→433 without reload | All three measurements pass. |
| Desktop1600px | Sidebar remains256px; amount cell stays in normal table flow; mobile tools hidden; no page overflow. |
| Existing synthetic employee session under unchanged CSP | At320px both scroll ends preserve the saved82.35/700.10 EGP drafts and full money visibility. GET/navigation/scroll only; no draft submission. This is still fixture authentication, not native login acceptance. |

Artifacts outside source: `/Users/mac/Documents/Codex/2026-11-01/task/m119-preview/mobile-evidence/`. `checks.json`, `extra-checks.json` and `resize-checks.json` contain measured results. `mobile-usability.html` in the preview root displays the final screenshots; earlier visual-restoration screenshots remain preserved as historical evidence.

Independent code review found no material issue. Independent visual review confirmed full maximum decimals/currency codes at all three sizes and no blocking money defect. A narrow clipped sliver of another column can remain at the frozen boundary; users scroll the other columns with the hint/buttons. This is a remaining table-view limitation, not clipped money.

Regression result: **69 scenarios pass**:16 domain/store +15 employee controller/rendering +13 native drafts +8 native identity +5 native capabilities +3 native provenance +9 synthetic HTTP. The new rendering scenario checks exact maximum originals, separate per-currency totals and explicit quote evidence in both languages.20 scoped PHP syntax checks, JS syntax and diff whitespace checks pass.

Full native `main.inc.php` HTTP acceptance remains unrun: no authorized version/engine-matched runtime is available. The historical release/PHP/MariaDB receipts are still not fresh parity evidence. Foreign-currency native persistence, tax/category mappings, payments/advances accounting and reconciliation retain their existing gates. No commit, push or deployment occurred.
