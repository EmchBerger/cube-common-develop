<?php

spl_autoload_register(function ($class) {
    $loadClass = function ($localName) use ($class) {
        try {
            $prefix = 'CubeTools\CubeCommonDevelop\CodeStyle\PhpStanDummy\Classes\\';
            $s = class_alias($prefix.$localName, $class);
        } catch (\Throwable $e) {
            $msg = sprintf("Exception %s while loading dummy for '%s'\n", get_class($e), $class);
            echo $msg;
            throw $e;
        }
        if (false === $s) {
            echo "failed to load dummy for '$class'\n";
        }
    };
    if ('PHPUnit\Framework\TestCase' === $class) {
        $loadClass('PHPUnit\TestCase');
    } elseif ('Symfony\Bundle\FrameworkBundle\Test\KernelTestCase' === $class) {
        $loadClass('SymfonyFrameworkTests\KernelTestCase');
    } elseif ('Symfony\Bundle\FrameworkBundle\Test\WebTestCase' === $class) {
        $loadClass('SymfonyFrameworkTests\WebTestCase');
    }
});
