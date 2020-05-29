<?php

namespace Tests\CubeTools\CubeCommonDevelop\Test;

use CubeTools\CubeCommonDevelop\Test\MoreAssertionsTrait;

class MoreAssertionsTraitTest extends \PHPUnit\Framework\TestCase
{
    use MoreAssertionsTrait;

    public function testAssertArrayEmptyElementsOnly()
    {
        $array = array('', null, array(), 0, false);
        self::assertArrayEmptyElementsOnly($array);
    }

    public function testAssertArrayEmptyElementsOnlyFailing()
    {
        $e = null;
        $array = array(null, 3);
        try {
            self::assertArrayEmptyElementsOnly($array);
            $this->assertTrue(false, 'Assertion should not have passed');
        } catch (\PHPUnit\Framework\ExpectationFailedException $e) {
            // phpunit > v6
        } catch (\PHPUnit_Framework_ExpectationFailedException $e) {
            // phpunit < v6
        }
        $this->assertRegexp('/\W1 *=> *3\W/', (string) $e, 'correct element detected');
    }

    public function testAssertArrayNoEmptyElements()
    {
        $array = array('x', 1, array(''), true);
        $this->assertArrayNoEmptyElements($array);
    }

    public function testAssertArrayNoEmptyElementsFailing()
    {
        $e = null;
        $array = array(5, 'bla', array(null), false);
        try {
            self::assertArrayNoEmptyElements($array);
            $this->assertTrue(false, 'Assertion should not have passed');
        } catch (\PHPUnit\Framework\ExpectationFailedException $e) {
            // phpunit > v6
        } catch (\PHPUnit_Framework_ExpectationFailedException $e) {
            // phpunit < v6
        }
        $this->assertRegexp('/\W3 *=> *false\W/', (string) $e, 'correct element detected');
    }
}
