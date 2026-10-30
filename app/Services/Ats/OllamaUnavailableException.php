<?php

namespace App\Services\Ats;

use RuntimeException;

/** Ollama مش شغال / timeout / الموديل مش منزّل. الـ controller يحوله لـ 503. */
class OllamaUnavailableException extends RuntimeException
{
}
