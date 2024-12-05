<?php

namespace IFix\Testing\Web;

use IFix\Testing\Web\WebTestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Generic test helpers for testing voters
 * 
 * Voters use the web test case because isGranted is a web concern
 */
abstract class VoterTestCase extends WebTestCase
{
    protected ?Security $security = null;
    protected ?TokenStorageInterface $tokenStorage = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->security = static::getContainer()->get(Security::class);
        $this->tokenStorage = static::getContainer()->get(TokenStorageInterface::class);
    }

    /**
     * Iterate the collection of roles, users, and subjects and check they are denied, unless
     * found in expectedGrants
     * 
     * @param string[]          $roles              Simple array of role strings
     * @param UserInterface[]   $users              Simple array of user entities
     * @param object[]          $subjects           Keyed array where the key is a label to use in case of deny:
     *                                              `['some subject' => $someSubject]
     * @param array[]           $expectedGrants     Array of arrays in the form of `['SOME_ROLE', $user, $subject]`
     *                                              Every combination of role, user and subject that doesn't appear
     *                                              here should be a denied
     */
    protected function checkAll(array $roles, array $users, array $subjects, array $expectedGrants)
    {
        foreach ($roles as $role) {
            foreach ($users as $user) {
                $this->loginUser($user);
                foreach ($subjects as $subjectKey => $subject) {
                    $this->assertEquals(
                        $this->isAccessGrantedCase($role, $user, $subject, $expectedGrants),
                        $this->security->isGranted($role, $subject),
                        sprintf('Voter failed for %s, %s, %s', $role, $user->getUserIdentifier(), $subjectKey)
                    );
                }
            }
        }
    }

    private function loginUser(UserInterface $user)
    {
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $this->tokenStorage->setToken($token);
    }

    private function isAccessGrantedCase($role, $user, $subject, array $expectedGrants): bool
    {
        return count(array_filter($expectedGrants, function ($case) use ($role, $user, $subject) {
            return
                $case[0] === $role &&
                $case[1] === $user &&
                $case[2] === $subject;
        })) > 0;
    }
}
