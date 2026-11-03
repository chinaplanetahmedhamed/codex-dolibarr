# China Planet OS mission register

Maintained from the canonical ERP repository. Status values: `completed`, `open`, `failed`, `abandoned`. A status applies only to the named scope; deployment, functional acceptance and program phase are separate. Unknown history is not silently marked completed or failed. Do not renumber existing Mission IDs or business keys.

This initial register records directly relevant Missions whose evidence was inspected for the 2026-11-02 master audit. It is not a retrospective claim that all historical missions have been verified. Add earlier Missions as their specific evidence is reviewed; do not scan/modify archives solely to fill a status column.

| ID | Name | Goal | Status | Module/function folder | Mission/evidence | Limits / next step |
|---|---|---|---|---|---|---|
| M107 | Customer/project workspaces | Existing native customer > project > Boards hierarchy | completed | `custom/chinaplanetoperations/class/InternalTaskWorkspaceService.class.php` in existing Boards worktree | Recorded VPS current-state M107; Boards branch `codex/m107-customer-project-workspaces` | Recorded live release, not fresh parity; canonical checkout lacks these sources |
| M108–M115 | Boards improvements and recovery | Existing workspace/Board completion and presentation | completed | `custom/chinaplanetoperations/tasks/` in Boards worktree | Recorded VPS current-state M108–M115 | Historical scoped deployment; current file parity pending |
| M116 | Customer cash statements / five presentations | Manual cash book, exact split/history/audit and authorized summaries | completed | `custom/chinaplanetoperations/class/CustomerAccountService.class.php` in statement worktree | `<MAC_HOME>/Documents/Codex/2026-10-26/task-4/statement-ui-dev/docs/REPORTS/M116_UI_RELEASE_COMPLETION.md` | Recorded live scope excludes supplier integration, obligations and automatic payments; new master acceptance remains open |
| M118 | Local Dolibarr24.0.1 upgrade | Upgrade existing loopback runtime/core DB | completed | Local runtime outside source; `docs/MISSIONS/M118_LOCAL_DOLIBARR_24_UPGRADE.md` | [Report](docs/REPORTS/M118_COMPLETION_REPORT.md) | Accepted with documented download/parity limits; no new custom activation |
| M119 | Local custom module path correction | Repair two source symlinks | completed | `custom/chinaplanetcustomercenter/`, `custom/chinaplanetshippingprices/` | [Report](docs/REPORTS/M119_COMPLETION_REPORT.md) | ID collides with independent Office Expenses Mission; path repair only |
| M119 | Office Expenses / native finance | Standalone office workflow, evidence, native treasury and gated finance | open | Target `custom/chinaplanetofficeexpenses/`; source currently `<MAC_HOME>/Documents/Codex/2026-11-01/task/office-expenses-erp/custom/chinaplanetofficeexpenses/` | Same worktree `docs/REPORTS/M119_NATIVE_FINANCE_IMPLEMENTATION.md` | Recorded backup R11 code deployed; business settings disabled, approved mappings and native finance/staff-login acceptance pending. Production excluded |
| M120 | Local custom module links | Point all six existing module links at canonical source | completed | `custom/` | [Report](docs/REPORTS/M120_COMPLETION_REPORT.md) | Code availability only; no installation/activation or workflow acceptance |
| M121 | Master integration architecture audit | Audit existing product, define sources of truth/gaps and six-phase implementation plan | open | `docs/architecture/`, `custom/` ownership map | [Mission](docs/MISSIONS/M121_MASTER_INTEGRATION_ARCHITECTURE_AUDIT.md), [audit](docs/architecture/M121_ARCHITECTURE_AUDIT_2026-11-02.md) | Source audit/plan delivered; current runtime/schema/role/isolation evidence and Phase1 sign-off remain pending |

Future phases use the stable task keys in [progress tasks](docs/architecture/M121_PROGRESS_TASKS.csv) until a real Mission ID is reserved. Do not invent additional numbered Missions as though assigned/approved. Record any `failed`/`abandoned` decision with its scope, reason, date and evidence; retained history is never deleted to simplify this list.

