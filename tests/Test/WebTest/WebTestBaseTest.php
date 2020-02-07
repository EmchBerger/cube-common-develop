<?php

namespace Tests\CubeTools\CubeCommonDevelop\Test\WebTest;

use CubeTools\CubeCommonDevelop\Test\WebTest\WebTestBase;
use Symfony\Bundle\FrameworkBundle\Client;

class WebTestBaseTest extends \PHPUnit\Framework\TestCase
{
    use TestingWebTestBaseTrait;

    public function setUp()
    {
        $this->mockBaseClass();
    }

    public function testMsgUnexpectedRedirect()
    {
        $paramObj = (object) ['targetUrl' => 'tgt', 'requestUrl' => 'rq', 'flashBag' => []];
        $client = $this->getMockClient($paramObj);
        $msg = WebTestBase::msgUnexpectedRedirect($client);
        $this->assertSame("unexpected redirect (to 'tgt' from 'rq', flashbag: [])", $msg);

        $paramObj->flashBag = ['a' => 136, 'b' => 'x', 93 => ['x' => 7]];
        $msg2 = WebTestBase::msgUnexpectedRedirect($client);
        $this->assertNotSame($msg, $msg2, 'silly flashbag is shown'); // and no error has happened
    }

    private function getMockClient($paramObj)
    {
        $mClient = $this->getMockBuilder(Client::class)->setMethods(['getResponse', 'getRequest'])->getMock();

        $mResponse = $this->getMockBuilder('dummy\Response')->setMethods(['getTargetUrl'])->getMock();
        $mResponse->expects($this->any())->method('getTargetUrl')->willReturnCallback(function () use ($paramObj) {
            return $paramObj->targetUrl;
        });
        $mClient->expects($this->any())->method('getResponse')->willReturn($mResponse);

        $mBag = $this->getMockBuilder('dummy\Bag')->setMethods(['peekAll'])->getMock();
        $mBag->expects($this->any())->method('peekAll')->willReturnCallback(function () use ($paramObj) {
            return $paramObj->flashBag;
        });
        $mSession = $this->getMockBuilder('dummy\Session')->setMethods(['getFlashBag'])->getMock();
        $mSession->expects($this->any())->method('getFlashBag')->willReturn($mBag);
        $mRequest = $this->getMockBuilder('dummy\Request')->setMethods(['getRequestUri', 'getSession'])->getMock();
        $mRequest->expects($this->any())->method('getRequestUri')->willReturnCallback(function () use ($paramObj) {
            return $paramObj->requestUrl;
        });
        $mRequest->expects($this->any())->method('getSession')->willReturn($mSession);
        $mClient->expects($this->any())->method('getRequest')->willReturn($mRequest);

        return $mClient;
    }
}
