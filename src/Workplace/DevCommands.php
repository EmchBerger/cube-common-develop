<?php

namespace CubeTools\CubeCommonDevelop\Workplace;

use Symfony\Bridge\Twig\Command\LintCommand as TwigLint;
use Symfony\Component\Console\Application;
use Symfony\Component\Yaml\Command\LintCommand as YamlLint;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class DevCommands
{
    public static function addCommands(Application $application)
    {
        $application->add(new YamlLint());
        $twigLint = new TwigLint(new Environment(new ArrayLoader()));
        $application->add($twigLint);
    }
}
