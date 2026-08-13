# دليل M041: الترجمة التلقائية والنشر عبر GitHub

آخر تحديث: 2026-08-03
النطاق: M041 فقط — ترجمة ملاحظات أسعار الشحن من الصينية إلى الإنجليزية مع إمكانية التصحيح اليدوي.

## 1. النتيجة المتوقعة

عند رفع ملف أسعار الشحن:

1. يحتفظ النظام بالنص الصيني الأصلي داخلياً.
2. يرسل الملاحظة إلى DeepSeek ويولد نصاً إنجليزياً تلقائياً.
3. يحافظ على الرموز التشغيلية مثل `40HQ` و`RMB180` وأرقام الرحلات والتواريخ.
4. يستطيع الموظف تعديل الترجمة الإنجليزية من شاشة المراجعة إذا وجد خطأ.
5. العميل الإنجليزي يرى النص الإنجليزي المعتمد فقط، ولا يرى النص الصيني أو تعليمات المزود أو بيانات داخلية.
6. إذا تعطلت خدمة الترجمة، يبقى السجل قابلاً للمراجعة والتصحيح اليدوي ولا يتم نشر ترجمة غير موثوقة تلقائياً.

المزود المثبت لهذا الإصدار:

- Endpoint: `https://api.deepseek.com/chat/completions`
- Model: `deepseek-v4-flash`
- Thinking: disabled لهذا الاستخدام حتى تكون النتيجة ترجمة مباشرة وقابلة للتحقق.

## 2. حدود الأمان

- الأثر البرمجي ثابت على commit:
  `47ceae0d56848c7badbfbcca4abc9e7af3badbc0`
- SHA-256 للأثر:
  `6ee49c1fb298aaa248d56ce771d37d018001b1fc56c0813234e836d8facc7beb`
- النشر التلقائي من push يذهب إلى `backup-erp` فقط.
- الإنتاج لا يعمل من push. يجب تشغيله يدوياً وإدخال:
  `DEPLOY_M041_47CEAE0_TO_PRODUCTION`
- GitHub لا يستخدم مفتاح `codex-deploy` المشترك. توجد حسابات ومفاتيح منفصلة: `cp-m041-staging` و`cp-m041-production`، وكل SSH key مربوط forced-command لا يقبل shell أو `scp` أو port forwarding.
- الحسابان يستطيعان تشغيل 18 أمراً ثابتاً فقط من helper مقفول: 9 staging و9 production.
- أوامر production التي تغيّر الحالة تحتاج أيضاً توقيع Ed25519 قصير العمر ومربوط بالـcommand والـcommit والـartifact. مفتاح SSH وحده لا يستطيع تجاوز موافقة GitHub.
- مفتاح DeepSeek لا يدخل Git، الأثر، قاعدة البيانات، سجل الاختبارات، أو واجهة المستخدم.
- كل بيئة تأخذ backup للكود وقاعدة البيانات قبل التطبيق.

## 3. إنشاء مفتاح DeepSeek

1. افتح منصة DeepSeek الرسمية: <https://platform.deepseek.com/>.
2. أنشئ حساباً وأضف رصيداً إذا طلبت المنصة ذلك.
3. افتح صفحة API Keys وأنشئ مفتاحاً جديداً.
4. خزّن المفتاح في password manager. لا ترسله في WhatsApp أو البريد أو محادثة Codex، ولا تضعه في ملف داخل المشروع.

المفتاح المتوقع يبدأ بـ`sk-`. إذا تغير تنسيق DeepSeek مستقبلاً، يجب مراجعة helper قبل توسيع التحقق؛ لا تُضعف التحقق مباشرة على السيرفر.

## 4. تثبيت helper على VPS لأول مرة

يتم هذا مرة واحدة فقط. من Mac، داخل المستودع الرسمي:

```bash
cd /Users/mac/Documents/dev/chinaplanet-OS/codex-dolibarr
scp -i /Users/mac/.ssh/codex_vision_deploy \
  deploy/tools/chinaplanetos_m041_delivery.sh \
  deploy/tools/chinaplanetos_m041_ssh_dispatch.sh \
  deploy/tools/install_m041_delivery_helper.sh \
  deploy/config/codex-deploy-m041-delivery.sudoers \
  deploy/config/m041-staging-deploy.pub \
  deploy/config/m041-production-deploy.pub \
  deploy/config/m041-production-approval-public.pem \
  codex-deploy@31.97.158.178:/home/codex-deploy/staging-inbox/
```

تحقق محلياً من البصمات قبل التثبيت:

```bash
shasum -a 256 \
  deploy/tools/chinaplanetos_m041_delivery.sh \
  deploy/tools/chinaplanetos_m041_ssh_dispatch.sh \
  deploy/tools/install_m041_delivery_helper.sh \
  deploy/config/codex-deploy-m041-delivery.sudoers \
  deploy/config/m041-staging-deploy.pub \
  deploy/config/m041-production-deploy.pub \
  deploy/config/m041-production-approval-public.pem
```

البصمات المعتمدة:

- Helper: `e02cbd675ae5bfce8cc6c6cabfaae2ec25de0aec20bf3bfbd4e2836d2cf5747c`
- SSH dispatcher: `25e6362da8e47e9b861d54b36d2b007b70ad1354c51766f7b516594749188c2b`
- Sudoers: `c0ec2c4c049ed562adcd3905166316b7e3019b47eb2904b3b34fa68481ad76b3`
- Staging public key: `97b1ee7fe52f5091c5484b954eca83816018f29ca1dfb33b8de80a2bff318682`
- Production public key: `f232bc128ff4107222fd93ffaf8fba90d78f57a43179a648281931a501ac4cf4`
- Production approval public key: `683ff83437ab5df160ca8e4f49673f0eba01c9fbbf744a1b27e58df4c33a6e9a`
- Installer: `e5225e931a33e3d445d59ddb1543776dc66176301dc980229b0a08ed93bee0e2`

بعد ذلك افتح جلسة root المعتمدة على VPS، ولا تستخدم صلاحيات `codex-deploy` لتجاوز هذه الخطوة:

```bash
sha256sum /home/codex-deploy/staging-inbox/chinaplanetos_m041_delivery.sh
sha256sum /home/codex-deploy/staging-inbox/chinaplanetos_m041_ssh_dispatch.sh
sha256sum /home/codex-deploy/staging-inbox/install_m041_delivery_helper.sh
sha256sum /home/codex-deploy/staging-inbox/codex-deploy-m041-delivery.sudoers
bash /home/codex-deploy/staging-inbox/install_m041_delivery_helper.sh
```

النجاح يجب أن ينتهي بـ:

```text
M041_HELPER_INSTALL=PASS ... USERS=2 FORCED_COMMAND=YES DEPLOY_EXECUTED=NO PROVIDER_CONFIGURED=NO
```

هذا التثبيت لا ينشر الكود ولا يلمس قاعدة البيانات أو production.

## 5. إعداد GitHub Environments وSecrets

افتح:
<https://github.com/chinaplanetahmedhamed/codex-dolibarr/settings/environments>

أنشئ بيئتين بالاسم المطابق تماماً:

1. `backup-erp`
2. `production`

المفاتيح الخاصة المنشأة محلياً محفوظة خارج Git في:

```text
/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/.local-secrets/m041/staging_deploy_key
/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/.local-secrets/m041/production_deploy_key
/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/.local-secrets/m041/production_approval_private.pem
```

الصلاحية يجب أن تبقى `0600`. لا تنقل هذه الملفات إلى المستودع.

داخل Environment `backup-erp` أضف:

| Secret | القيمة | ملاحظة |
|---|---|---|
| `DEPLOY_SSH_KEY` | محتوى `.local-secrets/m041/staging_deploy_key` | يعمل فقط كـ`cp-m041-staging` عبر forced-command |
| `DEPLOY_KNOWN_HOSTS` | سطر host key الموثق لـ`31.97.158.178` | استخدم القيمة المتحقق منها محلياً |
| `DEEPSEEK_API_KEY` | مفتاح DeepSeek الذي يبدأ بـ`sk-` | Staging key أو المفتاح المشترك |

داخل Environment `production` أضف:

| Secret | القيمة | ملاحظة |
|---|---|---|
| `DEPLOY_SSH_KEY` | محتوى `.local-secrets/m041/production_deploy_key` | يعمل فقط كـ`cp-m041-production` عبر forced-command |
| `DEPLOY_KNOWN_HOSTS` | نفس سطر host key الموثق | لا تستخدم scan غير موثوق |
| `DEEPSEEK_API_KEY` | مفتاح DeepSeek | لا يطبع في logs |
| `PRODUCTION_APPROVAL_PRIVATE_KEY` | محتوى `.local-secrets/m041/production_approval_private.pem` | يوقع كل mutation لمدة 10 دقائق وبأمر محدد |

من Settings → Secrets and variables → Actions → Variables، أنشئ Repository variable:

- Name: `M041_AUTODEPLOY_ENABLED`
- Value أثناء الإعداد الأول: `false`