Shared progress: [Google tracker](https://docs.google.com/spreadsheets/d/1QZMWYIHbuigCW_40XdIFC4ZSknVrYGVsW-IL2rjF8HQ/edit). Shared reference: [Google workflow manual](https://docs.google.com/document/d/1pxjs7J7qACZGfhj8XIEdEvw1A5Rfz06IvYphX9G5bEQ/edit). Preserve sharing and align statuses to evidence.

## Cloud review and owner authorization — 2026-11-03
The preceding register is the returned local M121 evidence snapshot, not newly verified runtime status. Local canonical descriptor versions may differ from GitHub main; do not overwrite newer local services with the older remote tree.

# missions-list
تحديث 3 نوفمبر 2026. القيم: completed / open / failed / abandoned. المرحلة والبيئة والاختبار والنشر حقول مستقلة.
هذا فهرس العمل المنشور في GitHub؛ سجل M121 المحلي غير متاح للمطابقة بعد. لا يستبدل تاريخه ولا يعيد تشغيل Missions قديمة.
| Mission ID | الاسم | الهدف | الحالة | المسار | المرحلة / العائق |
| --- | --- | --- | --- | --- | --- |
| GOV-20261103-RESET | ترحيل حوكمة الذكاء الاصطناعي | Ahmed/Work/Codex/Dot؛ أرشفة القديم | open | docs/governance/MIGRATION_REPORT.md | ERP/Drive نُشرا؛ Mac وReact main والوصول التقني pending |
| MAC-GOV-20261103 | تكليف Dot المحلي | حوكمة الماك وأدلة M121 | completed | docs/governance/DOT_MAC_HANDOFF.md | تقرير التنفيذ المحلي وصل؛ completion للنطاق الموثق فقط؛ وصول الاختبار/متابعة M121 ما زالا منفصلين |
| M121 | تدقيق وتخطيط المنتج المتكامل | ملكية المصدر/المالية/العلاقات والفجوات وخطة الاختبار | open | docs/architecture/M121_CLOUD_ARCHITECTURE_REVIEW_2026-11-03.md | source review موثق؛ runtime/schema/auth/backup blocked |

المهام الـ29 ومفاتيح P1-01…REL-02 في [خطة التنفيذ](docs/architecture/M121_MASTER_IMPLEMENTATION_PLAN_2026-11-03.md) و[سجل Drive القائم](https://docs.google.com/spreadsheets/d/1QZMWYIHbuigCW_40XdIFC4ZSknVrYGVsW-IL2rjF8HQ/edit). مفاتيح المراحل ليست أرقام Mission جديدة.
السجل التاريخي الكامل يبقى في docs/MISSIONS/ وdocs/REPORTS/ مع إشعارات supersession؛ لا تغيير أسماء المؤلفين أو الموافقات التاريخية إلى موافقات حالية.
كل إسناد جديد يسجل الهدف والمالك والفرع/commit والمسار والاختبارات والقضايا والخطوة التالية. لا completed دون evidence وقبول النطاق، ولا production ضمن التفويض الحالي.

## M121 cloud execution checkpoint — 2026-11-03
| Task subset | Status | Agent | Paths and evidence | Next action |
| --- | --- | --- | --- | --- |
| P2-01 read-model defects | open; isolated candidate source tests pass | Codex finance; Work review | custom/chinaplanetcustomercenter/admin/financial_portfolio.php; lib/financial.lib.php; PR59 /6bdc868 | Mac parity, full financial semantics and backup-erp acceptance |
| P3-01/P3-02 UI subset | open; isolated candidate source tests pass | Codex Boards; Work review | custom/chinaplanetoperations/js/boards.js; css/boards.css; class/InternalTaskBoardsService.class.php; PR60 /422284e | Mac reconcile, whole startup/CSS/viewport/role verification |
| P1-02…P1-06, REL-01 | open; access/runtime blockers | Local Codex; Dot reports | docs/governance/DOT_MAC_HANDOFF.md | Acknowledge local execution, read VPS skill, prove target/backup/schema/grants/source |
Full evidence: [cloud implementation checkpoint](docs/REPORTS/M121_CLOUD_IMPLEMENTATION_2026-11-03.md). No staging deployment or production change; no global completion claim.
