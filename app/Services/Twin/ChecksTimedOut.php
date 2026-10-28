<?php

namespace App\Services\Twin;

use RuntimeException;

/**
 * Thrown inside GlbValidator when the checks of one file pass the time limit (TwinModelLimits::$checkSeconds);
 * the validator turns it into a plain-language reason.
 */
class ChecksTimedOut extends RuntimeException {}
