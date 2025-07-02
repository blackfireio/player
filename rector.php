<?php

declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\CodeQuality\Rector\If_\SimplifyIfReturnBoolRector;
use Rector\CodingStyle\Rector\Assign\SplitDoubleAssignRector;
use Rector\CodingStyle\Rector\Catch_\CatchExceptionNameMatchingTypeRector;
use Rector\CodingStyle\Rector\ClassMethod\NewlineBeforeNewAssignSetRector;
use Rector\CodingStyle\Rector\Stmt\NewlineAfterStatementRector;
use Rector\Config\RectorConfig;
use Rector\Php84\Rector\MethodCall\NewMethodCallWithoutParenthesesRector;
use Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\AssertEmptyNullableObjectToAssertInstanceofRector;
use Rector\Privatization\Rector\Class_\FinalizeTestCaseClassRector;
use Rector\Strict\Rector\Empty_\DisallowedEmptyRuleFixerRector;
use Rector\Symfony\CodeQuality\Rector\Class_\InlineClassRoutePrefixRector;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\DeclareStrictTypesRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/Player',
    ])
    ->withCache(
        cacheDirectory: __DIR__.'/var/cache/rector',
        cacheClass: FileCacheStorage::class,
    )
    ->withImportNames(
        importNames: true,
        importDocBlockNames: true,
        importShortClasses: false, // do not enable, it changes `\Datetime` (and co.) into `use` statement
        removeUnusedImports: false, // do not enable, it removed imports that are used in comments
    )
    ->withPhpSets(php84: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        privatization: true,
        naming: false, // do not enable, it changes variable name and break auto-wiring
        instanceOf: true,
        earlyReturn: true,
        strictBooleans: true,
        carbon: false, // do not enable, it replaces time functions with \Carbon lib
        rectorPreset: true,
        phpunitCodeQuality: true,
        doctrineCodeQuality: true,
        symfonyCodeQuality: true,
        symfonyConfigs: false, // do not enable, it changes variable name and break auto-wiring
    )
    ->withAttributesSets(
        symfony: true,
        doctrine: true,
        mongoDb: false, // do not enable, we don't use mongoDb
        gedmo: false, // do not enable, we don't use gedmo
        phpunit: true,
        fosRest: false, // do not enable, we don't use fosRest
        jms: false, // do not enable, we don't use jms
        sensiolabs: false, // do not enable, we don't use sensiolabs
        behat: false, // do not enable, we don't use behat
    )
    ->withComposerBased(
        twig: true,
        doctrine: true,
        phpunit: true,
        symfony: true,
    )
    ->withSkip([
        // injected by php83 preset
        AddOverrideAttributeToOverriddenMethodsRector::class,

        // injected by php84 preset
        NewMethodCallWithoutParenthesesRector::class,

        // injected by codeQuality preset
        DisallowedEmptyRuleFixerRector::class,
        FlipTypeControlToUseExclusiveTypeRector::class,
        SimplifyIfReturnBoolRector::class,

        // injected by codingStyle preset
        CatchExceptionNameMatchingTypeRector::class,
        NewlineAfterStatementRector::class,
        NewlineBeforeNewAssignSetRector::class,
        SplitDoubleAssignRector::class,

        // injected by rectorPreset preset
        DeclareStrictTypesRector::class,
        FinalizeTestCaseClassRector::class,

        // injected by phpunitCodeQuality preset
        InlineClassRoutePrefixRector::class,
        AssertEmptyNullableObjectToAssertInstanceofRector::class,
    ]);
