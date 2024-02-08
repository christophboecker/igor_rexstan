<?php
/**
 * Fragment: Anzeige der Analyse-Ergebnisse einer Datei.
 *
 * link     Vollständiger Dateiname ab Root für die Editor-Verlinkung
 * file     Dateiname mit Pfadanteilen unterhalb von redaxo/src/addons/ dr aktuellen Datei
 * result   Die Meldungen.
 * showTip  Lösungshinweise initial aufblenden oder nur Button anzeigen
 *
 * Baut per Formular core/page/section eine Section mit einem Panel auf.
 * Der Panel-Header enthält den Dateinamen, diverse Counter und ggf. Buttons
 * uns dient auch als Collapse-Toggle-Button.
 *
 * Der Tag-Name (<section>) wird gegen den CustomHTML-Tag <rexstan-messages> getauscht
 *
 * Als Content sind CustomHTML-Container <rexstan-message> je Message.
 *
 * Hinweis: für einen Refresh einer einzelnen, bereits angezeigten Datei per API-Call
 * wird nur der Message-Teil benötigt. Da in diesem Fall $file nicht erforderlich ist,
 * dient das Fehlen ('' === $file) als Indikator, nur den Content auszugeben, nicht
 * aber den vollstänfigen Container.
 */

namespace Igor\Rexstan;

use rex_editor;
use rex_fragment;
use rex_i18n;

use function array_key_exists;
use function dirname;
use function is_int;

use const DIRECTORY_SEPARATOR;

/** @var rex_fragment $this */

/** @var string $link */
$link = $this->link;

/** @var string $file */
$file = $this->file ?? '';

/** @var array{errors:int,messages:array<int,array{message:string,line:int,ignorable:bool,tip?:string}>} $result */
$result = $this->result;

/** @var bool $showTip */
$showTip = $this->showTip ?? true;

$editor = rex_editor::factory();

$copy2clipboard = ' <rexstan-trigger class="btn btn-default btn-xs"><i class="fa fa-copy"></i></rexstan-trigger>';

/**
 * Die Messages in HTML umsetzen.
 */
$tipIsClosed = $showTip ? '' : ' rexstan-tip-closed';
$content = '';

foreach ($result['messages'] as $message) {
    $tipClass = '';
    $tip = '';
    if (array_key_exists('tip', $message)) {
        $tipClass = ' rexstan-has-tip' . $tipIsClosed;
        $tip .= '<span class="rexstan-tip">' . $message['tip'] . '</span>';
    }

    $text = rex_escape($message['message']);
    $url = $editor->getUrl($link, $message['line']);
    $pasteButton = '<rexstan-trigger class="btn btn-xs btn-default" event="rexstan:clipboard" detail="' . $text . '"title="' . rex_i18n::msg('igor_rexstan_analysis_clipboard') . '"><i class="fa fa-clipboard"></i></rexstan-trigger> ';

    if (null !== $url) {
        $text = '<a href="' . $url . '">' . $text . '</a>';
    }

    $ignoreClass = $message['ignorable'] ? '' : ' text-danger';

    $content .= '<rexstan-message class="' . $tipClass . '">';
    $content .= '<span class="rexstan-line-number' . $ignoreClass . '">' . $message['line'] . ':</span>';
    $content .= '<rexstan-trigger class="btn btn-xs btn-default btn-tip" event="rexstan:tip" title="' . rex_i18n::msg('igor_rexstan_analysis_tip') . '"><i class="fa fa-lightbulb-o"></i></rexstan-trigger> ';
    $content .= '<span class="rexstan-message-text">' . $pasteButton . $text . '</span>';
    $content .= $tip;
    $content .= '</rexstan-message>';
}

/**
 * ohne file-Angabe: nur den Code für die Messages ausgeben, keine Section mit Header.
 * für: api_rexstan.
 */
if ('' === $file) {
    echo $content;
    return;
}

/**
 * HTML Button-Group für den Refresh-Button
 * $headerButtons sind CustomHTML (<rexstan-trigger>).
 */
$fragment = new rex_fragment();
$fragment->setVar('size', 'xs', false);
$fragment->setVar('buttons', [
    [
        'icon' => 'refresh',
        'attributes' => [
            'class' => ['btn-default'],
            'from' => '<<rexstan-messages',
            'event' => 'rexstan:refresh',
            'title' => rex_i18n::msg('igor_rexstan_analysis_refresh'),
        ],
    ],
], false);
$headerButtons = $fragment->parse('core/buttons/button_group.php');
$headerButtons = str_replace(['<button ', '</button>'], ['<rexstan-trigger ', '</rexstan-trigger>'], $headerButtons);

/**
 * Fragment ausgeben.
 */
$fragment = new rex_fragment();

$fragment->setVar('sectionAttributes', [
    'data-name' => $file,
], false);

$fragment->setVar('options', $headerButtons, false);

$fragment->setVar('title',
    '<span class="text-muted">' . rex_escape(dirname($file)) . DIRECTORY_SEPARATOR . '</span><strong>' . rex_escape(basename($file)) . '</strong>&nbsp;' .
    '<rexstan-amount class="badge" target="<<.panel > div" filter=":scope > rexstan-message"></rexstan-amount>&nbsp;' .
    '<rexstan-amount class="badge rexstan-badge-success" target="<<.panel > div" filter=":scope > rexstan-message.rexstan-search-hit" pattern="<i class=&quot;rex-icon rex-icon-search&quot;></i> = #" options="{&quot;childList&quot;:true,&quot;subtree&quot;:true,&quot;attributeFilter&quot;:[&quot;rexstan-search-hit&quot;]}"></rexstan-amount>',
    false);

$fragment->setVar('collapse', true);
$fragment->setVar('collapsed', true); // die tatsächliche Anzeige wird im JS gesteuert.

$fragment->setVar('content', $content, false);
$HTML = $fragment->parse('core/page/section.php');

$leadIn = strpos($HTML, '<section');
$leadOut = strrpos($HTML, '</section>');

if (is_int($leadIn) && is_int($leadOut)) {
    $HTML = substr_replace($HTML, '</rexstan-messages>', $leadOut, 10);
    $HTML = substr_replace($HTML, '<rexstan-messages', $leadIn, 8);
}

echo $HTML;
