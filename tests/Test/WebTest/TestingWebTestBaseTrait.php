<?php

namespace Tests\CubeTools\CubeCommonDevelop\Test\WebTest;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

trait TestingWebTestBaseTrait
{
    public function mockBaseClass()
    {
        if (!class_exists(WebTestCase::class)) {
            // this defines the mocked parent class, even when the object is not used
            $this->getMockBuilder(WebTestCase::class)->getMock();
        }
    }
}
