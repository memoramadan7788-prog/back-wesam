<?php

namespace App\Services\Ats;

use RuntimeException;

/** اسم موديل مش في القايمة المسموحة. الـ controller يحوله لـ 422. */
class ModelNotAllowedException extends RuntimeException
{
}
