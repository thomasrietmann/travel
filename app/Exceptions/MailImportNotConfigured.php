<?php

namespace App\Exceptions;

use RuntimeException;

/** Contains only operator guidance about settings, never their values. */
final class MailImportNotConfigured extends RuntimeException {}
