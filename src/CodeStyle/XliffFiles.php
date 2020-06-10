<?php

namespace CubeTools\CubeCommonDevelop\CodeStyle;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DomCrawler\Crawler;

class XliffFiles
{
    public static function fixXliffFile($fileName, $doFix = false, $reindent = false)
    {
        if (!file_exists($fileName)) {
            return array('file not found');
        }
        $content = \file_get_contents($fileName);
        $crawler = new Crawler();
        $crawler->addXMLContent($content);
        $fixed = array();
        $runs = $crawler->filter('body trans-unit')->each(function (Crawler $unit) use (&$fixed) {
            self::checkUnit($unit, $fixed);
        });
        if ($fixed) {
            $xmlDoc = $crawler->getNode(0)->ownerDocument;
            $xmlDoc->encoding = 'utf-8';
            if ($reindent) {
                $xmlDoc->preserveWhiteSpace = false;
                $xmlDoc->formatOutput = true;
                $xmlDoc->loadXML($xmlDoc->saveXML()); // must reload because format is applied on loading only
                $xmlContent = \preg_replace('/^( +)\</m', '$1$1<', $xmlDoc->saveXML()); // change indentation from 2 to 4
            } else {
                $xmlContent = $xmlDoc->saveXML();
            }
            $xmlContent = \str_replace(' ns="', ' xmlns="', \substr($xmlContent, 0, 128)).\substr($xmlContent, 128);
            // str_replace because xmlns= is changed to ns=. (by Crawler.)
            $nBytes = \file_put_contents($fileName.'#', $xmlContent);
            if ($doFix && $nBytes) {
                rename($fileName, $fileName.'~');
                rename($fileName.'#', $fileName);
            } elseif ($doFix) {
                $fixed[] = 'FAILED to write file';
            }
        } elseif (!$runs) {
            $err = libxml_get_last_error();
            if ($err) {
                $fixed[] = 'ERROR in xml: '.$err->message;
            } else {
                $fixed[] = 'WARNING, no elements found, maybe xml not well-formatted';
            }
        }

        return $fixed;
    }

    /**
     * Create an order array from a xliff file.
     *
     * @return int[] keys set from entries on orderFile
     */
    public static function getOrderFromFile(string $orderFile)
    {
        $toOrder = simplexml_load_file($orderFile);
        foreach ($toOrder->file->body->children() as $unit) {
            $order[] = (string) $unit['id'];
        }

        return array_flip($order);
    }

    /**
     * Reorder entries in a xliff file according to $order.
     *
     * @param string $file  filename of xliff file to reorder
     * @param int[]  $order order to follow (key is keyname, value is order number)
     *
     * @return int 0 if the file is already sorted, else another number
     */
    public static function sortFile($file, array $order)
    {
        $domDoc = new \DomDocument();
        $domDoc->load($file);

        /** @var \DOMNode[] $toSort domElements to order */
        $toSort = [];
        foreach ($domDoc->getElementsByTagName('trans-unit') as $unit) {
            $toSort[$unit->attributes->getNamedItem('id')->value] = $unit;
        }
        $sorted = self::sortEntries($toSort, $order);
        if ($toSort === $sorted) {
            // it is already sorted

            return 0;
        }

        // reorder the entires in the file, keeping related nodes (comments, ...) together
        foreach ($sorted as $key => $unit) {
            $toAppend = [$unit];
            // collect related nodes
            while ($unit->previousSibling && \XML_ELEMENT_NODE !== $unit->previousSibling->nodeType) {
                $unit = $unit->previousSibling;
                $toAppend[] = $unit;
            }
            $beforeNode = $unit->parentNode->lastChild;
            foreach (array_reverse($toAppend) as $unit) {
                $beforeNode->parentNode->insertBefore($unit, $beforeNode);
            }
        }
        if ($domDoc->save($file.'#')) {
            rename($file, $file.'~');
            rename($file.'#', $file);
        }

        return 1;
    }

    /**
     * Sort the entries according to the keys (as the keys of $getOrder).
     *
     * @param mixed[] $toSort   entries (from xliff file) to sort
     * @param int[]   $getOrder sort order (key is key, value is order number)
     *
     * @return mixed[] reordered array with keys and values of $toSort
     */
    private static function sortEntries(array $toSort, array $getOrder)
    {
        /** @var float[] $newOrder with keys form $toSort */
        $newOrder = [];
        $lPos = -1;
        $j = 0;
        // determin the order
        foreach (array_keys($toSort) as $key) {
            if (isset($getOrder[$key])) {
                $lPos = $getOrder[$key];
                $newOrder[$key] = $lPos + 0.0;
                $j = 0;
            } else {
                ++$j;
                $newOrder[$key] = $lPos + $j / 1000.0;
            }
        }

        asort($newOrder);
        /** @var mixed[] $sorted sorted $toSort */
        $sorted = [];
        foreach (array_keys($newOrder) as $keyLabel) {
            $sorted[$keyLabel] = $toSort[$keyLabel];
        }

        return $sorted;
    }

    private static function checkUnit(Crawler $unit, array &$fixed)
    {
        $id = $unit->attr('id');
        $sourceTxt = $unit->filter('source')->text();
        if (self::invalidId($id, $sourceTxt)) {
            $spacePos = strpos($sourceTxt, ' %');
            if (false !== $spacePos) {
                $nId = substr($sourceTxt, 0, $spacePos);
            } elseif (false !== strpos($sourceTxt, ' ') && strlen($sourceTxt) > 64) {
                $nId = substr($sourceTxt, 0, 64 - 8 - 1).'_'.substr(md5($sourceTxt), 3, 8);
            } else {
                $nId = $sourceTxt;
            }
            if ($id !== $nId) {
                $node = $unit->getNode(0);
                $node->setAttribute('id', $nId);
                $node->removeAttribute('resname'); // unwanted
                $fixed[] = 'id of "'.substr(strtr($sourceTxt, array("\n" => "\\n")), 0, 128).'"';
            } else {
                $fixed[] = 'TODO id invalid: '.$id;
            }
        }
        if (false !== strpos($sourceTxt, ' %') && false === self::getTranslatedParamPos($unit->filter('target')->text())) {
            $fixed[] = 'TODO include parameters in source "'.strtr($sourceTxt, array("\n", "\\n")).'" (from target )';
        }
    }

    /**
     * Check if translation contains something looking like a parameter.
     *
     * The translated text can also contain the parameter inside quotes. (The original text (label) can not.)
     *
     * @return int|false
     */
    private static function getTranslatedParamPos($string, $offset = 0)
    {
        $pos2 = strpos($string, '%', $offset);
        if (false === $pos2 || 0 === $pos2) {
            // not found, or at start
            return $pos2;
        }
        $atPos1 = strtolower(substr($string, $pos2 - 1, 1));
        if ($atPos1 < 'a' && '%' !== $atPos1 || $atPos1 > 'z') {
            // looks like a parameter start (like '"%', ' %' or '(%', but not '%%')
            return $pos2;
        } else {
            // look for next % character
            return self::getTranslatedParamPos($string, $pos2 + 1);
        }
    }

    /**
     * Check $id is valid for $sourceTxt.
     *
     * $id is invalid when:
     *  * it is empty
     *  * it contains '% '
     *  * it is not contained in $sourceTxt
     */
    private static function invalidId($id, $sourceTxt)
    {
        return !$id || false !== strpos($id, ' %') ||
            (false === strpos($sourceTxt, $id) && false === strpos(strtolower($sourceTxt), $id));
    }
}
