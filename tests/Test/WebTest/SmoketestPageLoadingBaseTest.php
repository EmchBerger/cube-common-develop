<?php

namespace Tests\CubeTools\CubeCommonDevelop\Test\WebTest;

use CubeTools\CubeCommonDevelop\Test\WebTest\SmoketestPageLoadingBase;
use Symfony\Component\DomCrawler\Crawler;

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

    public function testMatchAnyOfWithNullCrawler()
    {
        $rSmokeTestCls = new \ReflectionClass(SmoketestPageLoadingBase::class);
        $rMatchAnyOf = $rSmokeTestCls->getMethod('matchAnyOf');
        $rMatchAnyOf->setAccessible(true);
        $testObj = $this->getMockBuilder(SmoketestPageLoadingBase::class)->setMethods(['getName'])->getMock();
        $testObj->expects($this->any())->method('getName')->will($this->returnValue('testingSmoketest'));
        $fnMatchAnyOf = function (array &$aw, array $anyOf) use ($rMatchAnyOf, $testObj) {
            $args = [&$aw, $anyOf];

            return $rMatchAnyOf->invokeArgs($testObj, $args);
        };

        $awIn = ['crawler' => null, 'msg' => 'a12', 'code' => 500];
        $aw = $awIn; // copy
        $anyOf = [];
        $matches = $fnMatchAnyOf($aw, $anyOf);
        $this->assertNull($matches, 'matches');
        $this->assertSame($awIn, $aw, 'aw');

        return ['fnMatchAnyOf' => $fnMatchAnyOf, 'awIn' => $awIn, 'anyOf' => $anyOf];
    }

    /**
     * @depends testMatchAnyOfWithNullCrawler
     */
    public function testMatchAnyOfWithTitle(array $dep1)
    {
        $awIn = $dep1['awIn'];
        $anyOf = $dep1['anyOf'];
        $fnMatchAnyOf = $dep1['fnMatchAnyOf'];

        $errTitle = 'error title '.random_int(1, 128);
        $awIn['crawler'] = new Crawler(null, 'https://cube.example.com/page');
        $awIn['crawler']->addHtmlContent('<html><head><title>'.$errTitle);
        $aw = $awIn;

        $matches = $fnMatchAnyOf($aw, $anyOf);
        $this->assertNull($matches, 'matches');
        $this->assertNotSame($awIn['msg'], $aw['msg'], 'msg');
        $this->assertContains($awIn['msg'], $aw['msg'], 'original msg');
        $this->assertContains($errTitle, $aw['msg'], 'title msg');

        $dep1['awIn'] = $awIn;

        return $dep1;
    }

    /**
     * @depends testMatchAnyOfWithTitle
     */
    public function testMatchAnyOfMatching(array $dep1)
    {
        $awIn = $dep1['awIn'];
        $fnMatchAnyOf = $dep1['fnMatchAnyOf'];

        $anyOf = ['xx' => ['code' => 9999], 'textOnly' => 'a12'];
        $aw = $awIn;

        $matches = $fnMatchAnyOf($aw, $anyOf);
        $this->assertContains('a12', $matches);
        $this->assertContains('textOnly', $matches);

        $anyOf = [
            'x' => ['msg' => 'x not match'],
            'mCode' => ['code' => 500],
        ];
        $matches = $fnMatchAnyOf($aw, $anyOf);
        $this->assertContains(500, $matches);
        $this->assertContains('mCode', $matches);

        $anyOf = [
            'msgWrong' => ['code' => 500, 'msg' => 'not matching x'],
            'codeWrong' => ['code' => 503, 'msg' => 'a12'],
            'codeMsg' => ['code' => 500, 'msg' => 'a12'],
            'tooLate' => ['code' => 500],
        ];
        $matches = $fnMatchAnyOf($aw, $anyOf);
        $this->assertContains(500, $matches);
        $this->assertContains('a12', $matches);
        $this->assertContains('codeMsg', $matches);
    }
}
