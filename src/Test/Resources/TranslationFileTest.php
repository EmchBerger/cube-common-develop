<?php

namespace CubeTools\CubeCommonDevelop\Test\Resources;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test if translation files are loadable.
 */
class TranslationFileTest extends KernelTestCase
{
    /**
     * List of the languages to test.
     *
     * @var string[]
     */
    protected static $languages = array('en');

    /**
     * Add a text from each language file you want to be sure it loaded. To be sure it is not skipped.
     *
     * @var string[]
     */
    protected static $checkTexts = array('PLEASE DEFINE TEXTS TO CHECK IN THE TEST');

    /**
     * @dataProvider listLanguages
     */
    public function testLanguageFile($langName)
    {
        static::bootKernel();
        $tr = static::$kernel->getContainer()->get('translator');
        $cat = $tr->getCatalogue($langName);
        $texts = $this->getCheckTexts($langName);
        foreach ($texts as $text) {
            $r = $cat->defines($text);
            $msg = 'translation problem';
            if (!$r) {
                $msg = sprintf('translation of "%s" missing', $text);
                if ($this->isIncompleteLanguage($langName, $text)) {
                    $this->markTestIncomplete('Incomplete translation, '.$msg);
                }
            }
            $this->AssertTrue($r, $msg);
        }
    }

    final public static function listLanguages()
    {
        $languages = static::getLanguages();
        // TODO automatic check if all are listed (from {app,src/*Bundle}/Resources/translations/*.x*)
        foreach ($languages as $lang) {
            yield $lang => array($lang);
        }
    }

    /**
     * Returns true when the language test can be skipped because of an incomplete language. For overwriting.
     *
     * @param string $language
     * @param string $missingText
     *
     * @return bool
     */
    protected function isIncompleteLanguage($language, $missingText)
    {
        return 'en' === $language && static::class === self::class;
    }

    /**
     * Returns the texts to check if they are translated.
     *
     * @return string[]
     */
    protected function getCheckTexts($language)
    {
        return static::$checkTexts;
    }

    /**
     * Returns the list of the languages to test.
     *
     * @return string[]
     */
    protected static function getLanguages()
    {
        return static::$languages;
    }
}
