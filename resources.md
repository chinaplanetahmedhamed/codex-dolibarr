# China Planet OS resource register

Updated 2026-11-02 UTC for M121. All ERP paths are relative to this repository unless qualified. **Observed source** is distinct from **recorded deployed** and **fresh runtime verification**. Nothing was deployed by M121. Keep version, deployment status, reason and evidence current when a reviewed change occurs.

| Module/resource | Path | Functionality | Observed source version / latest relevant change | Last updated reason | Deployment status / evidence |
|---|---|---|---|---|---|
| Customer Center | `custom/chinaplanetcustomercenter/` | Customer/project portal, finance projections, commitments and controlled documents | Canonical descriptor2.5.0; M116 source tree carries2.3.2 | Inventory existing owner; preserve newer native/funding bridges in main | Existing module recorded live; current main contents/parity unknown. Local source link observed; activation not verified |
| Financial Portfolio | `custom/chinaplanetcustomercenter/admin/financial_portfolio.php` and `lib/financial.lib.php` | Customer cash/obligation/invoice roll-up and statement links | Current canonical unmodified source hashed in M121 evidence | Identify currency/project/ACL inconsistencies before unifying | Current deployed exact variant unknown; not functionally accepted by M121 |
| Finance Allocation Center / commitments | `custom/chinaplanetcustomercenter/admin/financial.php`, `class/CustomerFundsService.class.php`, `sql/llx_cpcc_*.sql` | Native receipt evidence, reservations/attributions, charges/adjustments and audit | Canonical existing implementation; supplier native payment attribution present | Reuse rather than new ledger | Source-only for exact canonical variant; runtime schema/permissions unknown |
| Operations / Boards | `custom/chinaplanetoperations/`; newer source in `<MAC_HOME>/Documents/Codex/2026-10-24/task-2/release-work/source/` | Customer > native project > canonical task, lists, completion, deadlines, permissions, activity | Descriptor2.9.0; recorded M108–M115 overlays | Audit missing canonical files and integration surfaces | Recorded live; current runtime hashes not verified. Canonical `tasks/boards.php` absent |
| Customer cash statements | Existing statement source `<MAC_HOME>/Documents/Codex/2026-10-26/task-4/statement-ui-dev/custom/chinaplanetoperations/` | Manual cash book, original currencies/rates, project/task splits, five views, exact arithmetic/audit | Local0fb1dcc; recorded runtime f6fefab / M116 | Prevent independent native/CPCC/manual cash entry duplication | Recorded live2026-10-27; not in canonical main. No supplier/obligations integration in this scope |
| Native supplier bridge | `custom/chinaplanetoperations/class/OperationSupplierWorkflowService.class.php` | Native supplier order/invoice/payment with customer/project source markers and retry protection | Existing canonical source | Reuse native cash/evidence path and assess multi-supplier support | Exact runtime parity unknown; do not overwrite with older release tree |
| Office Expenses | Target `custom/chinaplanetofficeexpenses/`; source `<MAC_HOME>/Documents/Codex/2026-11-01/task/office-expenses-erp/custom/chinaplanetofficeexpenses/` | Standalone office requests, private evidence, branch roles, treasury, gated native finance | Descriptor0.2.0; source HEAD53ac3ca; finance application a6d1893, R11 release documented | Inventory native bridge already present; retain compatibility paths and disabled business settings | Recorded staging/backup R11 code deployed; financial activation/native acceptance pending. Not live; absent canonical checkout |
| Shipping Center / Logistics | `custom/chinaplanetlogistics/` | Containers, shipment operational status/ETA/free time, customer/project/order and public/private documents | Canonical1.4.0; statement branch1.3.0 plus shared-document features | Reconcile features by source, not version number alone | Existing recorded live; current exact variant unknown |
| Shared shipment documents | B/S:`custom/chinaplanetlogistics/class/ShipmentDocumentService.class.php`; Operations task context | Shared storage/version/publication, shipment/task files and cover | Existing M099-era services | Reuse for Board uploads and Docs save-back | Recorded staging evidence has browser acceptance limits; current flags/schema/parity unknown |
| Shipping Prices | `custom/chinaplanetshippingprices/` | Rate inquiry and workbook draft/review/publish | Descriptor1.5.0; source note translation remains disabled | Preserve existing release/business rules | Existing recorded live; local source availability observed, exact current rights/parity unknown |
| Documents generator | Workspace `../shipping-doc-generator-main/` | Existing document editor/template/export service and Dolibarr staff integration | Local HEAD04f3d24 plus dirty edits; recorded live Docs M006R1 source9a1bba2 | Pin actual live renderer before ERP packaging/integration | Recorded live docs.chinaplanet.org and separate docstest; fresh source/DB/ERP-target isolation unknown |
| Legacy cpkanban | `custom/cpkanban/` | Existing distinct Kanban data/UI | Descriptor2.1.0 | Preserve callers/data until retirement assessed | Canonical source/local link observed; current activation/parity unknown |
| Vision UI | `custom/visiontradingui/` | Existing Dolibarr UI hooks/theme | Descriptor0.1.0 | Reuse native presentation and avoid core changes | Source/local link observed; current activation/parity unknown |
| Public website + secure Node API | Workspace `../moaz-react/`, `server/`, WordPress bridge | Website/content/catalog inquiry intake and server-side Dolibarr boundary | Independent dirty branch codex/m087-china-guide-map | Keep history separate and sensitive data server-side | Current release parity outside M121; no writes |
| Customer mobile app | `mobile/chinaplanet-customer-app/` | Customer-safe mobile surfaces | Existing dirty main changes preserved | Track affected parity in each phase | New staff statement/office flows not exposed by this audit |
| Architecture + plan | `docs/architecture/M121_ARCHITECTURE_AUDIT_2026-11-02.md`, `M121_IMPLEMENTATION_PLAN.md` | Source map, risk/gap register, staged implementation/acceptance | M121 v1 | Ahmed master instructions | Local documentation only, runtime gaps open |
| Mission register | `missions-list.md` | ID/name/goal/status/path and scope-qualified legacy collision | M121 initial bounded register | Centralize future IDs without inventing historical status | Local documentation |
| Evidence inventory | `docs/evidence/m121-2026-11-02/` | Branch/status/hash observations and source DDL relations | Source-only, dated2026-11-02 UTC | Distinguish actual source and recorded deployments from unknown runtime | No secrets or business records included |
| Progress list | [Google tracker](https://docs.google.com/spreadsheets/d/1QZMWYIHbuigCW_40XdIFC4ZSknVrYGVsW-IL2rjF8HQ/edit); `docs/architecture/M121_PROGRESS_TASKS.csv` | Six program phases and granular task dependencies/evidence/blockers | Coordinator-created private tracker; local29-row task seed | Record verified progress and pending acceptance | Exact shared contents/readback tracked in M121 completion; sharing unchanged |
| Workflow manual | [Google manual](https://docs.google.com/document/d/1pxjs7J7qACZGfhj8XIEdEvw1A5Rfz06IvYphX9G5bEQ/edit); `docs/architecture/M121_WORKFLOW_MANUAL.md` | Step evidence, roles, expected/actual, sanitized screenshots and limits | Initial scaffold; no new workflow accepted | Document each tested step before advancing | Shared existing document, no new functional screenshots/assertions |

## Folder and ownership rule

All future ERP module/function changes are made in this main repository under the owning `custom/<module>/`, with reusable service rules in `class/`/`lib/`, existing `sql/` or approved module schema conventions, `langs/` for labels and scoped tests in `deploy/tests/`. Do not start another external task checkout, integration repository or duplicate module for the same business fact. Existing outside worktrees remain read-only evidence until their changes are reconciled safely; no relocation/deletion or live mount change is implied.

Keep website/API history separate in `moaz-react/`; Docs remains the existing workspace project until its packaging decision is reviewed. Private configuration, documents, financial backups and runtime data stay out of source. Link their controlled locations without copying sensitive contents into this register.

## Cloud review and owner authorization — 2026-11-03
The preceding register is the returned local M121 evidence snapshot, not newly verified runtime status. Local canonical descriptor versions may differ from GitHub main; do not overwrite newer local services with the older remote tree.

# resources
تحديث 3 نوفمبر 2026؛ inventory مصدر، وليس جرد runtime. الجذر المعتمد لكل وحدة ERP: custom/.
| المورد | المسار | الوظيفة | آخر إصدار مصدر / سبب التحديث | staging/live |
| --- | --- | --- | --- | --- |
| Customer Center | custom/chinaplanetcustomercenter/ | عميل/بوابة/التزامات/إسقاط مالي/وثائق | descriptor2.3.2؛ تدقيق التكامل دون تعديل كود | مثبت runtime غير متحقق |
| Financial Portfolio | custom/chinaplanetcustomercenter/admin/financial_portfolio.php | إجمالي العملاء والمتأخرات | main36de618؛ رُصد جمع عملات ومعنى available | غير متحقق |
| Finance read model | custom/chinaplanetcustomercenter/lib/financial.lib.php | native receipts/credit use/commitments/statement | موجود؛ يحتاج فصل cash/credit وتصفية سليمة | غير متحقق |
| Operations | custom/chinaplanetoperations/ | عملية/مهمة/سياق عميل ومشروع | descriptor2.7.0 على main؛ مصادر أحدث منفصلة | غير متحقق |
| Boards | custom/chinaplanetoperations/tasks/boards.php | workspace/Board/card | 113ddc6؛ غير موجود في main؛ لا overlay كامل | غير متحقق |
| Customer account M116 | custom/chinaplanetoperations/tasks/account.php | cashbook وتقسيمات وعكس وتدقيق | 0fb1dcc؛ مصدر قائم لا ledger جديد | live مذكور بالسجل السابق فقط؛ ليس تحققًا حاليًا |
| Logistics | custom/chinaplanetlogistics/ | شحن/أحداث/مستندات | descriptor1.3.0؛ إعادة استخدام الملكية | غير متحقق |
| Shipping Prices | custom/chinaplanetshippingprices/ | import/review/publish/booking | descriptor1.5.0؛ gateway قائم | غير متحقق |
| cpkanban | custom/cpkanban/ | Kanban قائم | descriptor2.1.0؛ تحديد الحدود قبل الإلغاء | غير متحقق |
| Vision UI | custom/visiontradingui/ | shell/hooks | descriptor0.1.0؛ لا مصدر أعمال مستقل | غير متحقق |
| Office Expenses | custom/chinaplanetofficeexpenses/ | مصروفات شركة | 53ac3ca؛ موجود بفرع Office وليس main | backup نشر مذكور تاريخيًا/إعدادات disabled؛ لم يتحقق الآن |
| Docs generator | shipping-doc-generator repository | القوالب/التحرير/تصدير ERP | 04f3d24؛ adapter داخل custom عند الاعتماد | لا تثبت docstest عزل ERP |
| ERP test | https://backup-erp.visiontrading.biz/ | الوجهة الوحيدة المسموحة للنشر/الاختبار | صفحة دخول تعرض24.0.1، 3 نوفمبر2026 | Login فقط؛ لا مصادقة أو قبول وظائف |
| Production | erp.visiontrading.biz | خارج النطاق | لم يُفتح أو يُعدّل في هذا التشغيل | ممنوع |
| Progress | Google Drive Blueprint workspace | مراحل/Tasks/Test Log | إعادة استخدام الملف القائم، لا نسخة منافسة | وثائق فقط |

تسجل كل ترقية لاحقة: الوحدة/الدالة، المسار، commit/version، سبب التغيير وwhats-new، dependencies/schema، نتيجة الاختبار، environment، deployed SHA/date، backup/rollback. Unknown لا تتحول إلى deployed بالاستنتاج من main أو HTTP200.
