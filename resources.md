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