اتركها `false` حتى ينجح Security/QA review ويتم تثبيت helper وإضافة الأسرار. بعد الجاهزية غيّرها إلى `true`. وجود هذا المفتاح يمنع أول push للـworkflow من محاولة النشر قبل اكتمال الإعداد.

للحصول على سطر host key الموثق من هذا الجهاز:

```bash
ssh-keygen -F 31.97.158.178 -f /Users/mac/.ssh/known_hosts
```

انسخ سطر `ssh-ed25519` الكامل. لا تعتمد على `ssh-keyscan` وحده من شبكة غير موثوقة.

في إعدادات `production`:

1. فعّل Required reviewers إذا كانت خطة GitHub تسمح.
2. أضف أحمد كمراجع.
3. اترك self-review معطلاً إن توفر الخيار.

حتى لو لم تدعم الخطة Required reviewers، يبقى الإنتاج محمياً بشرط `workflow_dispatch` والنص المطابق للإصدار، وبالتوقيع المشفر، وبوجود حالة staging `VERIFIED` على السيرفر.

## 6. كيف يعمل النشر اليومي

ملف التشغيل:
`.github/workflows/deploy-m041.yml`

### Backup ERP

عند push على الفرع `feature/m041-shipping-note-translation` في مسارات M041:

1. GitHub يفتح commit M041 المعتمد، وليس أي ملفات غير مثبتة.
2. يشغّل lint واختبارات M041 ومراجعات M034/M035 المرتبطة.
3. يبني artifact deterministic ويتحقق من SHA.
4. ينفذ `compare` ثم `preflight` على `backup-erp`.
5. يأخذ code backup وDB backup ثم يطبق schema والكود.
6. يثبت إعداد DeepSeek من secret ويشغّل داخلياً ترجمة synthetic؛ عند الفشل يعيد إعداد provider السابق آلياً.
7. يشغّل `verify` وprovider status وdeployment status.
8. إذا فشل التحقق بعد التطبيق، يشغّل rollback المقفول.

### Production

الإنتاج لا يعمل تلقائياً بعد push.

1. افتح GitHub → Actions → `Deploy pinned M041 shipping translation`.
2. اختر `Run workflow`.
3. اكتب بالضبط:
   `DEPLOY_M041_47CEAE0_TO_PRODUCTION`
4. GitHub يعيد staging أولاً.
5. بعد نجاح staging يطلب موافقة Environment إذا كانت مفعلة.
6. production يستخدم نفس artifact الذي اجتاز staging ونفس SHA. كل `apply` و`configure-provider` و`rollback` يحتاج توقيعاً منفصلاً صالحاً 10 دقائق ومربوطاً بـGitHub Run ID.

### بوابة قبول staging قبل الموافقة على production

نجاح GitHub workflow وحده لا يكفي للموافقة على production. سجّل نتائج الفحص التالي على `backup-erp` أولاً، ولا تمنح موافقة production قبل اكتمال كل البنود:

1. تأكد أن `staging-verify` و`staging-provider-status` ناجحان وأن provider يعرض `KEY=REDACTED`.
2. من `/custom/chinaplanetshippingprices/admin/import.php` ارفع ملف draft مضبوطاً يحتوي ملاحظة صينية ورموزاً ثابتة، مثل: `南沙 航次 AB12CD34 40HQ 加收 RMB180`.
3. افتح `/custom/chinaplanetshippingprices/admin/batch.php?id=<batch-id>` وتأكد أن الإنجليزية تولدت تلقائياً، ولا تحتوي Han characters، وحافظت على `AB12CD34` و`40HQ` و`RMB180`.
4. عدّل الترجمة الإنجليزية يدوياً، احفظها، ثم أعد فتح الصفحة وتأكد أن تصحيح الموظف بقي كما هو.
5. افحص العرض العام الإنجليزي من `/custom/chinaplanetshippingprices/index.php?view_lang=en` باستخدام بيانات staging مضبوطة فقط. يجب أن يرى العميل الإنجليزية، من دون النص الصيني أو API key أو provider response أو supplier/cost/margin/private notes.
6. افحص صفحات الإدارة والعرض العام بالإنجليزية والعربية: اتجاه LTR/RTL صحيح، ولا يوجد overflow أو قص للنص على desktop وعرضي هاتف `390px` و`360px`.
7. لا تنشر batch تجريبياً إلى العملاء. اترك ملف UI التجريبي draft وغير current، وسجّل `batch-id`. اختبارات التكامل الآلية تنشئ بياناتها المؤقتة وتحذفها بنفسها؛ وإذا استخدمت batch حقيقياً للاختبار، أعد نصه الإنجليزي الأصلي بعد الفحص وسجّل ذلك.
8. املأ سجل القبول أدناه وأرفق GitHub Run ID ولقطات desktop/390/360. موافقة production قبل هذا السجل ممنوعة.

