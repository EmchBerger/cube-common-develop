<?php

namespace CubeTools\CubeCommonDevelop\Test\WebTest;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;

/**
 * Smoke Test for pages not tested elsewhere.
 *
 * uses some files:
 *   PageLoadingTest_routes.yml, generated when running the test with environment variable PageLoading_Load=1
 *   PageLoadingTest_special.yml, settings for some urls
 * this files are next to the class or in tests/
 *
 * @group pageloading // not inherited, must be set in subclass
 */
class SmoketestPageLoadingBase extends WebTestBase
{
    const EXCEPTION_CODE = 'exception';

    /**
     * @dataProvider listUrls
     */
    public function testSimplePageLoading($method, $path, $info)
    {
        $aw = $this->loadPage($method, $path, $info);
        $this->checkRedirectAw($aw, $method, $path, $info);
        $aw['code'] = $this->passOrAnyOf($aw, $info);
        $this->throwIfException($aw);
        $this->assertEquals(Response::HTTP_OK, $aw['code'], $aw['msg']);
    }

    protected function loadPage($method, $path, $info)
    {
        if ($method == 'POST_ANY') {
            $method = 'POST';
        } elseif (!in_array($method, array('GET', 'DELETE', 'PATCH', 'POST', 'PUT'))) {
            $this->markTestIncomplete(sprintf('method %s not yet supported', $method));

            return;
        }

        $client = $this->getClient(true);
        $client->catchExceptions(false); // report exceptions directly
        $ex = null;
        $crawler = null;
        try {
            $crawler = $client->request($method, $path);
            $code = $client->getResponse()->getStatusCode();
            $msg = ''; // is set later when needed
        } catch (\Exception $ex) {
            $code = self::EXCEPTION_CODE;
            $msg = $ex->getMessage();
        } catch (\Throwable $ex) { // since php7
            $code = self::EXCEPTION_CODE;
            $msg = $ex->getMessage();
        }

        switch ($code) {
            case Response::HTTP_NOT_FOUND:
            case Response::HTTP_INTERNAL_SERVER_ERROR:
                $msg = $this->getPageLoadingFailure($crawler, $this->getName(), $msg);
                break;
        }

        return array('code' => $code, 'msg' => $msg, 'exception' => $ex, 'crawler' => $crawler);
    }

    protected function checkRedirectAw(array &$aw, $method, $path, $info)
    {
        $code = $aw['code'];
        $client = $this->getClient(false);

        if (Response::HTTP_OK === $code) {
            if (isset($info->redirect)) {
                $this->assertTrue(false, 'expected redirect to '.$info->redirect);
            }
        } elseif (self::EXCEPTION_CODE === $code) {
            // skip, is handled later
        } elseif ($client->getResponse()->isRedirect()) {
            $redirect = $client->getResponse()->getTargetUrl();
            if (isset($info->redirect)) {
                $this->checkRedirectTarget($client, $info, $redirect);
                $aw['code'] = Response::HTTP_OK; // set to pass
            } else {
                $aw['msg'] = static::msgUnexpectedRedirect($client);
            }
            if ('POST' !== $method && 'DELETE' !== $method) {
                // simply fail
            } elseif (false !== strpos($path, $redirect)) {
                // redirect to a parent URL after POST/DELETE
                $this->markTestSkipped("maybe {$aw['msg']}");
            } else {
                $this->markTestIncomplete($aw['msg']);
            }
        }
    }

    /**
     * @dataProvider listUrls
     */
    public function testSimplePageLoadingWithParameterUrl($method, $url, $info)
    {
        if (null === $method && null === $url && null === $info) {
            $this->markTestSkipped('OK, no loading with parameterUrl');
        }
        $url = $this->replaceUrlParameter($url, $info, $method);
        $aw = $this->loadPage($method, $url, $info);
        $this->checkRedirectAw($aw, $method, $url, $info);
        $aw['code'] = $this->passOrAnyOf($aw, $info);
        $this->throwIfException($aw);
        if ($aw['code'] == Response::HTTP_NOT_FOUND && (strpos($aw['msg'], 'entity') || strpos($aw['msg'], ' not found')) ||
            $aw['code'] == Response::HTTP_INTERNAL_SERVER_ERROR && strpos($aw['msg'], 'The file "') && strpos($aw['msg'], '" does not exist')
        ) {
            // id 1 does probably not exist locally, mark as skipped
            $this->markTestSkipped('missing resource locally - '.$aw['msg']);
        } elseif ($aw['code'] == Response::HTTP_FOUND && strpos($aw['msg'], 'flashbag: {"general-notice":["You are not allowed ') ||
            $aw['code'] == Response::HTTP_UNAUTHORIZED
        ) {
            // no rights for this
            $this->markTestSkipped('no access right on local resource - '.$aw['msg']);
        }
        $this->assertEquals(Response::HTTP_OK, $aw['code'], $aw['msg']);
    }

