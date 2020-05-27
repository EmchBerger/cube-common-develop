<?php

namespace CubeTools\CubeCommonDevelop\Test\WebTest\Traits;

/**
 * For compabitility to phpunit >= 7.
 */
trait TestBaseV7Trait
{
    use TestBaseVAllTrait;

    public function getSize(): int
    {
        return $this->doGetSize();
    }

    public function getDataSetAsString($includeData = true): string
    {
        return $this->doGetDataSetAsString();
    }

    public function getActualOutput(): string
    {
        return $this->doGetActualOutput();
    }
}
