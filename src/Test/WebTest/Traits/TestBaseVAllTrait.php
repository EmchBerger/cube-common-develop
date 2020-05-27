<?php

namespace CubeTools\CubeCommonDevelop\Test\WebTest\Traits;

/**
 * For compabitility, base trait.
 */
trait TestBaseVAllTrait
{
    abstract protected function doGetSize();

    abstract protected function doGetDataSetAsString();

    abstract protected function doGetActualOutput();
}
