<?php

namespace CubeTools\CubeCommonDevelop\Test\Resources;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\Matcher\TraceableUrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;

class MatchingRoutesTest extends KernelTestCase
{
    private static $router;

    public static function setUpBeforeClass()
    {
        static::bootKernel();
        self::$router = self::$kernel->getContainer()->get('router');
    }

    public function testRoutesMatching()
    {
        $context = self::$router->getContext();
        $routes = self::$router->getRouteCollection();
        $failures = [];
        foreach ($routes as $name => $route) {
            $methods = $route->getMethods();
            if (!$methods) {
                $methods = ['get', 'post'];
            }
            foreach ($methods as $method) {
                $context->setMethod($method);
                $failed = $this->checkRoute($route, $context, $name);
                if ($failed) {
                    $failures = array_merge($failures, $failed);
                }
            }
        }
        if ($failures) {
            $msgP1 = sprintf('Routes failed (%d):', count($failures));
            $msgP2 = "\nfor details, run `bin/console debug:route ROUTENAME`".
                ' or `bin/console route:match --method=POST /MY/PATH`';
            $this->fail($msgP1."\n * ".implode("\n * ", $failures).$msgP2);
        }
        $this->assertTrue(true);
    }

    protected function checkRoute(Route $route, RequestContext $context, $routeName)
    {
        $routes = self::$router->getRouteCollection();
        $matcher = new TraceableUrlMatcher($routes, $context);
        $url = $this->getCheckPath($route, $routeName);
        $traces = $matcher->getTraces($url);
        $failures = [];
        $matches = false;
        $almost = [];
        foreach ($traces as $trace) {
            if (TraceableUrlMatcher::ROUTE_MATCHES === $trace['level']) {
                if ($routeName === $trace['name']) {
                    $matches = true;
                } elseif ($this->almostMatches($almost, $routeName)) {
                    $failures[] = $this->createFailure($context, $routeName, 'wrong match likely: '.$trace['name']);
                } else {
                    $failures[] = $this->createFailure($context, $routeName, 'wrong match: '.$trace['name']);
                }
            } elseif (TraceableUrlMatcher::ROUTE_ALMOST_MATCHES === $trace['level']) {
                $almost[] = $trace;
            }
        }
        if ($matches || $failures) {
            // passed or failed clearly
        } elseif ($this->almostMatches($almost, $routeName)) {
            // Requirements not fulfilled for only this route, accept as passed
        } elseif (1 < count($almost) && true) {
            $almostMatches = '';
            foreach ($almost as $trace) {
                $almostMatches .= sprintf(' "%s (%s)"', $trace['name'], $trace['path']);
            }
            $failures[] = $this->createFailure($context, $routeName, 'unclear match to any of'.$almostMatches);
        } else {
            $failures[] = $this->createFailure($context, $routeName, 'no match');
        }

        return $failures;
    }

    /*
     * Get the path to check for the route.
     *
     * This normally is the path with all parameters as is.
     * This can be adapted in subclasses to fix unclear matches.
     *
     * @param Route  $route
     * @param string $routeName
     *
     * @return string
     */
    protected function getCheckPath(Route $route, $routeName)
    {
        return $route->getPath();
    }

    protected function createFailure(RequestContext $context, $routeName, $problem)
    {
        return sprintf('%s (%s), %s', $routeName, $context->getMethod(), $problem);
    }

    protected function almostMatches(array $almost, $routeName)
    {
        return 1 === count($almost) && // only one almost match
            $routeName === $almost[0]['name'] && // is this route
            'Requirement ' === substr($almost[0]['log'], 0, 12) // Requirement is not fulfilled
        ;
    }
}
