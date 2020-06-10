<?php

namespace CubeTools\CubeCommonDevelop;

use Symfony\Component\Console\Application;

class SymfonyCommands
{
    public static function addCcdCommands(Application $application)
    {
        $application->add(new Command\CheckXliffFiles());
        $application->add(new Command\ReorderXliffFiles());
        $application->add(new Command\CheckHtmlTwig());
    }

    public static function initCommands()
    {
        $autoload = null;
        $appDir = __DIR__.'/../../../../../';
        $autoloadDirs = array('./vendor/', $appDir.'vendor/');
        $autoloadDirs[] = $autoloadDirs[0];
        foreach ($autoloadDirs as $autoloadDir) {
            $autoload = $autoloadDir.'autoload.php';
            if (file_exists($autoload)) {
                break;
            }
        }
        require_once $autoload;

        $application = new Application();
        self::addCcdCommands($application);

        return $application->run();
    }
}
