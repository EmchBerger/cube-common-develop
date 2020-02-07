<?php

namespace Tests\CubeTools\CubeCommonDevelop\CodeStyle;

use Symfony\Component\Process\Process;

class CheckFilesTest extends \PHPUnit\Framework\TestCase
{
    public static function getCmdToTest()
    {
        static $cmdToTest = null;
        if (is_null($cmdToTest)) {
            $cmdToTest = strtr(__DIR__, ['tests' => 'src']).'/check-files-cube.sh';
        }

        return $cmdToTest;
    }

    /**
     * @dataProvider provideCheckFiles
     */
    public function testChecked($file, $matchingCmd)
    {
        $cmdLine = [
            self::getCmdToTest(),
            $file,
        ];
        $pCheck = new Process($cmdLine);
        $pCheck->setTimeout(1);
        $pCheck->setEnv([
            'REPORTONLY' => '1',
        ]);
        try {
            $pCheck->run();
        } catch (\Symfony\Component\Process\Exception\ProcessTimedOutException $e) {
        }
        $runningLines = preg_grep('@# running:@', explode("\n", $pCheck->getErrorOutput()));
        $checkText = '';
        static $posColon = null;
        foreach ($runningLines as $line) {
            if (is_null($posColon)) {
                $posColon = strpos($line, ' ', strpos($line, 'running:')) + 1;
            }
            $checkText .= substr($line, $posColon)."\n";
        }
        if (!is_array($matchingCmd)) {
            $matchingCmd = [$matchingCmd];
        }
        foreach ($matchingCmd as $regExp) {
            $this->assertRegExp($regExp, $checkText);
        }
    }

    public static function provideCheckFiles() /*$testName*/
    {
        yield 'php' => [__FILE__, [
            '@\bphp -l '.__FILE__.'\b|\bparallel-lint '.__FILE__.'\b@', // aborts script when file not existing
            '@\bphpcs\b.* '.__FILE__.'\b@',
            '@\bphpstan\b.* '.__FILE__.'\b@',
        ]];
        $file = 'not/existing/ju.xlf';
        yield 'xlf' => [$file, [
            '@\bphpunit src/Test/Resources/TranslationFileTest.php\b@', // aborts script when file not existing
            // TODO when implemented: '@\blint:xliff\b.*\b'.$file.'@',
        ]];
        // + .xliff
        $file = 'some/file/here.html.twig';
        yield 'twig' => [$file, [
            '@\blint:twig\b.*\b'.$file.'\b@',
        ]];
        $file = 'a/yaml/file.yml';
        yield 'yml' => [$file, [
            '@\blint:yaml\b.*\b'.$file.'\b@',
        ]];
        // + .yaml, .neon
        $file = 'composer.json';
        yield 'composer' => [$file, [
            '@\bcomposer\b.*\bvalidate\b@',
        ]];
        $file = 'web/doit.js';
        yield 'js' => [$file, [
            '@\bphpcs\b.*\b'.$file.'\b@',
        ]];
        $file = 'web/view.css';
        yield 'css' => [$file, [
            '@\bphpcs\b.*\b'.$file.'\b@',
        ]];
        $file = self::getCmdToTest();
        yield 'shellscript' => [$file, [
            '@\bbash -n\b.* '.$file.'\b@',
            '@\bshellcheck\b.* '.$file.'@',
        ]];
        /* only works when:
         *   - entity file is existing (because php -l aborts the script)
         'some/Entity/mine.php' as 'entity' matching '@\bbin/console doctrine:schema:validate\b@'
         */
    }
}
