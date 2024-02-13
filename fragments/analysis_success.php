<?php
/**
 * Fragment: Erfolgsanzeige (0 Fehler) der Analyse-Ergebnisse einer Datei.
 *
 * level    Aktueller Analyse-Level (0...9).
 */

namespace ChristophBoecker\IgorRexstan;

use rex_fragment;
use rex_i18n;
use rex_url;
use rex_view;

/** @var rex_fragment $this */

/** @var int $level */
$level = $this->level;

$emoji = '';
switch ($level) {
    case 0:
        $emoji = '❤️️';
        break;
    case 1:
        $emoji = '✌️';
        break;
    case 2:
        $emoji = '💪';
        break;
    case 3:
        $emoji = '🧙';
        break;
    case 4:
        $emoji = '🏎️';
        break;
    case 5:
        $emoji = '🚀';
        break;
    case 6:
        $emoji = '🥉';
        break;
    case 7:
        $emoji = '🥈';
        break;
    case 8:
        $emoji = '🥇';
        break;
}

echo '<span class="rexstan-achievement">' . $emoji . '</span>';
echo rex_view::success(rex_i18n::msg('igor_rexstan_analysis_success', $level));

if (9 === $level) {
    echo '<script>Rexstan.hipHipHurray();</script>';
} else {
    echo '<p>',rex_i18n::rawMsg('igor_rexstan_analysis_nextlevel', rex_url::backendPage('rexstan/settings')),'</p>';
}
