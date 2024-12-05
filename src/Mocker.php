<?php

namespace IFix\Testing;

use Prophecy\Prophet;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Interacts with the Container to override services. Also provides some
 * helpers around creating prophecy mocks
 * 
 * @deprecated prefer to use the phpunit mocker
 */
class Mocker
{
    private ContainerInterface $container;
    private Prophet $prophet;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->prophet = new Prophet();
    }

    /**
     * Reset the mocker
     *
     * Clears the prophet of promises
     */
    public function reset()
    {
        $this->prophet = new Prophet();
    }

    /**
     * Mock a service.
     *
     * @param   string  $serviceId  Could be the class name, or an old_style.service_id.
     *                              If an old style service id, the classname must be provided.
     * @param   string  $className
     */
    public function mockService(string $serviceId, ?string $className = null): object
    {
        if ($className === null) {
            $className = $serviceId;
        }

        // Prepend service id with 'test.', as all overridable services
        // need to be defined in test config and set to public
        $serviceId = 'test.' . $serviceId;
        $mock = $this->createMock($className);
        $this->container->set($serviceId, $mock->reveal());

        return $mock;
    }

    /**
     * Create a prophecy mock helper.
     */
    public function createMock(string $className): object
    {
        return $this->prophet->prophesize($className);
    }

    /**
     * Check the predictions for the mocks.
     */
    public function checkPredictions()
    {
        $this->prophet->checkPredictions();
    }
}
