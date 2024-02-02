<?php
/**
 * boot redaxo and load packages
 * necessary to use \rexstan\RexStanUserConfig::save()
 */
unset($REX);
$REX['REDAXO'] = true;
$REX['HTDOCS_PATH'] = './';
$REX['BACKEND_FOLDER'] = 'redaxo';
$REX['LOAD_PAGE'] = false;

require './redaxo/src/core/boot.php';
require './redaxo/src/core/packages.php';

/**
 * rexstan config
 */
$extensions = [
    '../../../../redaxo/src/addons/rexstan/config/rex-superglobals.neon',
    '../../../../redaxo/src/addons/rexstan/vendor/phpstan/phpstan/conf/bleedingEdge.neon',
    '../../../../redaxo/src/addons/rexstan/vendor/phpstan/phpstan-strict-rules/rules.neon',
    '../../../../redaxo/src/addons/rexstan/vendor/phpstan/phpstan-deprecation-rules/rules.neon',
    '../../../../redaxo/src/addons/rexstan/config/phpstan-phpunit.neon',
    '../../../../redaxo/src/addons/rexstan/config/phpstan-dba.neon',
    // '../../../../redaxo/src/addons/rexstan/config/cognitive-complexity.neon',
    // '../../../../redaxo/src/addons/rexstan/config/code-complexity.neon',
    '../../../../redaxo/src/addons/rexstan/config/dead-code.neon'
];

// get addon key from environment variable
$addon = ['../../../../redaxo/src/addons/' . getenv('ADDON_KEY') . '/'];

/**
 * save config
 * @param int $level the level to use
 * @param array $addon the addon to use
 * @param array $extensions the extensions to use
 * @param int $phpVersion the php version to use
 */
\rexstan\RexStanUserConfig::save(5, $addon, $extensions, 80203);

// ab hier Erweiterung für Focuspoint

/**
 * Datei einlesen
 */
$property = new ReflectionMethod(\rexstan\RexStanUserConfig::class, 'getPhpStanConfig');
$property->setAccessible(true);
/** @var array $UserConfig */
$UserConfig = $property->invoke(null);

/**
 * Exclude hinzufügen
 * - help.php wird nicht betrachtet, hat auch keine Referenzen
 */
// $UserConfig['parameters']['excludePaths']['analyseAndScan'][] = '../../../../*/focuspoint/help.php';

/**
 * Speichern
 */
$property = new ReflectionMethod(\rexstan\RexStanUserConfig::class, 'getUserConfigPath');
$property->setAccessible(true);
/** @var string $UserConfigPath */
$UserConfigPath = $property->invoke(null);
$prefix = "# rexstan auto generated file - do not edit, rename or remove\n\n";
rex_file::put($UserConfigPath, $prefix . rex_string::yamlEncode($UserConfig, 3));
