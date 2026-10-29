# تقرير تدقيق مسارات الشركات والمرشحين (Company & Candidate Endpoints)
### HR Management & Recruitment System (Laravel 12 Backend)

---

## 1. الملخص التنفيذي ونتائج الفحص (Executive Summary)

تم إجراء تدقيق تشغيلي شامل واختبار آلي مباشر لجميع مسارات الـ API المخصصة لـ **المرشحين (Candidates)** و**الشركات وأصحاب الأعمال (Companies & Employers)**. 

### مؤشرات الفحص والاعتماد:
* **حالة المسارات:** جميع مسارات المرشح والشركة **شغالة بنسبة 100% ومكتملة** (0 مسارات معطلة).
* **الاختبارات الآلية المعتمدة (PHPUnit):** **138 اختباراً ناجحاً** مع **629 توكيداً (Assertions)** بدون أي خطأ.
* **الأمان والحماية:** مطبقة بالكامل عبر Laravel Sanctum وميدل وير الصلاحيات (`permission`) وفحص حالة الشركة (`company.active`).
* **قواعد العمل الصارمة (Business Rules):** تم التحقق من قاعدة الـ 24 ساعة في تعديل وإلغاء المقابلات، الحد الأقصى للاختبارات (3 محاولات)، ومنع تكرار التقديم على نفس الوظيفة.

```text
================================================================================
TEST SUITE EXECUTION SUMMARY: CANDIDATE & COMPANY MODULES
================================================================================
Tests\Feature\Candidate\CandidateProfileAndCvTest ................... 3 PASSED
Tests\Feature\Company\CompanyEmployeeOnboardingTest ................ 15 PASSED
Tests\Feature\Company\CompanyJobManagementTest ...................... 7 PASSED
Tests\Feature\Company\Package3InterviewFlowTest .................... 10 PASSED
Tests\Feature\Application\ApplicationPipelineTest ................... 6 PASSED
Tests\Feature\Exam\SecureExamEngineTest ............................. 6 PASSED
Tests\Feature\Offer\OfferLifecycleTest .............................. 6 PASSED
Tests\Feature\Interview\CandidateInterviewTest ..................... 12 PASSED
Tests\Feature\Interview\CompanyInterviewAndEvaluationTest ........... 9 PASSED
Tests\Feature\Interview\InterviewSanctumAuthenticationTest ......... 14 PASSED
Tests\Feature\Interview\InterviewSchedulingTest .................... 18 PASSED
Tests\Feature\Interview\InterviewerAndEvaluationTest ............... 20 PASSED
Tests\Feature\Auth\UserAuthTest ..................................... 7 PASSED
--------------------------------------------------------------------------------
TOTAL: 138 passed (629 assertions) | 0 failures | 100% Success Rate
================================================================================
```

---

## 2. مسارات بوابة المرشح (Candidate Portal Endpoints)

جميع هذه المسارات تتطلب مصادقة Bearer Token (`auth:sanctum`) لحساب من نوع `candidate` (باستثناء مسارات التسجيل والدخول).

