<?php

namespace Tests\CubeTools\CubeCommonDevelop\Test\WebTest;

use CubeTools\CubeCommonDevelop\Test\WebTest\SmoketestPageLoadingBase;

class SmoketestPageLoadingBaseTest extends \PHPUnit\Framework\TestCase
{
    use TestingWebTestBaseTrait;

    public function setUp()
    {
        $this->mockBaseClass();
    }

    public function testReplaceUrlParameter()
    {
        $rSmokeTestCls = new \ReflectionClass(SmoketestPageLoadingBase::class);
        $rReplaceUrlParameter = $rSmokeTestCls->getMethod('replaceUrlParameter');
        $rReplaceUrlParameter->setAccessible(true);

        $url = '/aaa/{id}/xx/{bla}/{cj}';
        $info = (object) array('urlParameters' => array('{cj}' => 'CCJJ'));
        $method = 'GET';
        $newUrl = $rReplaceUrlParameter->invoke(null, $url, $info, $method /*, $defaultReplace*/);
        $this->assertSame('/aaa/1/xx/{bla}/CCJJ', $newUrl);

        $method = 'DELETE';
        $url = 'xx/{id}/y';
        $newUrl = $rReplaceUrlParameter->invoke(null, $url, $info, $method);
        $this->assertSame('xx/99999/y', $newUrl);

        $url = '/abc/{eg}/{id}/defg/{gte}/{fie}';
        $method = 'GET';
        $defaultReplace = array('{id}' => 22, '{eg}' => 'EgG', '{fie}' => '');
        $newUrl = $rReplaceUrlParameter->invoke(null, $url, null, $method, $defaultReplace);
        $this->assertSame('/abc/EgG/22/defg/{gte}/', $newUrl);

        $url = '/i/{id}/u/{hk}/{fie}/i';
        $info = (object) array('urlParameters' => array('{id}' => '83', '{hk}' => 35));
        $newUrl = $rReplaceUrlParameter->invoke(null, $url, $info, $method, $defaultReplace);
        $this->assertSame('/i/83/u/35//i', $newUrl);
    }
}