| فحص staging | الدليل/الرابط | النتيجة |
|---|---|---|
| Workflow + provider redaction | Run ID: | PASS/FAIL |
| Admin auto-translation | Batch ID: | PASS/FAIL |
| Manual correction persisted | Batch ID: | PASS/FAIL |
| Customer English-only + privacy | URL: | PASS/FAIL |
| Arabic/English RTL/LTR | URLs: | PASS/FAIL |
| Desktop / 390px / 360px | Screenshots: | PASS/FAIL |
| Synthetic cleanup/draft isolation | Batch ID/evidence: | PASS/FAIL |
| Ahmed staging acceptance | Name/date: | ACCEPTED/REJECTED |

### قبول production بعد النشر

بعد موافقة أحمد وتشغيل production، نفذ الفحص نفسه على `https://erp.visiontrading.biz` قبل إعلان النجاح:

1. سجل `production-verify` و`production-provider-status` وGitHub Run ID؛ يجب أن تبقى الأسرار محجوبة.
2. استخدم أول ملف عمل حقيقي معتمَد يحتوي ملاحظة صينية كـdraft، وتحقق من الترجمة والتوكنات ثم جرّب تصحيحاً يدوياً وأعد النص الأصلي إن كان التعديل للاختبار فقط.
3. لا تنشئ أو تنشر batch صناعي على production. اختبار provider الداخلي لا يكتب صفوفاً، واختبارات DB التكاملية تنظف fixtures تلقائياً.
4. بعد النشر الحقيقي المعتمد فقط، افحص العرض العام الإنجليزي، عزل بيانات العملاء، وعدم ظهور الصينية أو معلومات المورد والتكلفة والهامش والملاحظات الخاصة.
5. افحص العربية والإنجليزية على desktop و`390px` و`360px` وسجل اللقطات.

| فحص production | الدليل/الرابط | النتيجة |
|---|---|---|
| Workflow + provider redaction | Run ID: | PASS/FAIL |
| Admin translation/edit persistence | Batch ID: | PASS/FAIL |
| Customer English-only + privacy | URL: | PASS/FAIL |
| Arabic/English RTL/LTR | URLs: | PASS/FAIL |
| Desktop / 390px / 360px | Screenshots: | PASS/FAIL |
| No synthetic published data | Evidence: | PASS/FAIL |
| Ahmed production acceptance | Name/date: | ACCEPTED/REJECTED |

إعادة تشغيل workflow لتدوير مفتاح DeepSeek آمنة: إذا كانت M041 مطبقة بالفعل يرجع helper `NOOP_ALREADY_APPLIED`، ولا يشغّل workflow rollback الكامل إذا فشل تغيير provider؛ provider يعيد إعداده السابق داخلياً.

## 7. فحص الحالة يدوياً

هذه أوامر read-only عبر المفاتيح المخصصة والـforced dispatcher:

```bash
ssh -i /Users/mac/Documents/dev/dolibarr/dolibarr-Dev/.local-secrets/m041/staging_deploy_key cp-m041-staging@31.97.158.178 'run staging-status'
ssh -i /Users/mac/Documents/dev/dolibarr/dolibarr-Dev/.local-secrets/m041/staging_deploy_key cp-m041-staging@31.97.158.178 'run staging-provider-status'
ssh -i /Users/mac/Documents/dev/dolibarr/dolibarr-Dev/.local-secrets/m041/production_deploy_key cp-m041-production@31.97.158.178 'run production-status'
ssh -i /Users/mac/Documents/dev/dolibarr/dolibarr-Dev/.local-secrets/m041/production_deploy_key cp-m041-production@31.97.158.178 'run production-provider-status'
```

الـprovider status يعرض `KEY=REDACTED` فقط.

## 8. Rollback

استخدم rollback فقط عند وجود سبب واضح وبعد حفظ نتيجة `status` و`verify`.

Backup ERP:

شغّل GitHub workflow أو استخدم مفتاح staging المخصص: `run staging-rollback`.

Production:

افتح Actions → `Roll back pinned M041 production release` ثم اكتب بالضبط `ROLLBACK_M041_47CEAE0_FROM_PRODUCTION`. يستخدم workflow بيئة production وتوقيعاً قصير العمر. مفتاح production وحده لا يكفي؛ helper يرفض rollback بلا توقيع command-specific وحالة staging موثقة.

