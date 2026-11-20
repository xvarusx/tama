<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\SafeDeclareStrictTypesRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/../../src',
        __DIR__ . '/../../tests',
    ])

    // Detect PHP version from composer.json
    ->withPhpSets()

    // Import management
    ->withImportNames(
        importNames: true,
        importDocBlockNames: true,
        removeUnusedImports: false,
        importShortClasses: true,
    )

    // Gradually improve code quality
    ->withDeadCodeLevel(0)
    ->withCodeQualityLevel(0)
    ->withCodingStyleLevel(0)
    ->withTypeCoverageLevel(0)

    // Framework-specific rules
    ->withComposerBased(
        symfony: true,
        doctrine: true,
    )

    // Custom rules
    ->withRules([
        SafeDeclareStrictTypesRector::class,
    ]);
