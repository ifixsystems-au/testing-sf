<?php

namespace IFix\Testing\Command;

use Symfony\Component\Console\Tester\CommandTester;
use IFix\Testing\Assert as BaseAssert;

/**
 * Generic assertions for use in a command context.
 */
abstract class CommandAssert extends BaseAssert
{
    protected ?CommandTester $tester;

    public function setTester(CommandTester $tester)
    {
        $this->tester = $tester;
    }

    public function getTester(): ?CommandTester
    {
        return $this->tester;
    }

    /**
     * Check the exit code is as expected.
     */
    public function exitCodeEquals(int $expected)
    {
        $this->assert(
            $this->tester->getStatusCode() === (int) $expected,
            sprintf(
                'Incorrect exit code - expected %d, got %d',
                $expected,
                $this->tester->getStatusCode()
            )
        );
    }

    /**
     * Check that the expected string is displayed in the console.
     */
    public function seeInConsole(string $expected)
    {
        $this->assert(
            strpos($this->tester->getDisplay(), $expected) !== false,
            sprintf(
                'Unexpected console message, expected "%s", got "%s"',
                $expected,
                $this->tester->getDisplay()
            )
        );
    }
}
