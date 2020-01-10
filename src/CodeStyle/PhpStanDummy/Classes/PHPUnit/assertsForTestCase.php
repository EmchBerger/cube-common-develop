<?php

// dummy

namespace CubeTools\CubeCommonDevelop\CodeStyle\PhpStanDummy\Classes\PHPUnit;

trait assertsForTestCase
{
    static function assertArrayHasKey($key, $array, $msg='') {}
    static function assertArrayNotHasKey($key, $array, $msg='') {}
    static function assertCount($nr, $iter, $msg='') {}
    static function assertContains($el, $container, $msg='') {}
    static function assertEmpty($val, $msg='') {}
    static function assertEquals($val1, $val2, $msg='') {}
    static function assertFalse($val, $msg='') {}
    static function assertGreaterThan($val, $msg='') {}
    static function assertGreaterThanOrEqual($val, $msg='') {}
    static function assertInstanceOf($cls, $val, $msg='') {}
    static function assertInternalType($val, $msg='') {}
    static function assertNotContains($val, $msg='') {}
    static function assertNotEmpty($val, $msg='') {}
    static function assertNotEquals($val1, $val2, $msg='') {}
    static function assertNotSame($val1, $val2, $msg='') {}
    static function assertNull($val, $msg='') {}
    static function assertSame($val1, $val2, $msg='') {}
    static function assertRegExp($reg, $sgr, $msg='') {}
    static function assertTrue($val, $msg='') {}

    static function fail($msg='') {}

    function expectException($cls) {}
    function expectExceptionCode($c) {}
    function expectExceptionMessage($m) {}
    function expectExceptionObject(\Exception $o) {}
}
