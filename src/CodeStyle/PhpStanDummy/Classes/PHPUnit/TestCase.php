<?php

// dummy

namespace CubeTools\CubeCommonDevelop\CodeStyle\PhpStanDummy\Classes\PHPUnit;

abstract class TestCase
{
    // additionally defines the most used methods

    use assertsForTestCase;

    function any() {}

    static function exactly() {}

    function getCount() {}
    function getDataSetAsString($includeData = true) {}
    function getMockBuilder($className) {}
    function getName($withDataSet = true) {}

    static function markTestIncomplete($msg='') {}
    static function markTestSkipped($msg='') {}

    static function once() {}

    protected function setUp() {}
    static function setUpBeforeClass() {}

    protected function runTest() {}
    protected function tearDown() {}
    static function tearDownAfterClass() {}
}