الـhelper يعيد ملفات الكود وإعداد provider السابق. يحاول أولاً إعادة schema إلى pre-state. إذا كانت أعمدة M041 تحتوي بيانات تمنع الحذف الآمن، يعيد full DB backup السابق المتزامن مع الكود ويثبت `SCHEMA=RESTORED_DATABASE_BACKUP`. قبل rollback يأخذ reapply backup جديداً للكود وقاعدة البيانات وإعداد provider؛ إذا فشل أي تحقق أثناء rollback يعيد هذه الحالة الجديدة آلياً ويطبع `M041_ROLLBACK_REAPPLY=PASS`.

## 9. تغيير مفتاح DeepSeek

الطريقة المفضلة:

1. أنشئ مفتاحاً جديداً في DeepSeek.
2. حدث `DEEPSEEK_API_KEY` في Environment `backup-erp`.
3. شغّل push/re-run وتأكد من provider test.
4. حدث Environment `production`.
5. نفذ workflow production بالموافقة الدقيقة.
6. ألغِ المفتاح القديم من منصة DeepSeek بعد نجاح البيئتين.

لا تعدل `/etc/chinaplanetos/m041-*-translation.override.yml` يدوياً. عند إعادة نشر Stack من Portainer، افحص `provider-status` لأن أي redeploy لا يضم override قد يزيل environment variables من الحاوية.

## 10. إصدار مستقبلي بعد M041

هذه automation ليست ناشراً عاماً لأي تغيير. هي مقفولة على M041 لمنع وصول commit غير مراجع إلى production.

لكل Mission جديدة:

1. أنشئ manifest دقيقاً للملفات.
2. ثبت commit وartifact SHA جديدين.
3. حدث helper/workflow والاختبارات.
4. نفذ Security وQA review.
5. ثبت helper checksum الجديد من root.
6. اترك staging تلقائياً، واجعل production موافقة منفصلة دائماً.

بهذا لا تحتاج نسخ الملفات يدوياً بين backup وlive، لكن لا يتم تحويل GitHub إلى باب production مفتوح.

## 11. Troubleshooting سريع

- `HELPER_HASH_MISMATCH`: الملف المرفوع لا يطابق النسخة المراجعة؛ لا تعدل البصمة على السيرفر.
- `ARTIFACT_HASH_MISMATCH`: artifact مختلف؛ أوقف النشر وابنه من commit المثبت.
- `TARGET_*_MISMATCH`: اسم container/project/volume تغير؛ افحص topology ولا تغيّر helper بالتخمين.
- `PROVIDER_KEY_FILE_INVALID`: الملف ليس owner/mode المطلوبين أو رابط symbolic.
- `PROVIDER_KEY_FORMAT_INVALID`: المفتاح ناقص أو تنسيقه غير المتوقع.
- `PROVIDER_NOT_CONFIGURED`: أعد إعداد secret ثم شغّل provider configuration عبر workflow.
- `CANDIDATE_STATIC_TEST_FAILED`: الكود أو runtime غير متوافق؛ لا تتجاوز الاختبار.
- `SCHEMA_CHECK_DRIFT_OR_FAILURE`: schema ليست الحالة المتوقعة؛ يلزم تشخيص DB قبل أي apply.
- `STAGING_NOT_VERIFIED`: production مرفوض حتى تنجح نفس بصمة M041 في staging ويسجلها root.
- `PRODUCTION_APPROVAL_SIGNATURE_INVALID`: التوقيع أو المفتاح لا يطابق public key المثبت؛ لا تتجاوز الحماية.
- `PRODUCTION_APPROVAL_EXPIRED_OR_TOO_LONG`: أعد التشغيل من GitHub لإنتاج توقيع جديد قصير العمر.
- GitHub job ينتظر: راجع Environment approval في `production`.

## 12. ملفات الصيانة

- Workflow: `.github/workflows/deploy-m041.yml`
- Production rollback workflow: `.github/workflows/rollback-m041.yml`
- Root helper source: `deploy/tools/chinaplanetos_m041_delivery.sh`
- Forced SSH dispatcher: `deploy/tools/chinaplanetos_m041_ssh_dispatch.sh`
- Installer: `deploy/tools/install_m041_delivery_helper.sh`
- Sudoers contract: `deploy/config/codex-deploy-m041-delivery.sudoers`
- Delivery test: `deploy/tests/test_m042_m041_delivery_helper.sh`
- Runtime manifest: `deploy/releases/m041-runtime-files.txt`
- Support manifest: `deploy/releases/m041-support-files.txt`
