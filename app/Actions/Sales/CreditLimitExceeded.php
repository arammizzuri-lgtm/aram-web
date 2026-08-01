<?php

namespace App\Actions\Sales;

use RuntimeException;

/**
 * Distinct from a plain failure: this one is approvable.
 *
 * The UI catches it specifically to offer a manager the override, rather than
 * treating a credit decision as an error the salesperson has to work around.
 */
class CreditLimitExceeded extends RuntimeException {}
