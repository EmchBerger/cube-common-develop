<?php

namespace CubeTools\CubeCommonDevelop\Test\WebTest;

use Doctrine\Bundle\DoctrineBundle\Command\Proxy\ValidateSchemaCommand;
use Symfony\Bundle\FrameworkBundle\Client;
// use Symfony\Component\Console\Application; // ValidateSchemaCommand expects the following class instead
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

class WebTestBase extends WebTestCase
{
    const WRONG_STATUS_CODE_MSG = 'WRONG status code';

    /**
     * @var array with url and method to check if connection works after login, can be set in subclass
     */
    protected static $connectionCheckUrl = ['method' => 'GET', 'url' => '/profile/'];

    /**
     * @var Client
     */
    private static $client;

    /**
     * @var bool
     */
    private static $conditionsChecked = false;

    /**
     * @var int counts tests to guess if system is probably working
     */
    private static $probablyWorking = 0;

    private $usesClient = false;

    /**
     * Clear the cached client for a clean start of the next test case class.
     *
     * This method is called after all test methods of this class have run.
     */
    public static function tearDownAfterClass()
    {
        if (self::$client) { // only when valid client
            self::$client = null;
        }
    }

    /**
     * Gets a client already logged in, cached for one test class.
     *
     * @return Client
     */
    public function getClient($newClient = false)
    {
        $this->usesClient = true;
        if (!self::$client) {
            if (self::$client === false) {
                static::markTestSkipped('client could not be loaded');

                return;
            }
            self::$client = false;
            $client = static::createClient();
            $container = $client->getContainer();
            if (!$container->hasParameter('test_user') && getenv('TEST_USER') && getenv('TEST_PWD')) {
                // configured by environment variables
                $serverParameter = [
                    'PHP_AUTH_USER' => getenv('TEST_USER'),
                    'PHP_AUTH_PW'   => getenv('TEST_PWD'),
                ];
            } else { // try reading from parameters
                try {
                    $serverParameter = [
                        'PHP_AUTH_USER' => $container->getParameter('test_user'),
                        'PHP_AUTH_PW'   => $container->getParameter('test_pwd'),
                    ];
                } catch (InvalidArgumentException $e) {
                    if (getenv('APP_ENV')) { // running with symfony/flex
                        $explain = ' (set parameters in service_test.yml or '.
                            'set variables TEST_USER and TEST_PWD in .env.test)';
                    } else {
                        $explain = ' (run "composer update-test-parameters")';
                    }
                    throw new InvalidArgumentException($e->getMessage().$explain, $e->getCode(), $e);
                }
            }
            $serverParameter['HTTP_HOST'] = $container->getParameter('router.request_context.host');
            $client->setServerParameters($serverParameter);
            self::$client = $client;
        } elseif ($newClient) {
            self::$client->restart();
        }
        static::prepareNextRequest();

        return self::$client;
    }

    /**
     * Prepares environment for next (test) request.
     *
     * Empties global $_GET variable.
     */
    public static function prepareNextRequest()
    {
        if ($_GET) {
            /* knp_paginator pas problem when it is set from a previous run
               (it sets it when only a default sorting is set, on the next run it does not match */
            $_GET = [];
        }
    }

    /**
     * Get a short explication why loading the page failed.
     *
     * @param \Symfony\Component\DomCrawler\Crawler $crawler
     *
     * @return string explication of failure
     */
    public static function getPageLoadingFailure($crawler, $testName, $msgIn = null)
    {
        $errTitle = 'UNKNOWN';
        $crTitle = $crawler->filter('div.text-exception h1');
        if (0 === count($crTitle)) {
            $crTitle = $crawler->filter('div.exception-message-wrapper h1'); // symfony >= 3.3
        }
        if (0 === count($crTitle)) {
            $crTitle = $crawler->filter('title'); // page title, in head
        }
        if (count($crTitle)) {
            $errTitle = trim($crTitle->text());
        }

        if (false !== strpos($errTitle, 'No route found for "')) {
            // no further explication, has no more details

            return $errTitle;
        }

        $i = strpos($errTitle, "\n");
        if ($i !== false) {
            $j = strrpos($errTitle, "\n");
            if ($i == $j) {
                $sep = ' \n ';
            } else {
                $sep = ' \n..\n ';
            }
            $errTitle = substr_replace($errTitle, $sep, $i, $j - $i + 1);
        }
        $msg = sprintf('%s; ', $errTitle);

        $file = '??'; // in case next line fails
        try {
            if (!self::$client) {
                throw new \Exception('no kernel yet');
            } elseif (!self::$client->getContainer()) {
                self::$client->getKernel()->boot();
            }
            $fileDir = self::$client->getContainer()->getParameter('kernel.cache_dir').'/tests_temp/';
            file_exists($fileDir) || mkdir($fileDir, 0700);
            if (count($crawler) > 0) {
                $fileName = strtr($testName, [
                    '/' => '_',
                    ':' => '_',
                    '\\' => '_',
                    '"' => '_',
                    ' with data set ' => '_',
                    '#' => 'no',
                ]);
                $file = $fileDir.$fileName.'.html';
                file_put_contents($file, $crawler->html());
                $msg .= sprintf('details see in %s)', $file);
            } else {
                $msg .= sprintf('no details because no html answer');
            }
        } catch (\Exception $e) {
            $msg .= sprintf('no details because writing %s failed, %s)', $file, $e);
        }
        if (false !== strpos($msg, ' command: wkhtmltopdf ')) {
            $msg = 'local problem with wkhtmltopdf'.substr($msg, strpos($msg, ';'));
        }
        if ('' === $msgIn) {
            $msgIn = static::WRONG_STATUS_CODE_MSG; // set default error message
        }
        if ($msgIn) {
            $msg = $msgIn.': '.$msg;
        }

        return $msg;
    }

