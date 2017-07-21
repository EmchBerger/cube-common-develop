<?php

namespace CubeTools\CubeCommonDevelop\Test;

use PHPUnit\Framework\TestCase;

/**
 * Some assertions for TestCases.
 *
 * @author Simon Heimberg
 */
trait MoreAssertionsTrait
{
    /**
     * Asserts the array contains only empty elements.
     *
     * Empty values: {@see empty()}
     *
     * An empty array will pass.
     *
     * @param array  $array
     * @param string $explication
     */
    public static function assertArrayEmptyElementsOnly(array $array, $explication = null)
    {
        if (null === $explication) {
            $explication = 'only empty elements';
        }
        $isNotEmpty = function ($element) {
            return !empty($element);
        };
        TestCase::assertSame(array(), array_filter($array, $isNotEmpty), $explication);
    }

    /**
     * Asserts the array contains no empty elements.
     *
     * Empty values: {@see empty()}
     *
     * An empty array will pass.
     *
     * @param array  $array
     * @param string $explication
     */
    public static function assertArrayNoEmptyElements(array $array, $explication = null)
    {
        if (null === $explication) {
            $explication = 'no empty elements';
        }
        $isEmpty = function ($element) {
            return empty($element);
        };
        TestCase::assertSame(array(), array_filter($array, $isEmpty), $explication);
    }
}
