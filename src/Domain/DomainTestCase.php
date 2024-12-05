<?php

namespace IFix\Testing\Domain;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Generic test helpers for use in a domain context.
 */
abstract class DomainTestCase extends KernelTestCase
{
    /**
     * Get the assert.
     */
    abstract public function getAssert(): ?DomainAssert;
}
