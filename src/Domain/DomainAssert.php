<?php

namespace IFix\Testing\Domain;

use IFix\Testing\Assert as BaseAssert;
use PHPUnit\Framework\Assert as PhpUnitAssert;

/**
 * Generic assertions for use in a domain context.
 */
abstract class DomainAssert extends PhpUnitAssert {}