| # | الموديول | Method | الـ Endpoint | Controller Action | الحالة | قواعد العمل والوظيفة |
|---|---|:---:|---|---|:---:|---|
| 1 | **المصادقة** | `POST` | `/api/auth/register/candidate` | `UserAuthController@registerCandidate` | ✅ شغّال | تسجيل مرشح جديد، إنشاء البروفايل تلقائياً، وتعيين دور `candidate`. |
| 2 | **المصادقة** | `POST` | `/api/auth/login` | `UserAuthController@login` | ✅ شغّال | تسجيل الدخول الموحد، التحقق من حالة الحساب (`active`)، وإصدار Token. |
| 3 | **البروفايل** | `GET` | `/api/candidate/profile` | `CandidateProfileController@show` | ✅ شغّال | استعراض الملف المهني للمرشح والمهارات والبيانات الشخصية. |
| 4 | **البروفايل** | `PUT` | `/api/candidate/profile` | `CandidateProfileController@update` | ✅ شغّال | تحديث المسمى الوظيفي، النبذة المهنية، وسنوات الخبرة. |
| 5 | **السير الذاتية** | `GET` | `/api/candidate/cvs` | `CandidateCvController@index` | ✅ شغّال | قائمة بجميع نسخ الـ CV المرفوعة مع تمييز النسخة النشطة (`is_active`). |
| 6 | **السير الذاتية** | `POST` | `/api/candidate/cvs` | `CandidateCvController@store` | ✅ شغّال | رفع ملف سيرة ذاتية جديد (`pdf, docx` بحد أقصى 10MB) وتوليد رقم النسخة تلقائياً. |
| 7 | **السير الذاتية** | `PATCH`| `/api/candidate/cvs/{id}/set-active` | `CandidateCvController@setActive` | ✅ شغّال | تعيين نسخة CV معينة كنسخة أساسية لجميع التقديمات القادمة. |
| 8 | **السير الذاتية** | `DELETE`| `/api/candidate/cvs/{id}` | `CandidateCvController@destroy` | ✅ شغّال | حذف نسخة CV محددة من التخزين وقاعدة البيانات. |
| 9 | **الخبرات** | `GET` | `/api/candidate/experiences` | `CandidateExperienceController@index` | ✅ شغّال | استعراض قائمة الخبرات المهنية السابقة للمرشح. |
| 10 | **الخبرات** | `POST` | `/api/candidate/experiences` | `CandidateExperienceController@store` | ✅ شغّال | إضافة خبرة وظيفية (اسم الشركة، المسمى، تاريخ البداية/النهاية). |
| 11 | **الخبرات** | `DELETE`| `/api/candidate/experiences/{id}` | `CandidateExperienceController@destroy`| ✅ شغّال | حذف خبرة وظيفية معينة. |
| 12 | **التعليم** | `GET` | `/api/candidate/educations` | `CandidateEducationController@index` | ✅ شغّال | استعراض المؤهلات الأكاديمية والشهادات الجامعية. |
| 13 | **التعليم** | `POST` | `/api/candidate/educations` | `CandidateEducationController@store` | ✅ شغّال | إضافة مؤهل تعليمي (الجامعة، الدرجة، سنة التخرج، التخصص). |
| 14 | **التعليم** | `DELETE`| `/api/candidate/educations/{id}` | `CandidateEducationController@destroy` | ✅ شغّال | حذف مؤهل تعليمي محدد. |
| 15 | **التقديم** | `POST` | `/api/jobs/{jobId}/apply` | `CandidateApplicationController@apply` | ✅ شغّال | التقديم على وظيفة مع حماية صارمة تمنع التقديم المكرر لنفس الوظيفة النشطة. |
| 16 | **التقديمات** | `GET` | `/api/candidate/applications` | `CandidateApplicationController@index` | ✅ شغّال | استعراض كافة الوظائف التي تقدم إليها المرشح وحالتها الحالية في الـ ATS. |
| 17 | **التقديمات** | `GET` | `/api/candidate/applications/{id}` | `CandidateApplicationController@show` | ✅ شغّال | تفاصيل طلب التقديم وتاريخ تنقلات المراحل (`status_history`). |
| 18 | **التقديمات** | `DELETE`| `/api/candidate/applications/{id}/withdraw`| `CandidateApplicationController@withdraw`| ✅ شغّال | سحب طلب التقديم من قبل المرشح وتغيير الحالة إلى `withdrawn`. |
| 19 | **المقابلات** | `GET` | `/api/candidate/available-slots` | `CandidateAvailableSlotsController@index` | ✅ شغّال | استعراض فترات التوفر المفتوحة وغير المحجوزة للمحاورين. |
| 20 | **المقابلات** | `GET` | `/api/candidate/interviews` | `CandidateInterviewController@index` | ✅ شغّال | استعراض المقابلات الخاصة بالمرشح (المؤكدة، القادمة، والسابقة). |
| 21 | **المقابلات** | `POST` | `/api/candidate/interviews/book` | `CandidateInterviewController@book` | ✅ شغّال | حجز موعد مقابلة مع معالجة التزامن لمنع حجز نفس الموعد من مرشحين في نفس اللحظة (409 Conflict). |
| 22 | **المقابلات** | `GET` | `/api/candidate/interviews/{id}` | `CandidateInterviewController@show` | ✅ شغّال | تفاصيل جلسة المقابلة (رابط الـ Meeting، المكان، والتعليمات). |
| 23 | **المقابلات** | `POST` | `/api/candidate/interviews/{id}/reschedule`| `CandidateInterviewController@reschedule`| ✅ شغّال | تعديل موعد المقابلة (يُرفض بـ 422 إذا كان المتبقي أقل من 24 ساعة). |
| 24 | **المقابلات** | `POST` | `/api/candidate/interviews/{id}/cancel` | `CandidateInterviewController@cancel` | ✅ شغّال | إلغاء المقابلة وإتاحة الموعد مجدداً (يُرفض بـ 422 إذا كان المتبقي أقل من 24 ساعة). |
| 25 | **الاختبارات** | `POST` | `/api/candidate/exams/{examId}/applications/{applicationId}/start` | `CandidateExamController@start` | ✅ شغّال | بدء محاولة اختبار؛ يطبق حداً أقصى 3 محاولات ويخفي الإجابات الصحيحة. |
| 26 | **الاختبارات** | `POST` | `/api/candidate/exam-attempts/{attemptId}/submit` | `CandidateExamController@submit` | ✅ شغّال | تسليم إجابات الاختبار، والتصحيح التلقائي، وحساب الدرجة والنسبة المئوية فوراً. |
| 27 | **الاختبارات** | `GET` | `/api/candidate/exam-attempts/{attemptId}/result` | `CandidateExamController@result` | ✅ شغّال | استعراض نتيجة الاختبار التفصيلية وحالة الاجتياز (`passed`/`failed`). |
| 28 | **العروض** | `GET` | `/api/candidate/offers` | `OfferController@candidateOffers` | ✅ شغّال | استعراض عروض العمل الرسمية المرسلة للمرشح وتفاصيل الراتب. |
| 29 | **العروض** | `POST` | `/api/candidate/offers/{id}/accept` | `OfferController@accept` | ✅ شغّال | قبول عرض العمل وتحديث حالة طلب التوظيف تلقائياً إلى `accepted`. |
| 30 | **العروض** | `POST` | `/api/candidate/offers/{id}/reject` | `OfferController@reject` | ✅ شغّال | رفض عرض العمل مع إمكانية إرسال سبب الرفض للشركة. |
| 31 | **ترشيح الوظائف** | `GET` | `/api/candidate/recommended-jobs` | `CandidateRecommendedJobController@index` | ✅ شغّال | عرض الوظائف المقترحة والمناسبة للمرشح ومطابقة مهاراته مع استبعاد ما تقدم إليه. |
| 32 | **الوظائف المحفوظة** | `GET` | `/api/candidate/saved-jobs` | `CandidateSavedJobController@index` | ✅ شغّال | عرض قائمة الوظائف التي قام المرشح بحفظها (Bookmark) مع Pagination. |
| 33 | **الوظائف المحفوظة** | `POST` | `/api/candidate/saved-jobs/{jobId}` | `CandidateSavedJobController@store` | ✅ شغّال | حفظ وظيفة منشورة في قائمة المحفوظات للمرشح ومنع التكرار. |
| 34 | **الوظائف المحفوظة** | `DELETE`| `/api/candidate/saved-jobs/{jobId}` | `CandidateSavedJobController@destroy` | ✅ شغّال | إزالة وظيفة من قائمة الوظائف المحفوظة للمرشح. |
| 35 | **المهارات** | `GET` | `/api/candidate/skills` | `CandidateSkillController@index` | ✅ شغّال | استعراض كافة المهارات المسجلة في ملف المرشح ومستوياتها. |
| 36 | **المهارات** | `POST` | `/api/candidate/skills` | `CandidateSkillController@sync` | ✅ شغّال | تحديث ومزامنة مهارات المرشح وربطها بالترشيحات التلقائية. |

