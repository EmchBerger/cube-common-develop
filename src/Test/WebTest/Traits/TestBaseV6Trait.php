<?php

namespace CubeTools\CubeCommonDevelop\Test\WebTest\Traits;

/**
 * For compabitility to phpunit <= 6.
 */
trait TestBaseV6Trait
{
    use TestBaseVAllTrait;

    public function getSize()
    {
        return $this->doGetSize();
    }

    public function getDataSetAsString($includeData = true)
    {
        return $this->doGetDataSetAsString();
    }

    public function getActualOutput()
    {
        return $this->doGetActualOutput();
    }
}
