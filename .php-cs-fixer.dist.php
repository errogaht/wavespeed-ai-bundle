<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()->in([__DIR__.'/src', __DIR__.'/tests']);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'array_syntax' => ['syntax' => 'short'],
        'declare_strict_types' => true,
    ])
    ->setFinder($finder);
