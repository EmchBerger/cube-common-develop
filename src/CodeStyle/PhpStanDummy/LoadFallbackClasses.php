<?php

spl_autoload_register(function ($class) {
    $namespacePrefix = 'CubeTools\CubeCommonDevelop\CodeStyle\PhpStanDummy\Classes';
    $s = null;
    if ('PHPUnit\Framework\TestCase' === $class) {
        $s = class_alias($namespacePrefix.'\PhpUnit\TestCase', $class);
    } elseif ('Symfony\Bundle\FrameworkBundle\Test\KernelTestCase' === $class) {
        $s = class_alias($namespacePrefix.'\SymfonyFrameworkTests\KernelTestCase', $class);
    } elseif ('Symfony\Bundle\FrameworkBundle\Test\WebTestCase' === $class) {
        $s = class_alias($namespacePrefix.'\SymfonyFrameworkTests\WebTestCase', $class);
    }
    if (false === $s) {
        echo "failed to load dummy for '$class'\n";
    }
});
