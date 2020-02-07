<?php

namespace Tests\CubeTools\CubeCommonDevelop\Test;

use CubeTools\CubeCommonDevelop\Test\MoreAssertionsTrait;

class MoreAssertionsTraitTest extends \PHPUnit\Framework\TestCase
{
    use MoreAssertionsTrait;

    public function testAssertArrayEmptyElementsOnly()
    {
        $array = ['', null, [], 0, false];
        MoreAssertionsTrait::assertArrayEmptyElementsOnly($array);
    }

    public function testAssertArrayEmptyElementsOnlyFailing()
    {
        $e = null;
        $array = [null, 3];
        try {
            MoreAssertionsTrait::assertArrayEmptyElementsOnly($array);
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
        $array = ['x', 1, [''], true];
        $this->assertArrayNoEmptyElements($array);
    }

    public function testAssertArrayNoEmptyElementsFailing()
    {
        $e = null;
        $array = [5, 'bla', [null], false];
        try {
            MoreAssertionsTrait::assertArrayNoEmptyElements($array);
            $this->assertTrue(false, 'Assertion should not have passed');
        } catch (\PHPUnit\Framework\ExpectationFailedException $e) {
            // phpunit > v6
        } catch (\PHPUnit_Framework_ExpectationFailedException $e) {
            // phpunit < v6
        }
        $this->assertRegexp('/\W3 *=> *false\W/', (string) $e, 'correct element detected');
    }
}