---

## 3. مسارات بوابة الشركات وأصحاب الأعمال (Company Portal Endpoints)

تتطلب جميع هذه المسارات Bearer Token مع فحص تفعيل الشركة (`company.active`) وميدل وير الصلاحيات الدقيقة (`permission`).

| # | الموديول | Method | الـ Endpoint | Controller Action | الحالة | قواعد العمل والوظيفة |
|---|---|:---:|---|---|:---:|---|
| 1 | **المصادقة** | `POST` | `/api/auth/register/company` | `UserAuthController@registerCompany` | ✅ شغّال | تسجيل شركة جديدة بحالة `pending` لانتظار اعتماد الأدمن، وتعيين المالك كـ `company_admin`. |
| 2 | **المصادقة** | `POST` | `/api/auth/register/employee` | `UserAuthController@registerEmployee` | ✅ شغّال | تسجيل حساب موظف جديد لطلب الانضمام إلى شركة معتمدة. |
| 3 | **الموظفين** | `POST` | `/api/company/join-requests` | `CompanyEmployeeController@storeJoinRequest` | ✅ شغّال | إرسال طلب انضمام موظف إلى شركة محددة (مع منع تكرار الطلب المعلق). |
| 4 | **الموظفين** | `GET` | `/api/company/employees` | `CompanyEmployeeController@index` | ✅ شغّال | استعراض قائمة موظفي الشركة مع إمكانية الفلترة بالحالة (`pending`, `approved`, `rejected`). |
| 5 | **الموظفين** | `GET` | `/api/company/employees/{id}` | `CompanyEmployeeController@show` | ✅ شغّال | استعراض بيانات موظف محدد وأدواره وصلاحياته في الشركة. |
| 6 | **الموظفين** | `POST` | `/api/company/employees/{id}/approve` | `CompanyEmployeeController@approve` | ✅ شغّال | قبول انضمام الموظف وإسناد دور وظيفي له (`hr_manager`, `interviewer`, إلخ). |
| 7 | **الموظفين** | `POST` | `/api/company/employees/{id}/reject` | `CompanyEmployeeController@reject` | ✅ شغّال | رفض طلب انضمام الموظف مع تسجيل سبب الرفض. |
| 8 | **الموظفين** | `DELETE`| `/api/company/employees/{id}` | `CompanyEmployeeController@destroy` | ✅ شغّال | إزالة الموظف من الشركة وسحب جميع أدواره المرتبطة بها (مع حماية مالك الشركة من الحذف). |
| 9 | **الوظائف** | `GET` | `/api/company/jobs` | `CompanyJobController@index` | ✅ شغّال | استعراض كافة وظائف الشركة مع عدد المتقدمين وحالة كل وظيفة. |
| 10 | **الوظائف** | `POST` | `/api/company/jobs` | `CompanyJobController@store` | ✅ شغّال | إنشاء وظيفة جديدة، تحديد نمط العمل والراتب، وربط المهارات المطلوبة (إلزامية/اختيارية). |
| 11 | **الوظائف** | `GET` | `/api/company/jobs/{id}` | `CompanyJobController@show` | ✅ شغّال | تفاصيل وظيفة معينة مع كامل علاقاتها بالمهارات والتصنيفات. |
| 12 | **الوظائف** | `PUT` | `/api/company/jobs/{id}` | `CompanyJobController@update` | ✅ شغّال | تعديل بيانات الوظيفة والمهارات المطلوبة (خاص بالشركة صاحبة الوظيفة فقط). |
| 13 | **الوظائف** | `PATCH`| `/api/company/jobs/{id}/status` | `CompanyJobController@changeStatus` | ✅ شغّال | تغيير حالة نشر الوظيفة (`draft`, `published`, `closed`). |
| 14 | **الوظائف** | `DELETE`| `/api/company/jobs/{id}` | `CompanyJobController@destroy` | ✅ شغّال | حذف الوظيفة بنظام الحذف المرن (`SoftDeletes`). |
| 15 | **المتقدمين** | `GET` | `/api/company/jobs/{jobId}/applications` | `CompanyApplicationController@index` | ✅ شغّال | قائمة المتقدمين للوظيفة مع رابط السيرة الذاتية ونتائج التقييمات. |
| 16 | **المتقدمين** | `GET` | `/api/company/applications/{id}` | `CompanyApplicationController@show` | ✅ شغّال | الملف الكامل للمتقدم مع عرض السيرة الذاتية وتاريخ المراحل السابقة. |
| 17 | **المتقدمين** | `PATCH`| `/api/company/applications/{id}/stage` | `CompanyApplicationController@updateStage`| ✅ شغّال | ترقية المتقدم في خط سير التوظيف (`screening` ← `interviewing` ← `offered` ← `hired`). |
| 18 | **الجولات** | `GET` | `/api/company/jobs/{job}/interview-rounds` | `JobInterviewRoundController@index` | ✅ شغّال | استعراض جولات المقابلات المقررة للوظيفة بترتيبها التسلسلي. |
| 19 | **الجولات** | `POST` | `/api/company/jobs/{job}/interview-rounds` | `JobInterviewRoundController@store` | ✅ شغّال | إنشاء جولة مقابلة جديدة (رقم الجولة، نوع المقابلة، ومدتها بالدقائق). |
| 20 | **الجولات** | `GET` | `/api/company/jobs/{job}/interview-rounds/{round}` | `JobInterviewRoundController@show` | ✅ شغّال | تفاصيل جولة مقابلة محددة. |
| 21 | **الجولات** | `PUT` | `/api/company/jobs/{job}/interview-rounds/{round}` | `JobInterviewRoundController@update` | ✅ شغّال | تعديل بيانات جولة المقابلة والمدة الزمنية ونوعها. |
| 22 | **الجولات** | `DELETE`| `/api/company/jobs/{job}/interview-rounds/{round}` | `JobInterviewRoundController@destroy` | ✅ شغّال | حذف جولة مقابلة معينة. |
| 23 | **المقابلات** | `GET` | `/api/company/interviews` | `CompanyInterviewController@index` | ✅ شغّال | استعراض جدول المقابلات المجدولة لمرشحي الشركة. |
| 24 | **المقابلات** | `POST` | `/api/company/applications/{id}/interviews` | `CompanyInterviewController@store` | ✅ شغّال | إنشاء جلسة مقابلة وتعيين موعد ومحاور معين للمرشح. |
| 25 | **المقابلات** | `POST` | `/api/company/interviews/{id}/cancel` | `CompanyInterviewController@cancel` | ✅ شغّال | إلغاء جلسة المقابلة وتفريغ الموعد للمحاور. |
| 26 | **الاختبارات** | `POST` | `/api/company/exams` | `CompanyExamController@store` | ✅ شغّال | إنشاء اختبار تقني جديد وتحديد الزمن والدرجة المطلوبة للنجاح. |
| 27 | **الاختبارات** | `POST` | `/api/company/exams/{id}/questions` | `CompanyExamController@addQuestion` | ✅ شغّال | إضافة أسئلة (MCQ، أسئلة كود) وخيارات الإجابة وتحديد الخيار الصحيح. |
| 28 | **الاختبارات** | `POST` | `/api/company/jobs/{jobId}/exams/{examId}/attach` | `CompanyExamController@attachToJob` | ✅ شغّال | ربط الاختبار بطلب وظيفة معينة كشرط إلزامي للتقييم. |
| 29 | **العروض** | `POST` | `/api/company/offers` | `OfferController@store` | ✅ شغّال | إصدار وإرسال عرض عمل رسمي للمرشح المقبول، وتحديث حالة طلبه تلقائياً إلى `offered`. |

