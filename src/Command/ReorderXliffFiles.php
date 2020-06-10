<?php

namespace CubeTools\CubeCommonDevelop\Command;

use CubeTools\CubeCommonDevelop\CodeStyle\XliffFiles;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ReorderXliffFiles extends Command
{
    protected static $defaultName = 'cubetools:xliff:reorder';

    protected function configure()
    {
        $cmdName = self::$defaultName;
        $this
            // self::$defaultName instead of ->setName()
            ->setDescription('reorders entries xliff files as in original')
            ->setHelp(<<<EoMsg
to sort as in de, call like:
bin/console $cmdName translations/messages.de.xlf translations/messages.en.xlf translations/messages.fr.xlf
EoMsg
             )
            ->addArgument('order-file', InputArgument::REQUIRED, 'file to get order from')
            ->addArgument('files', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'files to reorder')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $orderFile = $input->getArgument('order-file');
        $files = $input->getArgument('files');

        $order = XliffFiles::getOrderFromFile($orderFile);
        $ret = 0;
        foreach ($files as $file) {
            $output->writeln("sorting $file");
            $ret |= XliffFiles::sortFile($file, $order);
        }

        return $ret;
    }
}
