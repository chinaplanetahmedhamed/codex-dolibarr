# M121 — مرجع إجراءات الاختبار والتوثيق
الحالة: NOT RUN / BLOCKED للمسارات المصادقة. لا توجد screenshots workflow مولدة في هذا التشغيل.
الدليل المشترك الحالي: https://docs.google.com/document/d/1pxjs7J7qACZGfhj8XIEdEvw1A5Rfz06IvYphX9G5bEQ/edit
يعاد استخدامه وتضاف نتائج فعلية؛ لا استبدال الأدلة أو فبركة نجاح.

قبل الانتقال من وظيفة إلى التالية يسجل:
- Mission/task/test-run ID، البيئة والـcommit/schema، الدور والصلاحيات، fixture مجهّل.
- المسار والخطوات وماذا يتوقع الموظف، expected/actual، pass/fail/blocked.
- screenshots حقيقية مجهلة عند توافرها، المرفقات والروابط، defect/recovery/retest.
- هل العمل operationally completed وهل الالتزام ماليًا settled؟ كل منهما مستقل.

سيناريو الأساس: DEMO-M121 customer/project/3suppliers/container، deposit50,000 ثم payments5,000/10,000/3,000 => cash32,000. عقد40,000 ومدفوع10,000 => supplier remaining30,000 ولا مصروف نقد جديد. لا إرسال بريد حقيقي ولا بيانات مالية إنتاجية.
أعد السيناريو بمورد مرتبط بعميلين، تمويل شركة، splits، FX، retries، reverse، closed/history/reopen، Staff/Customer negative routes.
لا تختبر كتابة قبل verified backup/restore وtarget isolation. النشر مسموح backup-erp فقط؛ الإنتاج ممنوع.
المشاهدة الوحيدة من browser: Login @24.0.1 على backup-erp؛ لا استنتاج نجاح login أو create-user أو payment أو shipment أو document.
