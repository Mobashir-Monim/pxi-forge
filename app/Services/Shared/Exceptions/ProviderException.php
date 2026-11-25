<?php

namespace App\Services\Shared\Exceptions;

use RuntimeException;

/** Base for every app-shaped provider failure, so callers never catch a vendor exception. */
abstract class ProviderException extends RuntimeException {}
