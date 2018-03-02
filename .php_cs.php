<?php

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
;

return (new PhpCsFixer\Config('Streamlike'))
    ->setRules([
        '@Symfony' => true,
        '@DoctrineAnnotation' => true,
        'array_syntax' => [
            'syntax' => 'short'
        ],
        'declare_strict_types' => false,
    ])
    ->setFinder($finder)
;
