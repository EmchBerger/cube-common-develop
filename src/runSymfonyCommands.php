#!/usr/bin/env php
<?php

namespace CubeTools\CubeCommonDevelop;

$_mainFile = !\debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
if ($_mainFile) {
    require_once __DIR__.'/SymfonyCommands.php';
    $r = SymfonyCommands::initCommands();
    echo $r;
}
