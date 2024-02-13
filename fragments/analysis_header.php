<?php
/**
 * Fragment: Anzeige der Header-Sektion oberhalb der Analyse-Ergebnisse.
 *
 * level    Prüfungs-Level von PhpStan
 *
 * Baut per Formular core/page/section eine Section mit einem Panel auf.
 * Das Panel hat aber keinen weiteren Inhalt, nur den Header.
 *
 * Die aktiven Elemente (Zähler, Buttons, Suchfeld sind CustomHTML.
 */

namespace ChristophBoecker\IgorRexstan;

use rex_fragment;
use rex_i18n;

/** @var rex_fragment $this */

/** @var int $level */
$level = $this->level ?? 0;

/**
 * HTML Button-Group zum Ein-/Ausblenden aller Dateien erzeugen.
 * $collapseButtons sind CustomHTML (<rexstan-trigger>).
 */
$fragment = new rex_fragment();
$fragment->setVar('size', 'xs', false);
$fragment->setVar('buttons', [
    [
        'icon' => 'view',
        'attributes' => [
            'class' => ['btn-default'],
            'from' => '<<rexstan-analysis',
            'event' => 'rexstan:toggleCollapse',
            'detail' => 'show',
            'title' => 'Alle einblenden',
        ],
    ],
    [
        'icon' => 'hide',
        'attributes' => [
            'class' => ['btn-default'],
            'from' => '<<rexstan-analysis',
            'event' => 'rexstan:toggleCollapse',
            'detail' => 'hide',
            'title' => 'Alle ausblenden',
        ],
    ],
], false);
$collapseButtons = $fragment->parse('core/buttons/button_group.php');
$collapseButtons = str_replace(['<button ', '</button>'], ['<rexstan-trigger ', '</rexstan-trigger>'], $collapseButtons);

/**
 * SearchWidget ist ein Custom-HTML (<rexstan-search>).
 */
$searchWidget = '<rexstan-search></rexstan-search>';

/**
 * Eigenständiges CustomHTML als Zähler.
 */
$totalFiles = '<rexstan-amount zero target="<<rexstan-analysis" filter=":scope > rexstan-messages"></rexstan-amount>&nbsp;';
$totalErrors = '<rexstan-amount zero target="<<rexstan-analysis" filter=":scope > rexstan-messages > div rexstan-message" force="rexstan:count.total"></rexstan-amount>&nbsp;';

/**
 * Header-Section zusammenbauen.
 */
$fragment = new rex_fragment();
$fragment->setVar('sectionAttributes', [
    'class' => 'rexstan-sticky-headline',
]);
$fragment->setVar('class', 'warning');
$fragment->setVar('options', $searchWidget . '&nbsp;' . $collapseButtons, false);
// $fragment->setVar('title', 'Level-<strong>'.$level.'</strong>-Analyse: <strong>'. $totalErrors .'</strong> Probleme gefunden in <strong>'. $totalFiles .'</strong> Dateien', false);
$fragment->setVar('title',
    rex_i18n::msg('igor_rexstan_analysis_header_a') . ' <strong>' . $level . '</strong> | ' .
    rex_i18n::msg('igor_rexstan_analysis_header_b') . ' <strong>' . $totalErrors . '</strong> | ' .
    rex_i18n::msg('igor_rexstan_analysis_header_c') . ' <strong>' . $totalFiles . '</strong>',
    false);

echo $fragment->parse('core/page/section.php');
