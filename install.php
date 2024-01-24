<?php

namespace Igor\Rexstan;

use rex_addon;
use rex_config;
use rex_file;
use rex_path;
use rex_scss_compiler;
use Throwable;

/** @var rex_addon $this */

/**
 * Steht auf true, wenn erfolgreich resatsn.scss in style.css umgewandelt wurde.
 * @var bool $systemStyleApplied
 */
$systemStyleApplied = false;

/**
 * Erstellt eine CSS-Datei basierend auf den Backend-Styles aus dem Addon be_style (falls aktiv).
 * rex_scss_compiler ist verfügbar wenn be_style installiert ist.
 */
if (class_exists('rex_scss_compiler')) {
    try {
        $compiler = new rex_scss_compiler();
        // Klartext-Ausgabe falls man für Tests "lesbares" CSS erzeugen möchte
        $compiler->setFormatter(\ScssPhp\ScssPhp\Formatter\Expanded::class);

        $compiler->setRootDir(__DIR__ . '/scss');
        $compiler->setScssFile([
            rex_path::plugin('be_style', 'redaxo', 'scss/_variables.scss'),
            rex_path::plugin('be_style', 'redaxo', 'scss/_variables-dark.scss'),
            rex_path::addon('be_style', 'vendor/font-awesome/scss/_variables.scss'),
            __DIR__ . '/scss/rexstan.scss',
        ]);

        $compiler->setCssFile(__DIR__ . '/assets/style.min.css');
        $compiler->compile();
        $systemStyleApplied = true;
    } catch (Throwable $th) {
        // ignore Error Message;
    }
}

/**
 * Als Fallback steht eine funktionierende Datei im SCSS-Verzeichnis zur Verfügung, die statt des
 * aus SCSS generierten CSS in das Asset-Verzeichnis kopiert wird.
 * Greift auch, wenn die Compilierung schief ging.
 */
if (!$systemStyleApplied) {
    rex_file::copy(__DIR__ . '/scss/style.min.css', __DIR__ . '/assets/style.min.css');
}

/**
 * Installiere die zusätzlichen Config-Einträge mit Default-Werten.
 */

rex_config::set('rexstan', 'dont_analyse', rex_config::get('rexstan', 'dont_analyse', ''));
rex_config::set('rexstan', 'dont_scan', rex_config::get('rexstan', 'dont_scan', ''));
rex_config::set('rexstan', 'clear_phpstan_cache', rex_config::get('rexstan', 'clear_phpstan_cache', 0));
rex_config::set('rexstan', 'open', rex_config::get('rexstan', 'open', '1'));
rex_config::set('rexstan', 'tip_key', rex_config::get('rexstan', 'tip_key', '^'));
rex_config::set('rexstan', 'tip', rex_config::get('rexstan', 'tip', '1'));