    /**
     * @dataProvider listUrls
     */
    public function testKnownProblem($method, $url, $info)
    {
        if (null === $method && null === $url && null === $info) {
            $this->markTestSkipped('OK, no known problem');
        }
        $url = $this->replaceUrlParameter($url, $info, $method);
        $aw = $this->loadPage($method, $url, $info);
        if ($aw['code'] !== Response::HTTP_OK) {
            $matched = $this->matchAnyOf($aw, $info->knownProblem);
            if (null === $matched) {
                // no match, fail
                $this->assertEquals(Response::HTTP_OK, $aw['code'], $aw['msg']);
            } else {
                // problem known and matches description
                $this->markTestSkipped('known problem ('.$matched['name'].'): '.$aw['msg']);
            }
        } else {
            $this->markTestIncomplete('PASSED, but marked as known problem');
        }
        $this->throwIfException($aw);
    }

    /**
     * @dataProvider listUrls
     */
    public function testExpectError($method, $url, $info)
    {
        if (null === $method && null === $url && null === $info) {
            $this->markTestSkipped('OK, no expected failure');

            return;
        }
        $url = $this->replaceUrlParameter($url, $info, $method);
        $aw = $this->loadPage($method, $url, $info);
        $matched = $this->matchAnyOf($aw, $info->expectError);
        if (null !== $matched) {
            // matches
            if (isset($matched['pass']) && $matched['pass']) {
                $this->assertTrue(true);
            } else {
                $this->markTestIncomplete('failed ('.$matched['name'].'): '.$aw['msg']);
            }
        } elseif (Response::HTTP_OK === $aw['code']) {
            $this->fail('should have an error, but passed');
        } else {
            $this->fail('failed with wrong error (with '.$aw['code'].' - '.$aw['msg'].')');
        }
    }

    /**
     * @dataProvider listUrls
     */
    public function testIgnore($method, $url, $info)
    {
        if (null === $method && null === $url && null === $info) {
            $this->markTestSkipped('OK, nothing ignored');
        }
        $this->markTestIncomplete($info->ignore);
    }

    /**
     * Generates the route data similar to 'app/console debug:route' but only urls interested in.
     *
     * @return array with route information
     */
    public static function &generateUrlData()
    {
        if (null === self::$kernel || null === self::$kernel->getContainer()) {
            // kernel not booted (properly)
            self::bootKernel();
        }
        $routes = self::$kernel->getContainer()->get('router')->getRouteCollection();
        $result = array();
        foreach ($routes as $name => $route) {
            // only use interesting urls, the rest is too much uninteresting data
            if (static::interestedInRoute($route)) {
                $infos = array('path' => $route->getPath(),
                    'methods' => $route->getMethods(),
                    'defaults' => $route->getDefaults(),
                );
                $result[$name] = $infos;
            }
        }
        ksort($result);

        return $result;
    }

    /**
     * Returns true when we are interested in this route (when route from AppBundle or App).
     *
     * Can be overwritten in a subclass to adapt to what is interesting in a project.
     *
     * @param Route $route
     *
     * @return bool true when interested in this route
     */
    public static function interestedInRoute(Route $route)
    {
        $controllerName = $route->getDefault('_controller');
        $topName = strtok($controllerName, ':\\.');

        return 'AppBundle' === $topName || 'App' === $topName;
    }

    /**
     * Loads the url data and its settings from disc (or generates the route data on demand).
     *
     * @return array with route informaton
     */
    public static function loadUrlData()
    {
        if (self::class === static::class) {
            $rPath = '../../../../../../tests/PageLoadingTest_routes.yml';
        } else {
            $clsRefl = new \ReflectionClass(static::class);
            $rPath = str_replace('.php', '_routes.yml', $clsRefl->getFileName());
        }
        $curUrls = static::generateUrlData();
        if (getenv('PageLoading_Load')) {
            if (file_put_contents($rPath, Yaml::dump($curUrls))) {
                $urls = $curUrls;
                echo " # routes file regenerated\n";
            } else {
                echo " # FAILED generating routes file\n";
            }
        } else {
            $newFound = false;
            if (file_exists($rPath)) {
                $urls = Yaml::parse(file_get_contents($rPath));
            } else {
                $urls = array();
                $todoUrl = array('defaults' => array('_controller' => ''), 'methods' => array('GET'));
                $todoUrl['path'] = 'TO'.'DO: update _routes.yml, see hint at top.';
                $curUrls = array_merge(array('TODO' => $todoUrl), $curUrls);
            }
            foreach ($curUrls as $name => $data) {
                if (!isset($urls[$name]) || $urls[$name] != $data) {
                    $urls[$name.' __new__'] = $data;
                    $newFound = true;
                }
            }
            if ($newFound) {
                echo "  ** run `PageLoading_Load=1 phpunit --filter=matchNoTest` to update $rPath\n\n";
            }
        }
        $sPath = str_replace('_routes.yml', '_special.yml', $rPath);
        if (file_exists($sPath)) {
            $specials = Yaml::parse(file_get_contents($sPath));
        } else {
            $specials = array();
        }
        $specialTests = isset($specials['tests']) ? array_fill_keys(array_keys($specials['tests']), true) : array();
        foreach ($urls as $name => &$data) {
            $special = isset($specials['tests'][$name]) ? $specials['tests'][$name] : array();
            $data['testType'] = static::determineTestType($special, $data);
            $data['testSpecial'] = $special;
            unset($specialTests[$name]);
        }

        if ($specialTests) {
            echo "  ** warning, unexisting routes in $sPath: ".implode(', ', array_keys($specialTests))."\n\n";
        }

        return $urls;
    }

