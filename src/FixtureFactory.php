<?php

namespace IFix\Testing;

use ReflectionClass;

/**
 * Fixture factory base class.
 *
 * Helper methods to create fixtures
 */
class FixtureFactory
{
    protected function buildEntity(string $class, array $properties = []): object
    {
        $refl = new ReflectionClass($class);
        /** @var object */
        $entity = $refl->newInstanceWithoutConstructor();
        $this->doSetProperties($refl, $entity, $properties);

        return $entity;
    }

    protected function setProperties(object $entity, array $properties)
    {
        $refl = new ReflectionClass(get_class($entity));
        $this->doSetProperties($refl, $entity, $properties);
    }

    private function doSetProperties(ReflectionClass $refl, object $entity, array $properties)
    {
        foreach ($properties as $property => $value) {
            $reflectionProperty = $refl->getProperty($property);
            $reflectionProperty->setAccessible(true);
            $reflectionProperty->setValue($entity, $value);
        }
    }
}