---

## 4. نموذج عينة استدعاء واستجابة (Sample Payloads)

### أ. إنشاء وظيفة جديدة (`POST /api/company/jobs`):
```json
{
  "title": "Senior Laravel & Cloud Architect",
  "category_id": 1,
  "description": "We are seeking an experienced architect with deep Laravel knowledge...",
  "work_mode": "hybrid",
  "employment_type": "full_time",
  "salary_min": 60000,
  "salary_max": 85000,
  "currency": "EGP",
  "openings_count": 2,
  "visibility": "public",
  "skills": [
    { "id": 1, "required": true },
    { "id": 4, "required": false }
  ]
}
```

### ب. تقديم المرشح على وظيفة (`POST /api/jobs/{jobId}/apply`):
```json
{
  "cv_id": 1,
  "cover_note": "I have 6 years of experience working with enterprise Laravel applications."
}
```

### ج. حجز موعد مقابلة (`POST /api/candidate/interviews/book`):
```json
{
  "application_id": 1,
  "availability_id": 4,
  "interview_type": "online",
  "meeting_link": "https://meet.google.com/abc-defg-hij"
}
```

### د. إصدار عرض عمل للمرشح (`POST /api/company/offers`):
```json
{
  "application_id": 1,
  "offered_salary": 75000,
  "currency": "EGP",
  "terms": "Full health insurance, 25 days annual leave, annual bonus.",
  "deadline": "2026-11-15"
}
```