    protected static $urlData = null;

    /**
     * data provider
     *
     * @param string $testMethodName name of test method the dataprovider is called for
     */
    public static function listUrls($testMethodName)
    {
        if (static::$urlData === null) {
            $ex = null;
            try {
                static::$urlData = static::loadUrlData();
            } catch (\Exception $ex) {
                // handled below
            } catch (\Throwable $ex) { // php 7+
               // handled below
            }
            if ($ex) {
                $err = sprintf("  Exception %s in %s(), %s\n", get_class($ex), __FUNCTION__, $ex->getMessage());
                fwrite(STDERR, $err); // because generator hides error
                static::$urlData = []; // cache failure for next test method
                throw $ex;
            }
        }
        $i = 0;
        foreach (static::$urlData as $name => $data) {
            if ($data['testType'] == $testMethodName) {
                $info = $data['testSpecial'];
                $info['controller'] = $data['defaults']['_controller'];
                $methods = $data['methods'];
                if (empty($methods)) {
                    $methods = array('GET', 'POST_ANY');
                    $c = 2;
                } else {
                    $c = count($methods);
                }
                foreach ($methods as $method) {
                    if (1 === $c || 'GET' === $method) {
                        $mName = $name;
                    } else {
                        $mName = "${name}_${method}";
                    }
                    yield $mName => array(
                        'method' => $method,
                        'path'   => $data['path'],
                        'info'   => (object) $info,
                    );
                }
                ++$i;
            }
        }
        if (0 === $i && 'testSimplePageLoading' !== $testMethodName) {
            // would report an error when no tests returned
            yield 'skip' => array(null, null, null); // static::markTestSkipped does not yet work with phpunit 3.7.28
        }
    }

    public function getSize()
    {
        // overwrite method in TestCase, instead of using annotations @small, @medium, @large everywhere
        if (class_exists(\PHPUnit\Util\Test::class)) {
            return \PHPUnit\Util\Test::LARGE;
        } else {
            return \PHPUnit_Util_Test::LARGE;
        }
    }

    /**
     * To overwrite in subclass when unknown parameters should be skipped.
     *
     * TODO remove if not used anymore.
     *
     * @return bool false
     */
    protected static function skipUnknownRouteParameters()
    {
        return false;
    }

    public function getDataSetAsString($includeData = true)
    {
        $buffer = parent::getDataSetAsString($includeData);
        if ($includeData && $buffer) {
            $pos = strpos($buffer, ', stdClass');
            if ($pos !== false) {
                $buffer = substr_replace($buffer, ', ..)', $pos);
            }
        }

        return $buffer;
    }

    public function getActualOutput()
    {
        // overwrite parent function to shorten shown output

        $output = parent::getActualOutput();
        $len = strlen($output);
        if ($len > 64) {
            $output = substr($output, 0, 32)."...(c=$len)";
        }

        return $output;
    }

    protected static function replaceUrlParameter($url, $info, $method, array $defaultReplace = array())
    {
        $nr = 1;
        if ('DELETE' === $method) {
            $nr = 99999; // probably non existing
        }
        $replace = array('{id}' => $nr);
        if (isset($info->urlParameters)) {
            $replace = array_merge($replace, $defaultReplace, $info->urlParameters);
        } elseif ($defaultReplace) {
            $replace = array_merge($replace, $defaultReplace);
        }
        $url = strtr($url, $replace);
        if (static::skipUnknownRouteParameters() && strpos($url, '{')) {
            static::markTestIncomplete('skipped non-id parameter in url');
        }

        return $url;
    }

