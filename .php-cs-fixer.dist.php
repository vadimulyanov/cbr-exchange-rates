<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

return (new Config())
    ->setRules([
        '@Symfony'               => true,
        'binary_operator_spaces' => [
            'default'   => 'single_space',
            'operators' => [
                '=>' => 'align_single_space_minimal',
            ],
        ],
        'yoda_style' => [
            'equal'            => false,
            'identical'        => false,
            'less_and_greater' => false,
        ],
        'concat_space'                => ['spacing' => 'one'],
        'declare_strict_types'        => true,
        'blank_line_before_statement' => [
            'statements' => ['if', 'foreach', 'for', 'while', 'try', 'return', 'throw'],
        ],
        'method_argument_space'       => ['on_multiline' => 'ensure_fully_multiline'],
        'trailing_comma_in_multiline' => ['elements' => ['arguments', 'arrays', 'parameters']],
        'phpdoc_line_span'            => ['const' => 'multi', 'method' => 'multi', 'property' => 'multi'],
        'phpdoc_separation'           => true,
        'phpdoc_summary'              => false,
        'single_line_throw'           => false,
    ])
    ->setRiskyAllowed(true)
    ->setCacheFile(__DIR__ . '/var/php-cs-fixer.cache')
    ->setFinder(
        Finder::create()
            ->in([__DIR__ . '/src', __DIR__ . '/tests'])
            ->append([__FILE__]),
    );