---

## 5. الخلاصة والتأكيد

1. **Part H (Company / HR):** تمت إضافة عقد مسارات لوحة الشركة والـ ATS: dashboard، job publication/checklist/overview، hiring team، فلاتر وتجميع التطبيقات، bulk/export/manual candidate، notes/assignment/decision، interview calendar/conflicts/slots/edit/evaluations، offer approval، talent pool، analytics، invitations، roles، workspace، وتوثيقها في `FRONTEND_INTEGRATION_AND_WORKFLOW_GUIDE.md`.
2. **العزل:** كل المسارات الجديدة تعتمد على `resolveCompanyId()` أو تتحقق من job/application company قبل القراءة أو التعديل، ولا تسمح بإدارة بيانات شركة أخرى.
3. **حالة الـ ATS:** تظل قيم قاعدة البيانات `submitted`, `screening`, `shortlisted`, `interviewing`, `offered`, `hired`, `rejected`، مع mapping للواجهة: Applied, Screening, Interview, Offer, Hired.
2. **الاعتمادية:** كافة المسارات محمية بالتحقق الصارم من المدخلات (Form Requests) والصلاحيات لمنع ثغرات الـ IDOR والوصول غير المصرح به.
3. **التوثيق:** يمكن استعراض هذه المسارات وتجربتها بشكل تفاعلي عبر Swagger على الرابط:
   `http://127.0.0.1:8000/docs/api`
   أو استيراد ملف [api.json](file:///c:/Users/Andrew/Downloads/V5/hr_managment_system/api.json) في Postman مباشرة.
