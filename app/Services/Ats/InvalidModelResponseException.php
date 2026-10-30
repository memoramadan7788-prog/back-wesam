<?php

namespace App\Services\Ats;

use RuntimeException;

/** الموديل رجّع رد مش صالح حتى بعد كل المحاولات. الـ controller يحوله لـ 502. */
class InvalidModelResponseException extends RuntimeException
{
}
