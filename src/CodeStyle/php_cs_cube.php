<?php
    /*
     * configuration for php-cs-fixer 2.x (cubetools standard)
     *
     * use like this in .php_cs.dist:
     * return require __DIR__.'vendor/cubetools/cube-common-develop/src/CodeStyle/php_cs_cube.php';
     *
     * or "php-cs-fixer fix --config=path/to/php_cs_cube.php ..."
     */

namespace CubeTools\CubeCommonDevelop\CodeStyle;

use PhpCsFixer;

$finder = PhpCsFixer\Finder::create()
    ->exclude('vendor')
    ->exclude('cache')
    ->exclude('logs')
    ->exclude('var')
    ->in('src')
    ->append(['.php_cs.dist'])
;

if (is_dir('tests')) {
    $finder->in('tests');
}

return (new PhpCsFixer\Config('Cubetools Standard'))
    ->setRules([
        '@Symfony' => true,
        //'concat_without_spaces' => false,
        'binary_operator_spaces' => [
            'align_double_arrow' => null,
            'align_equals' => null,
        ],
        'no_unused_imports' => true, // to have this set in _reduced also
        'ordered_class_elements' => true,
    ])
    ->setFinder($finder)
    ->setCacheFile('vendor/.php_cs.cache')
;
