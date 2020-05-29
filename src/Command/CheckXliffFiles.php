<?php

namespace CubeTools\CubeCommonDevelop\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use CubeTools\CubeCommonDevelop\CodeStyle\XliffFiles;

class CheckXliffFiles extends Command
{
    protected static $defaultName = 'lint:xliff:cubestyle';

    protected function configure()
    {
        $this
            // self::$defaultName instead of ->setName()
            ->setDescription('checks some styles in xliff files')
            ->setHelp(<<<EoMsg
checks some styles in xliff files
  id is (shortened) source
  source contains parameters of trans
EoMsg
             )
            ->addArgument('files', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'files to check')
            ->addOption('fix', 'f', InputOption::VALUE_NONE, 'write file directly')
            ->addOption('reindent', 'i', InputOption::VALUE_NONE, 'redo indentation of tags')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $files = $input->getArgument('files');
        $doFix = $input->getOption('fix');
        $reindent = $input->getOption('reindent');
        $nErrors = 0;
        $eFiles = 0;
        $cFiles = 0;
        foreach ($files as $file) {
            ++$cFiles;
            $output->write($file);
            $errors = XliffFiles::fixXliffFile($file, $doFix, $reindent);
            if ($errors) {
                $n = count($errors);
                $output->writeln(sprintf(' <error>%d ERRORS</>', $n));
                $nErrors += $n;
                foreach ($errors as $error) {
                    $msg = sprintf(
                        " * <comment>%s</>\n",
                        $error
                    );
                    $output->write($msg);
                }
                ++$eFiles;
            } else {
                $output->writeln(' <info>[OK]</>');
            }
        }
        if ($nErrors) {
            $msg = '<comment>%s</>: <error>%d Errors</> in <error>%d</> files (checked %d of %d)';
            $output->writeln(\sprintf($msg, $this->getName(), $nErrors, $eFiles, $cFiles, \count($files)));
            !$doFix && $output->writeln(\sprintf('fix with running: %s --fix ...', $this->getName()));
        } else {
            $output->writeln(\sprintf('%s: <info>[OK] checked %d files</>', $this->getName(), \count($files)));
        }

        return $nErrors;
    }
}
