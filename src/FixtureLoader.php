<?php

namespace IFix\Testing;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use IFix\Testing\FixtureLoader\PostPersist;

/**
 * Fixture loader.
 *
 * Helper methods to reset database state
 */
class FixtureLoader
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    /**
     * Reset the database state - purge all ORM tables and reset
     * the sequences.
     *
     * @param string[] $sequences   The names of the sequences to reset
     * @param string[] $exclusions  The names of the tables to exclude from the purge
     */
    public function resetDatabase(array $sequences = [], array $exclusions = [])
    {
        $this->purgeDatabase($exclusions);
        $this->resetSequences($sequences);
    }

    /**
     * Purge the ORM tables in the database.
     */
    public function purgeDatabase(array $exclusions = [])
    {
        $purger = new ORMPurger($this->em, $exclusions);
        $purger->purge();
    }

    /**
     * Reset a sequence.
     *
     * @param string    $sequenceName   The name of the sequence to reset
     * @param int       $next           The next number to use in the sequence
     */
    public function resetSequence(string $sequenceName, int $next = 1): self
    {
        $rsm = new ResultSetMapping();
        $this->em->createNativeQuery("ALTER SEQUENCE $sequenceName RESTART $next", $rsm)->execute();

        return $this;
    }

    /**
     * Reset an array of sequences.
     *
     * @param string[]  $sequences  The names of the sequences to reset
     * @param int       $next       The next number to use in the sequence
     */
    public function resetSequences(array $sequences = [], $next = 1)
    {
        foreach ($sequences as $sequence) {
            $this->resetSequence($sequence, $next);
        }
    }

    /**
     * Reset a table.
     *
     * @param string    $tableName  The name of the table to reset
     */
    public function resetTable(string $tableName): self
    {
        $rsm = new ResultSetMapping();
        $this->em->createNativeQuery("TRUNCATE $tableName CASCADE", $rsm)->execute();

        return $this;
    }

    /**
     * Reset an array of tables.
     *
     * @param string[]  $tables     The names of the tables to reset
     */
    public function resetTables(array $tables = [])
    {
        foreach ($tables as $table) {
            $this->resetTable($table);
        }
    }

    /**
     * Persist an entity.
     *
     * @param object        $entity         the item to persist
     * @param PostPersist   $postPersist    what to do after persisting the entity
     */
    public function persistEntity(object $entity, PostPersist $postPersist = PostPersist::Clear)
    {
        $this->em->persist($entity);
        $this->em->flush();

        switch ($postPersist) {
            case PostPersist::Clear:
                $this->em->clear();
                break;
            case PostPersist::Refresh:
                $this->em->refresh($entity);
                break;
        }
    }

    /**
     * Persist an array of entities.
     *
     * @param mixed[]       $entities       an array of items to persist. could be another array of entities
     * @param PostPersist   $postPersist    what to do after persisting all entities
     */
    public function persistEntities(array $entities = [], PostPersist $postPersist = PostPersist::Clear)
    {
        foreach ($entities as $entity) {
            if (is_array($entity)) {
                $this->persistEntities($entity, PostPersist::None);
            } else {
                $this->persistEntity($entity, PostPersist::None);
            }
        }

        switch ($postPersist) {
            case PostPersist::Clear:
                $this->em->clear();
                break;
            case PostPersist::Refresh:
                $this->refreshEntities($entities);
                break;
        }
    }

    /**
     * Refresh an array of entities.
     */
    public function refreshEntities(array $entities = [])
    {
        foreach ($entities as $entity) {
            if (is_array($entity)) {
                $this->refreshEntities($entity);
            } else {
                $this->refreshEntity($entity);
            }
        }
    }

    /**
     * Refresh an entity.
     */
    public function refreshEntity(object $entity)
    {
        $this->em->refresh($entity);
    }
}