    /**
     * Checks if error (msg and code in $aw) match any of the given variants.
     *
     * @param mixed[] $aw    answer from loadPage()
     * @param mixed[] $anyOf ['msg' => string, 'code' => int, ...], msg or code must be present)
     *
     * @return null|array matching element of $anyOf (with ['name'] set to key), null else
     */
    private function matchAnyOf(array &$aw, array $anyOf)
    {
        if (null !== $aw['crawler']) {
            $aw['msg'] = static::getPageLoadingFailure($aw['crawler'], $this->getName(), $aw['msg']); // also updates parameter $aw
        }
        $msg = $aw['msg'];
        $code = $aw['code'];
        $anyCodeMatched = false;
        foreach ($anyOf as $name => $any) {
            if (is_string($any)) {
                $match = $any;
                $any = array('msg' => $match);
            } elseif (isset($any['code']) && $code != $any['code']) {
                continue; // code did not match
            } elseif (!isset($any['msg']) && isset($any['code'])) {
                $match = ''; // will match
            } elseif (!isset($any['msg'])) {
                trigger_error("'msg' or 'code' must be set, missing in ".$name, E_USER_WARNING);
                continue;
            } else {
                $match = $any['msg'];
            }
            $anyCodeMatched = true;
            try {
                if (preg_match('~'.$match.'~', $msg)) {
                    $any['name'] = $name;

                    return $any;
                }
            } catch (\PHPUnit_Framework_Error_Warning $w) {
                throw new \Exception('Invalid "'.$name.'.msg" in _special.yml, must be a pattern. '.$w->getMessage());
            } catch (\PHPUnit\Framework\Error\Warning $w) {
                throw new \Exception('Invalid "'.$name.'.msg" in _special.yml, must be a pattern. '.$w->getMessage());
            }
        }
        if ($anyCodeMatched) { // remove "code" from message, gives some hint
            $newStatus = trim(strtr(static::WRONG_STATUS_CODE_MSG, ['code' => '']));
            $aw['msg'] = strtr($aw['msg'], [static::WRONG_STATUS_CODE_MSG => $newStatus]);
        }

        return null;
    }

    /**
     * Checks if redirect is to expected target.
     *
     * @param Symfony/Component/BrowserKit/Client $client
     * @param mixed[] $info
     * @param string $redirect url redirected to
     */
    private function checkRedirectTarget($client, $info, $redirect)
    {
        if ($client->getResponse()->isRedirect($info->redirect)) {
            $this->assertTrue(true);
        } else {
            $this->assertTrue(false, 'redirect to '.$redirect.' instead of '.$info->redirect);
        }
    }

    /**
     * Throws a cached exception.
     *
     * @param mixed[] $aw with elements [ 'code' => str|int, 'msg' => str|\Exception ]
     *
     * @throws \Exception cached exception
     */
    private function throwIfException(array $aw)
    {
        if (self::EXCEPTION_CODE === $aw['code']) {
            throw $aw['exception'];
        }
    }

    /**
     * Checks the configuration "passorAnyOf".
     *
     * @param mixed[] $aw   answer from loadPage()
     * @param mixed[] $info
     *
     * @return int code for into $aw
     */
    private function passOrAnyOf(array &$aw, $info)
    {
        $code = $aw['code'];
        if (Response::HTTP_OK === $code) {
            // fine
        } elseif (isset($info->passOrAnyOf)) {
            $matched = $this->matchAnyOf($aw, $info->passOrAnyOf);
            $msg = $aw['msg'];
            if (null === $matched) {
                // no match, will fail
            } elseif (isset($matched['pass']) && $matched['pass']) {
                $code = Response::HTTP_OK; // set to pass
            } elseif (isset($matched['msg']) && '.' === $matched['msg']) {
                $this->markTestSkipped('failure matched ('.$matched['name'].'): '.$msg);
            } else {
                $this->markTestIncomplete('failure matched ('.$matched['name'].'): '.$msg);
            }
        } else {
            $this->matchAnyOf($aw, []); // call to set $aw['msg']
        }

        return $code;
    }

    private static function determineTestType(array $special, array $urlData)
    {
        $type = '';
        if (empty($special)) {
            // nothing, quick path
        } elseif (isset($special['tested'])) {
            $type = '_tested';
        } elseif (isset($special['ignore'])) {
            $type = 'testIgnore';
        } elseif (isset($special['knownProblem'])) {
            $type = 'testKnownProblem';
        } elseif (isset($special['expectError'])) {
            $type = 'testExpectError';
        }
        if ($type != '') {
            // type already set
        } elseif (strpos($urlData['path'], '{')) {
            $type = 'testSimplePageLoadingWithParameterUrl';
        } else {
            $type = 'testSimplePageLoading';
        }

        return $type;
    }
}
