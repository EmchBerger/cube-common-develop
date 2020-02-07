<?php

namespace CubeTools\CubeCommonDevelop\Test\App;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Basic test to check if symfony binaries are loadable.
 *
 * Extend this class in a test of the project, or create a test file containing "new SymfonyLoadableTest();"
 *
 * Tests if front controller (public/index.php or web/app.php, web/app_dev.php) and xxx/console are runnable,
 * and if all console commands are at least loadable.
 *
 * Usable when modifying autoloading or console location, when modifying commands or ...
 */
class SymfonyLoadableTest extends TestCase
{
    /**
     * @var string php executable path
     */
    private static $php;

    /**
     * @var string symfony console path
     */
    private static $console;

    public static function setUpBeforeClass()
    {
        $executableFinder = new PhpExecutableFinder();
        self::$php = $executableFinder->find();
        $console = 'bin/console';
        if (!file_exists($console) && file_exists('app/console')) {
            $console = 'app/console';
        }
        self::$console = $console;
    }

    /**
     * @dataProvider getAppNames
     */
    public function testAppRunnable($appPath)
    {
        $proc = new Process(self::$php.' '.$appPath, null, null, 5);
        $proc->mustRun();
        $this->assertEquals('', $proc->getErrorOutput(), 'no error output');
        $this->assertNotEmpty($proc->getOutput(), 'some output');
    }

    public static function getAppNames()
    {
        if (file_exists('public/index.php')) {
            yield 'index' => ['public/index.php'];
        } else {
            yield 'prod' => ['web/app.php'];
            foreach (glob('web/app_*.php') as $appPath) {
                yield basename($appPath) => [$appPath];
            }
        }
    }

    public function testConsoleRunnable()
    {
        $p = $this->runConsoleCommand('-V');
        $this->assertEquals('', $p->getErrorOutput(), 'no error output');
        $this->assertContains('ymfony ', $p->getOutput(), 'some output');
        if ('bin/' === substr(self::$console, 0, 4)) {
            $this->assertTrue(is_dir('var'), 'var/ exists if console in bin/');
        }
    }

    /**
     * @depends testConsoleRunnable
     */
    public function testConsoleCommandsValid()
    {
        try {
            $this->runConsoleCommand('list --format=md');
        } catch (\Symfony\Component\Process\Exception\ProcessFailedException $e) {
            $fullMsg = $e->getMessage();
            $pos = strpos($fullMsg, "\n");
            $msg = substr($fullMsg, 0, $pos);
            // could find the failing command by analyzing remaining lines
            $expl = 'run the command manually and look which commands are listed only in the overview => next one is failing';
            /*
             * grep -B 1 -e '-----' # shows successful commands
             * grep -e '* [' # shows all commands
             */
            $this->assertFalse(true, $msg."\n\n  ".$expl);
        }
        $this->assertTrue(true);
    }

    protected function runConsoleCommand($arg)
    {
        $p = new Process(self::$php.' '.self::$console.' '.$arg, null, null, 5);
        $p->mustRun();

        return $p;
    }
}
