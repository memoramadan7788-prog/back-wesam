<?php

namespace App\Services\Ats;

use RuntimeException;

/**
 * خطأ متوقع من جهة المستخدم (ملف تالف، scan، صيغة مش مدعومة).
 * الـ controller يحوله لـ 422 مش 500.
 */
class CvExtractionException extends RuntimeException
{
}
