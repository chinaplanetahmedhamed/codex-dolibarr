# M140 workflow acceptance record

Owner Ahmed Hamed; Codex execution, 2026-11-22 Asia/Shanghai. Scope: existing Operations Boards, Customer Center projections, Logistics context and Office native views. Production is read-only reference.

| Environment and role | Steps | Expected and observed | Persistence/failure boundary |
| --- | --- | --- | --- |
| Mac 18121, retained lab administrator | Open customer workspace and Board22; follow project statement; switch statement/ledger | Correct customer/project context; columns 1/1/3; statement renders | 68 captured business projections hash-match; one deleted Board denied; no financial submission |
| Mac native/HTTP administrator | Read 30 Boards, five customer statement summaries, AR/EN/print/API routes | Independent financial calculations match; 99 HTTP checks pass | Anonymous login required; GET mutation rejected405; existing DB retained; mail/outbound blocked |
| backup-ERP, retained staging administrator | Recover old snapshot under separate prefix; prepare candidate; native reads before/after switch | All512 table hashes and3views recover; 30 Boards/5summaries/20Office views pass | Original llx_ tables retained; new m140_ namespace; separate empty documents; no restore over original records |
| backup-ERP Chrome administrator | Customer gallery, Board22, project statement/ledger, Office EN/AR, core navigation | All observed views render, context preserved; existing menus usable after restoring prior semantic permissions | No financial forms submitted, no password changes, other users remain inactive/no copied live authentication |
| Git application baseline | Compare each captured file and link against manifest in CI | 16,560 files and htdocs/custom relative link match | Code only; no private configuration, records, backups, attachments or screenshots uploaded |

Office financial posting stays disabled. Live attachment bytes and unrelated customer data were excluded. General application acceptance for modules outside these recorded read paths is not claimed. Historical source and private recovery evidence remain recoverable; a source snapshot does not imply any production deployment.
