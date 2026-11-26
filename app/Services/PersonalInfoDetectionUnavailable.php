<?php

namespace App\Services;

use RuntimeException;

/**
 * The text could not be checked for personal information. Callers must treat the text as
 * unchecked: never send it on, store it as clean, or show it publicly.
 *
 * The message never contains the text itself.
 */
class PersonalInfoDetectionUnavailable extends RuntimeException {}
