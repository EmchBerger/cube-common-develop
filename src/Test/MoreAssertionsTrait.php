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

    /**
     * Asserts the array contains only filled elements.
     *
     * Filled elements are values returned when a form field cot some value.
     * Not filled: null (Entities, Selections), '' (Text), [] (MultiSelect)
     *
     * An empty array will pass.
     *
     * @param array  $array
     * @param string $explication
     */
    public static function assertArrayFilledElementsOnly(array $array, $explication = null)
    {
        if (null === $explication) {
            $explication = 'only filled elements';
        }
        $isNotFilled = function ($element) {
            return '' !== $element && count($element) > 0;
        };
        TestCase::assertSame(array(), array_filter($array, $isNotFilled), $explication);
    }

    /**
     * Asserts the array contains no filled elements.
     *
     * {@see self::assertArrayFilledElementsOnly()}
     *
     * An empty array will pass.
     *
     * @param array  $array
     * @param string $explication
     */
    public static function assertArrayNoFilledElements(array $array, $explication = null)
    {
        if (null === $explication) {
            $explication = 'no filled elements';
        }
        $isFilled = function ($element) {
            return '' === $element || count($element) <= 0;
        };
        TestCase::assertSame(array(), array_filter($array, $isFilled), $explication);
    }
}
