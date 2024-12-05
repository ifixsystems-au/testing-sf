<?php

namespace IFix\Testing\Command;

use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Generic test helpers for use in a command context.
 */
abstract class CommandTestCase extends KernelTestCase
{
    /**
     * Run the given command.
     */
    public function runCommand(string $name, array $arguments = [])
    {
        $application = new Application(self::$kernel);
        $command = $application->find($name);
        $tester = new CommandTester($command);
        $tester->execute($arguments);
        $this->getAssert()->setTester($tester);
    }

    /**
     * Get the assert.
     */
    abstract public function getAssert(): ?CommandAssert;
}
