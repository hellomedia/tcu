<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('var')
    ->notPath([
        'config/bundles.php',
        'config/reference.php',
    ])
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        // ADAPT: the user's house style, decided per project (see README "php-cs-fixer").
        // TCU: `$dir . '/' . $name` and `$x === null`.
        'concat_space' => ['spacing' => 'one'],
        'yoda_style' => false,
        // No trailing dot forced at the end of docblock summaries.
        'phpdoc_summary' => false,
    ])
    ->setFinder($finder)
;
