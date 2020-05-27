<?php

/**
 * for compatibility to phpunit versions
 */

// inspired by https://github.com/symfony/symfony/commit/121f42641834a7bbe209ba85df32295aca9fe41c

namespace CubeTools\CubeCommonDevelop\Test\WebTest\Traits;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

if ((new ReflectionClass(TestCase::class))->getMethod('getSize')->hasReturnType()) {
    class_alias(TestBaseV7Trait::class, __NAMESPACE__.'\TestBaseTrait'); // alias for trait
} else {
    class_alias(TestBaseV6Trait::class, __NAMESPACE__.'\TestBaseTrait'); // alias for trait
}
