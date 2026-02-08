<?php

$finder = new PhpCsFixer\Finder()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->exclude( __DIR__ . '/docker')
    ->exclude( __DIR__ . '/var')
    ->exclude( __DIR__ . '/vendor')
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return new PhpCsFixer\Config()
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        '@Symfony' => true,
        'array_syntax' => ['syntax' => 'short'],
        'nullable_type_declaration' => ['syntax' => 'union'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'ordered_class_elements' => true,
        'method_argument_space' => true,
        'no_unused_imports' => true,
        'strict_param' => true,
        'declare_strict_types' => true,
        'phpdoc_to_comment' => ['ignored_tags' => ['var']],
        'phpdoc_align' => false,
        'phpdoc_order' => true,
        'single_quote' => true,
        'binary_operator_spaces' => ['default' => 'align_single_space_minimal'],
        'trailing_comma_in_multiline' => ['elements' => ['arrays', 'arguments', 'parameters']],
        'no_superfluous_phpdoc_tags' => true,
        'simplified_if_return' => true,
        'simplified_null_return' => true,
        'no_blank_lines_after_class_opening' => true,
        'multiline_whitespace_before_semicolons' => true,
        'no_trailing_whitespace_in_string' => true,
    ])
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setFinder($finder)
    ->setUsingCache(true)
    ->setCacheFile('/var/cache/.php-cs-fixer.cache');
