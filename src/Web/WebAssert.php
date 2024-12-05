<?php

namespace IFix\Testing\Web;

use Behat\Mink\Session;
use Behat\Mink\Exception\ExpectationException;
use Behat\Mink\Driver\BrowserKitDriver;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\Mink\WebAssert as BaseWebAssert;
use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\Mailer\DataCollector\MessageDataCollector;
use IFix\Testing\Assert as BaseAssert;
use Symfony\Component\HttpKernel\DataCollector\LoggerDataCollector;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

/**
 * Provide a bridge between Minks WebAssert and PHPUnits Assert strategies.
 *
 * Also, provide other generic assertions for use in a web context.
 */
class WebAssert extends BaseAssert
{
    protected Session $session;
    protected BaseWebAssert $minkWebAssert;

    public function __construct(Session $session)
    {
        $this->session = $session;
        $this->minkWebAssert = new BaseWebAssert($session);
    }

    /**
     * Hand off all other calls to the mink web assert. Passes Mink ExpectationExceptions
     * to PHPUnits assert library so assertions are counted, and result in failures instead
     * of errors.
     *
     * See https://gist.github.com/shashikantjagtap/3c25990235e7c343de42 
     * and http://www.bbc.co.uk/blogs/internet/entries/79fd4cb1-621e-4a7c-923d-08df8956c675
     */
    public function __call($name, $args)
    {
        if (!is_callable([$this->minkWebAssert, $name])) {
            throw new \BadMethodCallException(sprintf(
                'Tried to call "%s" on WebAssert but that method does not exist',
                $name
            ));
        }

        $success = true;
        $failMessage = '';

        try {
            $return = call_user_func_array(array($this->minkWebAssert, $name), $args);
        } catch (ExpectationException $e) {
            $success = false;
            $failMessage = $e->getMessage();
        }

        $this->assert($success, $failMessage);

        return $return;
    }

    /**
     * Assert the current page corresponds to the given route and optional
     * parameters.
     */
    public function currentRoute(string $route, array $parameters = [])
    {
        $url = $this->getUrl($route, $parameters);
        $this->addressEquals($url);
        $this->statusCodeEquals(200);
    }

    /**
     * Assert an email was sent to a given address.
     *
     * Requires profile to be collected in web_profiler.yaml or enableProfiler
     * called prior to the request. Also, don't forget to disable redirects if
     * the email is sent during an action that redirects.
     * 
     * @param string $email Address the email expected to be sent to
     */
    public function emailSentTo(string $email, string $type = 'to')
    {
        // if (getenv('SKIP_PROFILER')) {
        //     return;
        // }

        $this->assert($this->isEmailInProfiler($email, $type) === true, sprintf(
            'No email sent to %s',
            $email
        ));
    }

    /**
     * Assert an email was NOT sent to a given address.
     *
     * Requires profile to be collected in web_profiler.yaml or enableProfiler
     * called prior to the request. Also, don't forget to disable redirects if
     * the email is sent during an action that redirects.
     * 
     * @param string $email Address the email expected not to be sent to
     */
    public function emailNotSentTo(string $email, string $type = 'to')
    {
        // if (getenv('SKIP_PROFILER')) {
        //     return;
        // }

        $this->assert($this->isEmailInProfiler($email, $type) === false, sprintf(
            'Email was sent to %s',
            $email
        ));
    }

