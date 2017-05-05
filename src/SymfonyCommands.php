<?php

namespace CubeTools\CubeCommonDevelop;

use Symfony\Component\Console\Application;

class SymfonyCommands
{
    public static function addCcdCommands(Application $application)
    {
        $application->add(new Command\CheckXliffFiles());
    }

    public static function initCommands()
    {
        $bootstrap = null;
        $appDir = __DIR__.'/../../../../../';
        $bootstrapDirs = array('./var/', './app/', $appDir.'var/', $appDir.'app/');
        $bootstrapDirs[] = $bootstrapDirs[0];
        foreach ($bootstrapDirs as $bootstrapDir) {
            $bootstrap = $bootstrapDir.'bootstrap.php.cache';
            if (file_exists($bootstrap)) {
                break;
            }
        }
        require_once $bootstrap;

        $application = new Application();
        self::addCcdCommands($application);

        return $application->run();
    }
}
