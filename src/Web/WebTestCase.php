<?php

namespace IFix\Testing\Web;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase as BaseWebTestCase;
use Behat\Mink\Driver\BrowserKitDriver;
use Behat\Mink\Session;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Generic web test case
 * 
 * Logging in a user
 * -----------------
 * 
 * Application WebTestCases must provide a strategy for logging on a user.
 * Ultimately you will use one of the two strategies provided here -
 * `setServerAuthenticatedUsername` or `setSessionAuthenticatedUser`.
 * See their documentation for suggested implementation.
 */
abstract class WebTestCase extends BaseWebTestCase
{
    private ?Session $session = null;

    public function getSession(): Session
    {
        if ($this->session === null) {
            $driver = new BrowserKitDriver(static::createClient());

            $this->session = new Session($driver);

            // start the session
            $this->session->start();
        }

        return $this->session;
    }

    /**
     * Set the REMOTE_USER server variable in the client
     * 
     * One of the two options for using setting a user as the one to be used
     * for a test. This option is to be used when the username is retrieved from
     * the web server (perhaps via Mellon).
     * 
     * Requires an `asUser` function to be defined on the application WebTestCase:
     * 
     * ```
     *     protected function asUser($username)
     *     {
     *         $this->setServerAuthenticatedUsername($username);
     *     }
     * ```
     */
    protected function setServerAuthenticatedUsername($username, $serverVariable = 'REMOTE_USER')
    {
        $this
            ->getTestCaseClient()
            ->setServerParameter($serverVariable, $username);
    }

    /**
     * Set a user as having authenticated on a given firewall
     * 
     * The second strategy for setting a user on the session.
     * 
     * This one is to be used where a user will authenticate with a username and
     * password through a form. It sets a token on the session and a cookie so the
     * application will think this user has previously logged in.
     * 
     * Requires an `asUser` function to be defined on the application WebTestCase:
     * 
     * ```
     *     protected function asUser($username)
     *     {
     *         $user = $this->em->getRepository(User::class)->findOneByUsername($username);
     *         if ($user === null) {
     *             throw new \Exception(sprintf('User [%s] not found', $username));
     *         }
     *         $this->setSessionAuthenticatedUser($user);
     *     }
     * ```
     */
    protected function setSessionAuthenticatedUser(UserInterface $user, $firewall = 'main')
    {
        $session = static::getContainer()->get(SessionInterface::class);
        $cookieJar = $this->getTestCaseClient()->getCookieJar();

        $cookieJar->clear();
        $token = new UsernamePasswordToken($user, $firewall, $user->getRoles());
        $session->set('_security_' . $firewall, serialize($token));
        $session->save();

        $cookie = new Cookie($session->getName(), $session->getId());
        $cookieJar->set($cookie);
    }

    /**
     * Visit a route.
     */
    protected function visitRoute(string $route, array $parameters = [])
    {
        $url = $this->getUrl($route, $parameters);
        $this
            ->getSession()
            ->visit($url);
    }

    /**
     * Visit an url.
     */
    protected function visitUrl(string $url)
    {
        $this
            ->getSession()
            ->visit($url);
    }

    /**
     * Send a post request.
     *
     * @param string $route
     * @param array  $routeParameters   Parameters to build the route
     * @param array  $requestParameters Parameters to include in the body of the request (assoc array)
     */
    protected function sendPostRequest(string $route, array $routeParameters = [], array $requestParameters = [])
    {
        $url = $this->getUrl($route, $routeParameters);
        $this
            ->getTestCaseClient()
            ->request('POST', $url, $requestParameters, array());
    }

    /**
     * Fill in a hidden field.
     */
    protected function fillHiddenField(string $fieldId, string $value)
    {
        $this
            ->getPage()
            ->find('css', 'input[id="' . $fieldId . '"]')
            ->setValue($value);
    }

    /**
     * Submit a form.
     *
     * Call with something like `$this->submitForm('@name="name-of-the-form"');`
     * or change the xpath criteria to suit
     *
     * Still needed?
     */
    protected function submitForm(string $formPath)
    {
        $this
            ->getPage()
            ->find('xpath', 'descendant-or-self::form[' . $formPath . ']')
            ->submit();
    }

    /**
     * Follow client redirection once.
     */
    protected function followRedirect()
    {
        $this
            ->getTestCaseClient()
            ->followRedirect();
    }

    /**
     * Disable the automatic following of redirections.
     */
    protected function disableFollowRedirects()
    {
        $this
            ->getTestCaseClient()
            ->followRedirects(false);
    }

    /**
     * Restore the automatic following of redirections.
     */
    protected function restoreFollowRedirects()
    {
        $this
            ->getTestCaseClient()
            ->followRedirects(true);
    }

    /**
     * Get the URL for a given route and parameters.
     */
    protected function getUrl(string $route, array $parameters = []): string
    {
        $url = $this->getContainer()->get('router')->generate(
            $route,
            $parameters
        );

        return $url;
    }

    /**
     * @return KernelBrowser
     */
    protected function getTestCaseClient()
    {
        return $this
            ->getDriver()
            ->getClient();
    }

    /**
     * @return BrowserKitDriver
     */
    protected function getDriver()
    {
        return $this
            ->getSession()
            ->getDriver();
    }

    /**
     * Get the current Mink page.
     */
    protected function getPage()
    {
        return $this
            ->getSession()
            ->getPage();
    }

    /**
     * Use this in tests where profile collector is not enabled in web_profiler.yaml.
     * Add this just before the request is sent
     */
    protected function enableProfiler()
    {
        $this->getTestCaseClient()->enableProfiler();
    }
}