    private function isEmailInProfiler(string $email, string $type = 'to'): bool
    {
        switch ($type) {
            case 'to':
                $messageFunction = 'getTo';
                break;
            case 'cc':
                $messageFunction = 'getCc';
                break;
            case 'bcc':
                $messageFunction = 'getBcc';
                break;
        }

        /** @var TemplatedEmail[] */
        $messages = $this->getMessageDataCollector()->getEvents()->getMessages();

        $sent = false;
        foreach ($messages as $message) {
            foreach ($message->$messageFunction() as $messageAddress) {
                if ($messageAddress->getAddress() === $email) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Assert the maximum number of queries has not been exceeded.
     */
    public function maxQueryCount(int $maxCount)
    {
        $actualCount = $this->getDoctrineDataCollector()->getQueryCount();

        $this->assert($actualCount <= $maxCount, sprintf(
            'Too many database queries - expected no more than %d, got %d',
            $maxCount,
            $actualCount
        ));
    }

    /**
     * Assert a message was logged to the logger.
     *
     * Requires profile to be collected in web_profiler.yaml or enableProfiler
     * called prior to the request. Also, don't forget to disable redirects if
     * the message is logged during an action that redirects.
     * 
     * @param string $message   The message logged
     * @param string $priority  The expected priority of the message ('error', 'critical', etc)
     */
    public function messageLogged(string $message, ?string $priority = null)
    {
        // Logger data collector is always empty, not sure why, but this works to assert
        // on the log message getting recorded, use it at the top of the test before a request:
        //
        // $loggerMock = $this->createMock(LoggerInterface::class);
        // $loggerMock->expects($this->once())
        //     ->method('critical')
        //     ->with("Some message with a \"{$var}\" blah")
        // ;
        // static::getContainer()->set(LoggerInterface::class, $loggerMock);

        throw new \RuntimeException('logger data collection is a problem, see note in messageLogged');

        $logs = $this->getLoggerDataCollector()->getLogs();

        $logged = false;
        foreach ($logs as $log) {
            $messageLower = strtolower($message);
            // dump($log);
            if (strtolower($log['message']) === $messageLower) {
                $logged = true;
                break;
            }
        }

        $this->assert($logged, sprintf(
            'No message logged "%s"',
            $message
        ));
    }

    /**
     * Assert the elements contain the expected values.
     *
     * Not tested
     */
    public function elementsContainingInOrder(string $selector, array $expectedValues)
    {
        //get the elements
        $actualElements = $this->session->getPage()->findAll('css', $selector);

        //first check the elements count matches the table rows
        if (count($actualElements) !== count($expectedValues)) {
            throw new \Exception(sprintf(
                'Expecting %d elements matching "%s", got %d',
                count($expectedValues),
                $selector,
                count($actualElements)
            ));
        }

        //now iterate all the elements, and ensure they contain what is expected
        for ($index = 0; $index < count($actualElements); ++$index) {
            $row = $expectedValues[$index];
            $text = $row[0];

            $element = $actualElements[$index];

            $regex = '/' . preg_quote($text, '/') . '/ui';
            $actual = $element->getText();

            $this->assert(preg_match($regex, $actual), sprintf(
                'The text "%s" was not found in the text of the element matching "%s" at index %d, got "%s".',
                $text,
                $selector,
                $index,
                $element->getText()
            ));
        }
    }

    /**
     * Assert the table row contains the expected values.
     *
     * Borrowed and adapted from Behatch\TableContext
     * 
     * @see     https://github.com/Behatch/contexts/blob/master/src/Context/TableContext.php
     *
     * @param string $table    Selector for the table (#myTableId)
     * @param int    $rowIndex Row number (1 based)
     * @param array  $expected An assoc array where the key identifies the column:
     *                         [
     *                             'col1' => 'Some Value', 
     *                             'col9' => 'Something in the 9th <td>',
     *                         ]
     */
    public function tableRowMatches(string $table, int $rowIndex, array $expected)
    {
        $rowsSelector = "$table tbody tr";
        $rows = $this->session->getPage()->findAll('css', $rowsSelector);

        if (!isset($rows[$rowIndex - 1])) {
            throw new \Exception("The row $rowIndex was not found in the '$table' table");
        }

        $cells = (array) $rows[$rowIndex - 1]->findAll('css', 'td');
        $cells = array_merge((array) $rows[$rowIndex - 1]->findAll('css', 'th'), $cells);

        foreach (array_keys($expected) as $columnName) {
            // Extract index from column. ex "col2" -> 2
            preg_match('/^col(?P<index>\d+)$/', $columnName, $matches);
            $cellIndex = (int) $matches['index'] - 1;

            $this->assert($expected[$columnName] == $cells[$cellIndex]->getText(), sprintf(
                'Table "%s", row "%d", cell "%s" - expected "%s", got "%s"',
                $table,
                $rowIndex,
                $columnName,
                $expected[$columnName],
                $cells[$cellIndex]->getText()
            ));
        }
    }

    /**
     * Assert the page contains a link, and optionally assert the href of the link 
     * matches the route.
     * 
     * @param string $linkText
     * @param string $route
     * @param array  $routeParameters
     */
    public function hasLink(string $linkText, ?string $route = null, array $routeParameters = [])
    {
        $node = $this->elementExists('xpath', '//a[contains(text(), \'' . $linkText . '\')]');

        if ($route !== null) {
            $expectedUrl = $this->getUrl($route, $routeParameters);
            $actualUrl = $node->getAttribute('href');

            $this->assert($actualUrl === $expectedUrl, sprintf(
                "Expected link containing '%s' to have href '%s', got '%s'",
                $linkText,
                $expectedUrl,
                $actualUrl
            ));
        }
    }

    /**
     * Assert the page does not have a link containing the given text.
     */
    public function hasNotLink(string $linkText)
    {
        $this->elementNotExists('xpath', '//a[contains(text(), \'' . $linkText . '\')]');
    }

    /**
     * Assert the select has an expected option.
     */
    public function selectContainsOption(string $select, string $option)
    {
        $this->assert($this->checkSelectContainsOption($select, $option) === true, sprintf(
            'Select "%s" does not contain option "%s"',
            $select,
            $option
        ));
    }

    /**
     * Assert the select does not have an option.
     */
    public function selectNotContainsOption(string $select, string $option)
    {
        $this->assert($this->checkSelectContainsOption($select, $option) === false, sprintf(
            'Select "%s" does contain option "%s"',
            $select,
            $option
        ));
    }

    /**
     * Check if a select has an option.
     */
    protected function checkSelectContainsOption(string $select, string $option): bool
    {
        $obj = $this->session->getPage()->findField($select);
        if ($obj === null) {
            throw new \Exception(sprintf(
                'Select box "%s" not found',
                $select
            ));
        }
        $optionText = $obj->getText();

        $regex = '/' . preg_quote($option, '/') . '/ui';

        return preg_match($regex, $optionText) > 0;
    }

    /**
     * Get the container.
     */
    protected function getContainer()
    {
        return $this->getClient()->getContainer();
    }

    /**
     * @return \Symfony\Bundle\FrameworkBundle\Client
     */
    protected function getClient()
    {
        return $this->getSymfonyDriver()->getClient();
    }

    /**
     * Get the Symfony driver.
     * 
     * @throws UnsupportedDriverActionException when not using mink browser kit driver
     */
    protected function getSymfonyDriver(): BrowserKitDriver
    {
        $driver = $this->session->getDriver();
        if ($driver instanceof BrowserKitDriver === false) {
            throw new UnsupportedDriverActionException(
                'Not using the Symfony Driver - current driver is %s',
                $driver
            );
        }

        return $driver;
    }

    /**
     * Resolve the URL for a given route and optional parameters.
     * 
     * @param string $route
     * @param array  $parameters
     *
     * @return string
     */
    protected function getUrl(string $route, array $parameters = [])
    {
        $url = $this->getContainer()->get('router')->generate(
            str_replace(' ', '_', $route),
            $parameters
        );

        return $url;
    }

    /**
     * Get the symfony profiler.
     * 
     * @throws \RuntimeException when profiler is disabled
     */
    private function getSymfonyProfile(): Profile
    {
        $profile = $this->getClient()->getProfile();

        if ($profile === null) {
            throw new \RuntimeException(
                'Profiler is disabled. Set framework:profiler:collect to true in config_test.yml'
            );
        }

        return $profile;
    }

    private function getLoggerDataCollector(): LoggerDataCollector
    {
        return $this->getSymfonyProfile()->getCollector('logger');
    }

    private function getDoctrineDataCollector(): DoctrineDataCollector
    {
        return $this->getSymfonyProfile()->getCollector('db');
    }

    private function getMessageDataCollector(): MessageDataCollector
    {
        return $this->getSymfonyProfile()->getCollector('mailer');
    }
}
