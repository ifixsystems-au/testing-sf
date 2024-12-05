<?php

namespace IFix\Testing;

use PHPUnit\Framework\Assert as PhpUnitAssert;

/**
 * Generic assertions that apply to all contexts.
 */
class Assert
{
    /**
     * Helper method to generate PHPUnit assertion exceptions.
     *
     * @param bool   $success
     * @param string $failMessage Message to use upon failure
     */
    public function assert($success, $failMessage)
    {
        PhpUnitAssert::assertTrue($success, $success ? '' : $failMessage);
    }

    /**
     * Check the array contains the expected elements
     *
     * @param  array  $haystack
     * @param  mixed  $needles
     */
    public function arrayContains(array $haystack, $needles)
    {
        if (!is_array($needles)) {
            $needles = [$needles];
        }

        $this->assert(count($haystack) === count($needles), sprintf(
            'Expected same number of elements, got %d elements and %d elements',
            count($haystack),
            count($needles)
        ));

        foreach ($needles as $key => $needle) {
            $this->assert(in_array($needle, $haystack, true), sprintf('Element %d not found in array', $key));
        }
    }
}
