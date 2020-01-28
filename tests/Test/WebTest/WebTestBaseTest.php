<?php

namespace Tests\CubeTools\CubeCommonDevelop\Test\WebTest;

use CubeTools\CubeCommonDevelop\Test\WebTest\WebTestBase;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Component\DomCrawler\Crawler;

class WebTestBaseTest extends \PHPUnit\Framework\TestCase
{
    use TestingWebTestBaseTrait;

    public function setUp()
    {
        $this->mockBaseClass();
    }

    public function testMsgUnexpectedRedirect()
    {
        $paramObj = (object) array('targetUrl' => 'tgt', 'requestUrl' => 'rq', 'flashBag' => array());
        $client = $this->getMockClient($paramObj);
        $msg = WebTestBase::msgUnexpectedRedirect($client);
        $this->assertSame("unexpected redirect (to 'tgt' from 'rq', flashbag: [])", $msg);

        $paramObj->flashBag = array('a' => 136, 'b' => 'x', 93 => array('x' => 7));
        $msg2 = WebTestBase::msgUnexpectedRedirect($client);
        $this->assertNotSame($msg, $msg2, 'silly flashbag is shown'); // and no error has happened
    }

    public function testGetPageLoadingFailureHtmlEmpty()
    {
        $crawler = new Crawler(null, 'https://cube.example.com/page');
        $testName = __FUNCTION__;

        $failMsg = WebTestBase::getPageLoadingFailure($crawler, $testName);

        $this->assertContains('UNKNOWN', $failMsg);
        $this->assertNotContains('WRONG', $failMsg, 'NO default error reason');

        $failMsg = WebTestBase::getPageLoadingFailure($crawler, $testName, '');
        $this->assertContains('UNKNOWN', $failMsg);
        $this->assertContains('WRONG', $failMsg, 'default error reason');

        $originalMessage = 'original message';
        $failMsg = WebTestBase::getPageLoadingFailure($crawler, $testName, $originalMessage);
        $this->assertContains('UNKNOWN', $failMsg);
        $this->assertNotContains('WRONG', $failMsg, 'NO default error reason');
        $this->assertContains($originalMessage, $failMsg, 'original message');
    }

    public function testGetPageLoadingFailureHtmlTitle()
    {
        $crawler = new Crawler(null, 'https://cube.example.com/page');
        $testName = __FUNCTION__;

        $errTitle = 'err title '.random_int(1, 128);
        $crawler->addHtmlContent('<html><head><title>'.$errTitle);

        $failMsg = WebTestBase::getPageLoadingFailure($crawler, $testName);

        $this->assertContains($errTitle, $failMsg, 'title msg');

        $errHeading = 'error in h element '.random_int(1, 128);
        $crawler->clear();
        $crawler->addHtmlContent('<html><head><title>err title</title></head><body><div class=exception-message-wrapper><h1>'.$errHeading);

        $failMsg = WebTestBase::getPageLoadingFailure($crawler, $testName);

        $this->assertContains($errHeading, $failMsg, 'heading msg');
    }

    private function getMockClient($paramObj)
    {
        $mClient = $this->getMockBuilder(Client::class)->setMethods(array('getResponse', 'getRequest'))->getMock();

        $mResponse = $this->getMockBuilder('dummy\Response')->setMethods(array('getTargetUrl'))->getMock();
        $mResponse->expects($this->any())->method('getTargetUrl')->willReturnCallback(function () use ($paramObj) {
            return $paramObj->targetUrl;
        });
        $mClient->expects($this->any())->method('getResponse')->willReturn($mResponse);

        $mBag = $this->getMockBuilder('dummy\Bag')->setMethods(array('peekAll'))->getMock();
        $mBag->expects($this->any())->method('peekAll')->willReturnCallback(function () use ($paramObj) {
            return $paramObj->flashBag;
        });
        $mSession = $this->getMockBuilder('dummy\Session')->setMethods(array('getFlashBag'))->getMock();
        $mSession->expects($this->any())->method('getFlashBag')->willReturn($mBag);
        $mRequest = $this->getMockBuilder('dummy\Request')->setMethods(array('getRequestUri', 'getSession'))->getMock();
        $mRequest->expects($this->any())->method('getRequestUri')->willReturnCallback(function () use ($paramObj) {
            return $paramObj->requestUrl;
        });
        $mRequest->expects($this->any())->method('getSession')->willReturn($mSession);
        $mClient->expects($this->any())->method('getRequest')->willReturn($mRequest);

        return $mClient;
    }
}
