<?php

namespace Tests\CubeTools\CubeCommonDevelop\Test\WebTest;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

trait TestingWebTestBaseTrait
{
    public function mockBaseClass()
    {
        if (!class_exists(WebTestCase::class)) {
            // simply alias the class to an ancestor (parent of parent)
            class_alias(TestCase::class, WebTestCase::class);
        }
    }
}