    /**
     * @param Client $client
     *
     * @return string message with details about unexpected redirect
     */
    public static function msgUnexpectedRedirect(Client $client)
    {
        /* getResponse returns Symfony\Component\HttpFoundation\RedirectResponse,
               supporting ->getTargetUrl()
           getInternalResponse() returns Symfony\Component\BrowserKit\Response,
               supporting ->getHeader('Location') */
        $reqst = $client->getRequest();
        $toUrl = $client->getResponse()->getTargetUrl();
        $rqUrl = $reqst->getRequestUri();
        $flash = json_encode($reqst->getSession()->getFlashBag()->peekAll());

        return "unexpected redirect (to '$toUrl' from '$rqUrl', flashbag: $flash)";
    }

    protected function runTest()
    {
        /* do here because
              onNotSuccessfulTest() gets client deinitialized because after teardown
              teardown() can not modify the exception
        */
        try {
            $r = parent::runTest();
            if ($this->usesClient && $this->getCount()) {
                ++self::$probablyWorking;
            }

            return $r;
        } catch (\PHPUnit_Framework_SkippedTestError $e) {
            throw $e; // no check for skipped tests
        } catch (\PHPUnit\Framework\SkippedTestError $e) {
            throw $e; // no check for skipped tests
        } catch (\PHPUnit_Framework_IncompleteTestError $e) {
            throw $e; // no check for incomplete tests
        } catch (\PHPUnit\Framework\IncompleteTestError $e) {
            throw $e; // no check for incomplete tests
        } catch (\PHPUnit_Framework_RiskyTestError $e) {
            throw $e; // no check for more phpunit failures
        } catch (\PHPUnit\Framework\RiskyTestError $e) {
            throw $e; // no check for more phpunit failures
        } catch (\Exception $e) {
            $e = $this->maybeCheckFailureProblem($e);
            throw $e;
        }
    }

    final protected function maybeCheckFailureProblem(\Exception $e)
    {
        $doCheck = self::$client && !self::$conditionsChecked && $this->usesClient &&
            self::$probablyWorking < 24 && 'all' !== getenv('TestCaseDisableCheck') &&
            ('PHPUnit_Framework_ExpectationFailedException' !== get_class($e) ||
                false === strpos($e->getMessage(), 'local problem ') &&
                false === strpos($e->getMessage(), '_routes.yml')
            );
        if ($doCheck) {
            fwrite(STDOUT, "  checking local problems - after a failure\n");
            try {
                static::checkFailureProblem(self::$client, $e);

                self::$conditionsChecked = true; // passed, do not check on next failure
            } catch (\Exception $ex) {
                self::$client = false; // disable remaining tests
                fwrite(STDOUT, $ex->getMessage()."\n");
                $e = $ex; // replace exception
            }
            fwrite(STDOUT, "  checking done\n\n");
        }

        return $e;
    }

    /**
     * Check if there is probably a reason for the test failure.
     *
     * Checks if the login worked and the db is ok.
     * Is at most called once per run of phpunit.
     *
     * @param Client    $client
     * @param Exception $e      the exeption which failed the test
     *
     * @throws \Exception when a failure reason is detected (with the original exeption chained)
     */
    protected function checkFailureProblem(Client $client, \Exception $e)
    {
        static::checkClientConnection($client, $e);
        if ('dbCheck' === getenv('TestCaseDisableCheck')) {
            fwrite(STDOUT, "  dbCheck skipped\n");
        } else {
            static::checkDbMapping($client->getKernel(), $e);
        }
    }

    private static function checkDbMapping($kernel, \Exception $oldEx = null)
    {
        $application = new Application($kernel);
        $cmd = new ValidateSchemaCommand();
        $application->add($cmd);
        $cmdName = $cmd->getName();
        $output = new ConsoleOutput(); //writes output directly to console
        $input = new ArrayInput(['command' => $cmdName]);
        $r = $application->find($cmdName)->run($input, $output);
        if (0 !== $r) {
            throw new \Exception("doctrine mapping is wrong ($r)", $r, $oldEx);
        }
    }

    /**
     * Check if login works.
     */
    private static function checkClientConnection(Client $client, \Exception $oldEx = null)
    {
        $client->request(self::$connectionCheckUrl['method'], self::$connectionCheckUrl['url']);
        $r = $client->getResponse();
        if ($r->isRedirect()) { // hostname is in redirect, so "/login" does not work
            $msg = 'Abort WebTest*: login failed, check password and username in .env.test(.local) '.
                'or parameters_test.yml';
            throw new \Exception($msg, 0, $oldEx);
        }
        if ($r->getStatusCode() != 200) {
            $msg = self::getPageLoadingFailure($client->getCrawler(), 'loginError');
            throw new \Exception(sprintf(
                'Abort WebTest*: login failed, http status code %d; ',
                $r->getStatusCode()
            ).$msg, 0, $oldEx);
        }
    }
}
