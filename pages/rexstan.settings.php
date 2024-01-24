<?php
/**
 * Ersetzt die Seite rexstan/settings.
 *
 * Aufbau des Formulars und Speichern der Eingaben sind komplett nach Project\Rexstan\RexStanConfig verlagert.
 */

namespace Igor\Rexstan;

use rex_fragment;
use rex_i18n;
use rex_url;

/**
 * Hinweis auf die überlagerte Seite.
 */
echo '<small class="text-danger"><i class="fa fa-warning text-info"></i> ',rex_i18n::msg('igor_rexstan_replaced_warning', 'Settings'),'</small>';

/**
 * Inhalte holen.
 */
$form = RexStanConfig::factory('rexstan');
$faqUrl = rex_url::backendPage('rexstan/faq');
$navigation = $form->tabsetNavigation(true);

/**
 * Formatierte Ausgabe als section/panel.
 */
$fragment = new rex_fragment();
$fragment->setVar('options', '<a class="btn btn-info" href="'. $faqUrl .'">'.rex_i18n::msg('igor_rexstan_see_faq').'</a>', false);
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', '<p>'.rex_i18n::msg('igor_rexstan_settings').'</p>'.$navigation, false);
$fragment->setVar('body', $form->get(), false);
echo $fragment->parse('core/page/section.php');
