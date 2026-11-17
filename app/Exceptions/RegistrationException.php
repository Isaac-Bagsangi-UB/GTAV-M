<?php

namespace App\Exceptions;

use DomainException;

/** Thrown when a registration breaks a capacity or cutoff rule. The message is safe to show to users. */
class RegistrationException extends DomainException {}
