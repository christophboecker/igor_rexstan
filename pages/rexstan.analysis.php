<?php
/**
 * Ersetzt die Seite rexstan/analysis.
 *
 * Die Fehlermeldungen werden in ein CustomHTML <rexstan-analysis> gekapselt.
 */

namespace Igor\Rexstan;

use rex_config;
use rex_fragment;
use rex_i18n;
use rex_path;
use rex_url;
use rex_view;
use rexstan\RexStan;
use rexstan\RexStanTip;

use function array_key_exists;
use function is_string;

/**
 * Vorgelagerter neuer Code: easy way um den phpstan-Cache zu löschen.
 * 0:   nicht löschen, default
 * 1:   einmal löschen, danach clear_phpstan_cache auf 0 setzen
 * 2:   stets löschen.
 */
$clearCache = rex_config::get('rexstan', 'clear_phpstan_cache', '0');
if ('1' === $clearCache) {
    rex_config::set('rexstan', 'clear_phpstan_cache', '0');
    $clearCache = '2';
}
if ('2' === $clearCache) {
    RexStan::clearResultCache();
}

/**
 * Hinweis auf die überlagerte Seite
 * optional: clearResultCache.
 */
echo '<small class="text-danger"><i class="fa fa-warning text-info"></i> ',rex_i18n::msg('igor_rexstan_replaced_warning', 'Analyse'),'</small>';

/**
 * zunächst Standard-Ablauf aus rexstan/analysis.php.
 * Nur etwas anders arrangiert.
 * @var string|array{totals:array{errors:int,file_errors:int},files?:array<string,array{errors:int,messages:array<int,array{message:string,line:int,ignorable:bool,tip?:string}>}>,errors:string[]}
 */
$phpstanResult = RexStan::runFromWeb();
$settingsUrl = rex_url::backendPage('rexstan/settings');

/**
 * PhpStan meldet einen funktionalen Fehler im Ablauf.
 */

if (is_string($phpstanResult)) {
    // we moved settings files into config/.
    if (false !== stripos($phpstanResult, "neon' is missing or is not readable.")) {
        // TODO: Text nach lang übertragen
        echo rex_view::warning(
            "Das Einstellungsformat hat sich geändert. Bitte die <a href='". $settingsUrl ."'>Einstellungen öffnen</a> und erneut abspeichern. <br/><br/>".nl2br($phpstanResult)
        );
    } else {
        echo rex_view::error(
            // TODO: Text nach lang übertragen
            '<h4>PHPSTAN: Fehler</h4>'
                .nl2br($phpstanResult)
        );
    }

    // TODO: Text nach lang übertragen
    echo rex_view::info('Die Web UI funktionert nicht auf allen Systemen, siehe README.');

    return;
}

/**
 * PhpStan stellt Laufzeitfehler fest.
 */
if (0 < $phpstanResult['totals']['errors']) {
    // TODO: Text nach lang übertragen
    $msg = '<h4>PHPSTAN: Laufzeit-Fehler</h4><ul>';
    foreach ($phpstanResult['errors'] as $error) {
        $msg .= '<li>'.nl2br($error).'<br /></li>';
    }
    $msg .= '</li>';
    echo rex_view::error($msg);
    return;
}

/**
 * PhpStan liefert gar keine Datei-Meldungen (auch nicht "leer => fehlerfrei").
 */
if (!isset($phpstanResult['files'])) {
    echo rex_view::warning('No phpstan result');
    return;
}

/* das ist wohl spätestetsns mit 1.0.63 wiedr rausgefallen
if (array_key_exists('N/A', $phpstanResult['files'])) {
    $phpstanResult['totals']['file_errors'] -= $phpstanResult['files']['N/A']['errors'];
    unset($phpstanResult['files']['N/A']);
}
*/

/**
 * PhPStan hat die Dateien analysiert und findet keinen Fehler.
 */
if (0 === $phpstanResult['totals']['file_errors']) {
    $level = intval(rex_config::get('rexstan', 'level', 0));
    $fragment = new rex_fragment();
    $fragment->setVar('level', $level);
    echo $fragment->parse('analysis_success.php');
    return;
}

/**
 * Analyse endet mit Fehlermeldungen.
 */
$initialExpand = '|1|' === rex_config::get('rexstan', 'open', '|1|') ? '1' : '0';
$searchKey4Tips = rex_config::get('rexstan', 'tip_key', '^');
$searchKey4Tips = '' === $searchKey4Tips ? '^' : $searchKey4Tips;
echo '<script>Rexstan.initialCollapse=\''.$initialExpand.'\';Rexstan.searchKey4Tips=\''.$searchKey4Tips.'\'</script>';

echo '<rexstan-analysis class="rex-page-section">';

$fragment = new rex_fragment();
$fragment->setVar('level', rex_config::get('rexstan', 'level', '0'), false);
$fragment->setVar('infoset', rex_config::get('rexstan', 'level', '0'), false);
echo $fragment->parse('analysis_header.php');

$basePath = rex_path::src('addons/');

$fragment = new rex_fragment();
$fragment->setVar('showTip', '|1|' === rex_config::get('rexstan', 'tip', '|1|'));
foreach ($phpstanResult['files'] as $file => $fileResult) {
    // Tips wenn vorhanden aufbereiten
    foreach ($fileResult['messages'] as &$message) {
        if (array_key_exists('tip', $message)) {
            $message['tip'] = RexStanTip::renderTip($message['message'], $message['tip']);
            if (null === $message['tip']) {
                unset($message['tip']);
            }
        }
    }

    $fragment->setVar('link', preg_replace('/\s\(in context.*?$/', '', $file), false);
    $fragment->setVar('file', rex_path::relative($file, $basePath), false);
    $fragment->setVar('result', $fileResult, false);
    echo $fragment->parse('analysis_items.php');
}

echo '</rexstan-analysis>';
