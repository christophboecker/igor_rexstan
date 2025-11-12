<?php
/**
 * Das Config-Formular basiert auf rex_config_form und speichert die Eingaben primär
 * in rex_config. Die überschriebenen und zusätzlichen Methoden sind wie folgt genutzt:.
 *
 * __construct:     Eröffnet das Formular immer auf dem Namespace "rexstan".
 *                  Holt diverse Formularoptionen aus RexStan\RexStanSettings, um bei neuen
 *                  RexStan-Versionen stets aktuelle Daten zu haben
 * init:            baut das Formular auf, fügt also alle Felder etc. hinzu
 * save:            Normiert einzelne Eingaben, bevor sie von parent::save nach rex_config
 *                  geschrieben werden. Ruft dann config2neon auf.
 * config2neon      Schreibt die Daten aus der rex_config in die RexStan-User-Config zur
 *                  Nuzung durch PhpStan. Achtung: erweiterter Datenumfang!
 */

namespace ChristophBoecker\IgorRexstan;

use Exception;
use ReflectionMethod;
use ReflectionProperty;
use rex;
use rex_addon;
use rex_config;
use rex_config_form;
use rex_developer_manager;
use rex_editor;
use rex_file;
use rex_finder;
use rex_fragment;
use rex_i18n;
use rex_package;
use rex_path;
use rex_string;
use rex_version;
use FriendsOfRedaxo\Rexstan\RexStanSettings;
use FriendsOfRedaxo\Rexstan\RexStanUserConfig;

use function count;
use function is_array;
use function is_int;

use const PHP_EOL;
use const PHP_INT_MIN;
use const PHP_VERSION_ID;

class RexStanConfig extends rex_config_form
{
    private const NAMESPACE = 'rexstan';

    /** @var array<string,string> */
    private static $phpstanExtensions = [];

    /** @var array<string,string> */
    private static $phpstanExtensionDocLinks = [];

    /** @var array<int,string> */
    private static $phpVersionList = [];

    /** @var array<int,string> */
    private $tabTitle = [];
    private int $activeTab = PHP_INT_MIN;
    private string $tabNavId = '';

    /**
     * Derr Namespace ist immer 'rexstan' (self::NAMESPACE)!
     * Und aus RexstanSettings die Formulardaten (private ...) übernehmen.
     *
     * @param string      $namespace ignorieren und durch self::NAMESPACE ersetzen
     * @param string|null $fieldset  ignorieren. class:.init macht den Job.
     * @param bool        $debug
     */
    protected function __construct(string $namespace, $fieldset = null, $debug = false)
    {
        $namespace = self::NAMESPACE; // just to make RexStan happy
        $fieldset = null; // just to make RexStan happy
        parent::__construct($namespace, $fieldset, $debug);

        /**
         * Tab-Titel vorbelegen.
         */
        $this->tabTitle = explode('|', rex_i18n::msg('igor_rexstan_sf_tabs'));
        $this->tabNavId = sprintf('%s-%d', $this->getName(), random_int(100000, 999999));

        /**
         * Formularparameter im aktuellen Stand aus RexstanSettings auslesen.
         */
        $property = new ReflectionProperty(RexStanSettings::class, 'phpstanExtensions');
        $property->setAccessible(true);
        /**
         * STAN: Static property Project\Rexstan\RexStanConfig::$phpstanExtensions (array<string, string>) does not accept mixed.
         * Über die Bande gespielt (Zwischenspeichern in $data) verschwindet die Meldung.
         * @var array<string,string> $data
         */
        $data = $property->getValue(null);
        self::$phpstanExtensions = $data;

        $property = new ReflectionProperty(RexStanSettings::class, 'phpstanExtensionDocLinks');
        $property->setAccessible(true);
        /**
         * STAN: Static property Project\Rexstan\RexStanConfig::$phpstanExtensionDocLinks (array<string, string>) does not accept mixed.
         * Über die Bande gespielt (Zwischenspeichern in $data) verschwindet die Meldung.
         * @var array<string,string> $data
         */
        $data = $property->getValue(null);
        self::$phpstanExtensionDocLinks = $data;

        if (rex_version::compare(rex::getVersion(), '5.15.0-dev', '>=')) {
            // $phpVersions = self::$phpVersionListFrom5_15;
            $property = new ReflectionProperty(RexStanSettings::class, 'phpVersionListFrom5_15');
        } else {
            // $phpVersions = self::$phpVersionListUpTp5_14;
            $property = new ReflectionProperty(RexStanSettings::class, 'phpVersionListUpTp5_14');
        }
        // $property = new ReflectionProperty(RexStanSettings::class, 'phpVersionList');
        $property->setAccessible(true);
        /**
         * STAN: Static property Project\Rexstan\RexStanConfig::$phpVersionList (array<int, string>) does not accept mixed.
         * Über die Bande gespielt (Zwischenspeichern in $data) verschwindet die Meldung.
         * @var array<int,string> $data
         */
        $data = $property->getValue(null);
        self::$phpVersionList = $data;
    }

    public function init(): void
    {
        parent::init();

        $extensions = [];
        foreach (self::$phpstanExtensions as $label => $path) {
            $extensions[RexStanSettings::relativePath(rex_path::addon('rexstan', $path))] = $label;
        }

        $extensionLinks = [];
        foreach (self::$phpstanExtensionDocLinks as $label => $link) {
            $extensionLinks[] = '<a href="' . $link . '">' . $label . '</a>';
        }

        $sapiVersion = (int) (PHP_VERSION_ID / 100);
        $cliVersion = (int) shell_exec('php -r \'echo PHP_VERSION_ID;\'');
        $cliVersion = (int) ($cliVersion / 100);

        $phpVersions = self::$phpVersionList;
        foreach ($phpVersions as $key => &$label) {
            $key = (int) ($key / 100);

            if ($key === $sapiVersion) {
                $label .= ' [' . rex_i18n::msg('igor_rexstan_version_sapi') . ']';
            }
            if ($key === $cliVersion) {
                $label .= ' [' . rex_i18n::msg('igor_rexstan_version_cli') . ']';
            }
        }

        $this->addRawField('<rexstan-tabset data-navigation="' . $this->tabNavId . '"><div id="' . md5($this->tabTitle[0]) . '" class="tab-pane fade">');

        $field = $this->addInputField('number', 'level', null, ['class' => 'form-control', 'min' => 0, 'max' => 9]);
        $field->setLabel(rex_i18n::msg('igor_rexstan_sf_level_label'));
        $field->setNotice(rex_i18n::rawMsg('igor_rexstan_sf_level_notice'));

        $field = $this->addSelectField('extensions', null, ['class' => 'form-control selectpicker']);
        $field->setAttribute('multiple', 'multiple');
        $field->setLabel(rex_i18n::msg('igor_rexstan_sf_extensions_label'));
        $field->setNotice(rex_i18n::rawMsg('igor_rexstan_sf_extensions_notice', implode(', ', $extensionLinks)));
        $select = $field->getSelect();
        $select->addOptions($extensions);

        $field = $this->addSelectField('phpversion', null, ['class' => 'form-control selectpicker']);
        $field->setLabel(rex_i18n::msg('igor_rexstan_sf_php_label'));
        $field->setNotice(rex_i18n::rawMsg('igor_rexstan_sf_php_notice'));
        $select = $field->getSelect();
        $select->addOptions($phpVersions);

        $field = $this->addCheckboxField('baseline');
        $field->setLabel(rex_i18n::msg('igor_rexstan_sf_baseline_label'));
        $field->addOption(rex_i18n::msg('igor_rexstan_sf_baseline_choice_1'), RexStanSettings::BASELINE_ENABLED);
        $field->addOption(rex_i18n::msg('igor_rexstan_sf_baseline_choice_2'), RexStanSettings::BASELINE_REPORT_UNMATCHED);
        $baselineFile = RexStanSettings::getAnalysisBaselinePath();
        $url = rex_editor::factory()->getUrl($baselineFile, 0);
        $baselineButton = null !== $url ? '<a href="' . $url . '">Baseline im Editor &ouml;ffnen</a> - ' : '';
        $field->setNotice(rex_i18n::rawMsg('igor_rexstan_sf_baseline_notice', $baselineButton));

        $this->addRawField('</div><div id="' . md5($this->tabTitle[1]) . '" class="tab-pane fade">');

        $field = $this->addSelectField('addons', null, ['class' => 'form-control selectpicker', 'data-live-search' => 'true', 'required' => 'required']); // die Klasse selectpicker aktiviert den Selectpicker von Bootstrap
        $field->setAttribute('multiple', 'multiple');
        $field->setLabel(rex_i18n::msg('igor_rexstan_sf_addons_label'));
        $select = $field->getSelect();
        foreach (rex_addon::getAvailableAddons() as $availableAddon) {
            $availablePlugins = $availableAddon->getAvailablePlugins();
            $optGroup = 0 < count($availablePlugins) || 'developer' === $availableAddon->getName();
            if ($optGroup) {
                $select->addOptgroup($availableAddon->getName());
            }
            $select->addOption($availableAddon->getName(), RexStanSettings::relativePath($availableAddon->getPath()));
            if ($optGroup) {
                foreach ($availablePlugins as $availablePlugin) {
                    $select->addOption($availableAddon->getName() . ' ⇒ ' . $availablePlugin->getName(), RexStanSettings::relativePath($availablePlugin->getPath()));
                }
                if ('developer' === $availableAddon->getName() && class_exists(rex_developer_manager::class)) {
                    $select->addOption('developer: modules', RexStanSettings::relativePath(rex_developer_manager::getBasePath() . '/modules/'));
                    $select->addOption('developer: templates', RexStanSettings::relativePath(rex_developer_manager::getBasePath() . '/templates/'));
                }
                $select->endOptgroup();
            }
        }

        $field = $this->addTextAreaField('dont_analyse', null, ['class' => 'form-control', 'rows' => 3]);
        $field->setLabel(rex_i18n::msg('igor_rexstan_sf_exclude_label'));
        $field->setNotice(rex_i18n::rawMsg('igor_rexstan_sf_exclude_notice'));

        $field = $this->addTextAreaField('dont_scan', null, ['class' => 'form-control', 'rows' => 3]);
        $field->setLabel(rex_i18n::msg('igor_rexstan_sf_skip_label'));
        $field->setNotice(rex_i18n::rawMsg('igor_rexstan_sf_skip_notice'));

        //        $baselineFile = RexStanSettings::getAnalysisBaselinePath();
        //        $url = rex_editor::factory()->getUrl($baselineFile, 0);
        //        $baselineButton = null === $url ? '' : sprintf('<a href="%s">%s</a> | ', $url, rex_i18n::msg('igor_rexstan_sf_baseline_editor'));
        //        $field = $this->addCheckboxField('baseline');
        //        $field->setLabel(rex_i18n::msg('igor_rexstan_sf_baseline_label'));
        //        $field->addOption(rex_i18n::msg('igor_rexstan_sf_baseline_label'), 1);
        //        $field->setNotice($baselineButton . rex_i18n::rawMsg('igor_rexstan_sf_baseline_notice')); // 'Weiterlesen: <a href="https://phpstan.org/user-guide/baseline">Baseline erklärung</a>');

        $this->addRawField('</div><div id="' . md5($this->tabTitle[2]) . '" class="tab-pane fade">');

        $field = $this->addSelectField('clear_phpstan_cache', null, ['class' => 'form-control selectpicker']); // die Klasse selectpicker aktiviert den Selectpicker von Bootstrap
        $field->setLabel(rex_i18n::msg('igor_rexstan_sf_clearcache'));
        $select = $field->getSelect();
        $select->addOptions(explode('|', rex_i18n::rawMsg('igor_rexstan_sf_clearcache_options')));

        $field = $this->addCheckboxField('open');
        $field->setLabel(rex_i18n::msg('igor_rexstan_sf_collapse_label'));
        $field->addOption(rex_i18n::msg('igor_rexstan_sf_collapse_option'), '1');

        $field = $this->addInputField('text', 'tip_key', null, ['class' => 'form-control', 'required' => 'required']);
        $field->setLabel(rex_i18n::msg('igor_rexstan_sf_tipkey_label'));
        $field->setNotice(rex_i18n::msg('igor_rexstan_sf_tipkey_notice'));

        $field = $this->addCheckboxField('tip');
        $field->setLabel(rex_i18n::msg('igor_rexstan_sf_tip'));
        $field->addOption(rex_i18n::msg('igor_rexstan_sf_tip_visible'), '1');

        $this->addRawField('</div></rexstan-tabset>');
    }

    /**
     * vor dem Speichern durch rex_config_form (parent)
     *    - die Testarea-Felder bereinigen
     *    - bei Choices "leer" als "" statt null
     * nach dem Speichern durch rex_config_form (parent)
     *    - die Daten in die RexStan-Benutzerkonfiguration übertragen.
     */
    protected function save(): bool|int|string
    {
        foreach ($this->getSaveElements() as $fieldsetElements) {
            foreach ($fieldsetElements as $element) {
                $fieldName = $element->getFieldName();
                if ('dont_analyse' === $fieldName || 'dont_scan' === $fieldName) {
                    $fieldValue = (string) ($element->getValue() ?? '');
                    $value = preg_split('/[\r\n]+/', $fieldValue);
                    if (is_array($value)) {
                        $value = self::normalizeArray($value);
                    } else {
                        $value = [];
                    }
                    $fieldValue = implode(PHP_EOL, $value);
                    $element->setValue($fieldValue);
                    continue;
                }
                if ('open' === $fieldName || 'tip' === $fieldName) {
                    $element->setValue($element->getValue() ?? '');
                    continue;
                }
            }
        }

        // Speichert in der Datenbank rex_config (rex_config::set(...))
        $result = parent::save();

        // gespeicherte rex_config-Daten für RexStan aufbereiten
        // Basis: normierte Werte in der config-DB
        if (true === $result) {
            $result = self::config2neon();
            $result = '' === $result ? true : $result;
        }

        return $result;
    }

    /**
     * Erzeugt aus den in rex_config gespeicherten Parametern die user-config.neon.
     *
     * Übergeht RexStanUserConfig, da hier im Plugin ein paar mehr Parameter berücksichtigt werden.
     * Der Dateiname wird aus RexStanUserConfig ausgelesen.
     *
     * Rückgabe ist entweder eine Fehlermeldung oder ein leerer String.
     *
     * Wenn das Addon Plugins hat:
     *
     * @api
     */
    public static function config2neon(): string
    {
        $neon = ['includes' => [], 'parameters' => []];

        /**
         * immer dabei seit rexstan 1.0.???
         */
        $neon['parameters']['scanDirectories'] = [];
        foreach (rex_package::getAvailablePackages() as $package) {
            $functionsPath = $package->getPath('functions/');
            if (is_dir($functionsPath)) {
                $neon['parameters']['scanDirectories'][] = RexStanSettings::relativePath($functionsPath);
            }
            $functionsPath = $package->getPath('vendor/');
            if (is_dir($functionsPath)) {
                $neon['parameters']['scanDirectories'][] = RexStanSettings::relativePath($functionsPath);
            }
        }

        /**
         * STAN: Stand heute (REDAXO 5.14.1) liefert rex_config::get mixed zurück. Deshalb muss der Typ für PhpStan präzisiert werden.
         * Erledigt sich vieleicht, wenn REDAXO als Return-Type nicht mehr mixed nimmt.
         * @var string $extensions
         */
        $extensions = rex_config::get(self::NAMESPACE, 'extensions', '');
        $includes = explode('|', $extensions);
        $neon['includes'] = self::normalizeArray($includes);

        $value = rex_config::get(self::NAMESPACE, 'level', 0);
        $neon['parameters']['level'] = is_numeric($value) ? (int) $value : 0;

        $value = rex_config::get(self::NAMESPACE, 'phpversion', array_key_first(self::$phpVersionList));
        $neon['parameters']['phpVersion'] = is_numeric($value) ? (int) $value : array_key_first(self::$phpVersionList);

        /**
         * STAN: Stand heute (REDAXO 5.14.1) liefert rex_config::get mixed zurück. Deshalb muss der Typ für PhpStan präzisiert werden.
         * Erledigt sich vieleicht, wenn REDAXO als Return-Type nicht mehr mixed nimmt.
         * @var string $addonPaths
         */
        $addonPaths = rex_config::get(self::NAMESPACE, 'addons', '');
        $paths = explode('|', $addonPaths);
        $neon['parameters']['paths'] = self::normalizeArray($paths);

        /**
         * Alle Pfade auf Addons ausfindig machen, die ein Plugin-Verzeichnis haben
         * - Alle Pfade auf Plugins ausfindig machen
         * - Wenn eines der gefundenen Addons auch in der Liste der Plugin-Pfade vorkommt:
         *   => alle anderen Plugins ausschließen
         * - Wenn das Addon allein ausgewählt wurde:
         *   => den kompletten Plugin-Ordner ausschließen.
         */
        // relative Pfade in echte ändern
        $absoluteAddonPaths = array_map(static function ($v) {
            return '/' . rex_path::absolute(rex_path::addonData('rexstan') . $v) . '/';
        }, $neon['parameters']['paths']);
        $addonsWithPlugins = array_filter($absoluteAddonPaths, static function ($v) {
            //            dump([$v, $v.'plugins', is_dir($v.'plugins')]);
            return is_dir($v . 'plugins');
        });
        $plugins = array_filter($absoluteAddonPaths, static function ($v) {
            return is_int(stripos($v, '/plugins/'));
        });

        $pluginExcludes = [];
        foreach ($addonsWithPlugins as $addon) {
            $selectedPlugins = array_filter($plugins, static function ($v) use ($addon) {
                return str_starts_with($v, $addon);
            });
            if (0 === count($selectedPlugins)) {
                $pluginExcludes[] = $addon . 'plugins/';
            } else {
                $pluginFinder = rex_finder::factory($addon . 'plugins')->dirsOnly()->ignoreSystemStuff();
                $addonPlugins = [];
                foreach ($pluginFinder as $plugin) {
                    $addonPlugins[] = $plugin->getPathname() . '/';
                }
                $addonPlugins = array_diff($addonPlugins, $selectedPlugins);
                $pluginExcludes = array_merge($pluginExcludes, $addonPlugins);
            }
        }
        $excludeFromAnalyse = self::normalizePathField('dont_analyse');
        $excludeFromAnalyse = array_merge($excludeFromAnalyse, $pluginExcludes);
        $excludeFromAnalyse = array_unique($excludeFromAnalyse);
        $neon['parameters']['excludePaths']['analyse'] = [];
        foreach ($excludeFromAnalyse as $path) {
            $neon['parameters']['excludePaths']['analyse'][] = RexStanSettings::relativePath($path);
        }

        $excludeFromScan = self::normalizePathField('dont_scan');
        $neon['parameters']['excludePaths']['analyseAndScan'] = [];
        foreach ($excludeFromScan as $path) {
            $neon['parameters']['excludePaths']['analyseAndScan'][] = RexStanSettings::relativePath($path);
        }

        /**
         * Baseline-Einstellungen (analog zu RexStan/Pages/Settings.php).
         */
        $baseline = rex_config::get(self::NAMESPACE, 'baseline', '');
        $baselineSettings = self::normalizeArray(explode('|', $baseline));
        if (isset($baselineSettings[RexStanSettings::BASELINE_ENABLED])) {
            $neon['includes'][] = basename(RexStanSettings::getAnalysisBaselinePath());
        }
        $neon['parameters']['reportUnmatchedIgnoredErrors'] = isset($baselineSettings[RexStanSettings::BASELINE_REPORT_UNMATCHED]);

        try {
            $property = new ReflectionMethod(RexStanUserConfig::class, 'getUserConfigPath');
            $property->setAccessible(true);
            /** @var string $UserConfigPath */
            $UserConfigPath = $property->invoke(null);
            $prefix = '# rexstan auto generated file - do not edit, rename or remove (Project/rexstan | ' . date('Y-m-d H:i:s') . ")\n\n";
            rex_file::put($UserConfigPath, $prefix . rex_string::yamlEncode($neon, 4));
        } catch (Exception $e) {
            return $e->getMessage();
        }

        return '';
    }

    /**
     * Baut aus den textarea-Feldern ein Array mit Pfadnamen auf.
     * -> Feld "dont_analyse"
     * -> Feld "dont_scan".
     *
     * Leere Zeilen und Leerzeichen an Anfang und Ende werden entfernt.
     * (Solte eigentlich überflüssig sein. Aber egal, sicher is sicher)
     *
     * Ein führendes "~"    wird in den absoluten Pfad zum BAckend der Redaxo-Instanz umgewandelt
     *                      "~/data" wird zu "/server/...../redaxoinstanz/redaxo/data"
     * Ein führendes "."    wird in den absoluten Pfad zum Addons-Verzeichnis umgewandelt (./yform/lib).
     *                      "./project/lib" wird zu "/server/...../redaxoinstanz/redaxo/addons/project/lib"
     *
     * STAN: Parameter #2 $string of function explode expects string, mixed given.
     * Stand heute (REDAXO 5.14.1) liefert rex_config::get mixed zurück. Deshalb muss der Typ für PhpStan präzisiert werden.
     * Erledigt sich vieleicht, wenn REDAXO als Return-Type nicht mehr mixed nimmt.
     *
     * @return string[]
     */
    private static function normalizePathField(string $fieldname): array
    {
        /** @var string $paths */
        $paths = rex_config::get(self::NAMESPACE, $fieldname, '');
        $paths = preg_split('/[\n\r]+/', $paths);
        $paths = is_array($paths) ? $paths : [];
        $paths = self::normalizeArray($paths);
        $paths = preg_replace_callback('/^[~\.]/', static function ($marker) {
            if ('.' === $marker[0]) {
                return rex_path::backend('src/addons');
            }
            if ('~' === $marker[0]) {
                return substr(rex_path::backend(), 0, -1);
            }
        }, $paths) ?? [];
        return $paths;
    }

    /**
     * Entfernt aus dem String-Array alle leeren und doppelten Elemente
     * und entfernt führende und abschließende Leerzeichen.
     * Indizierung fortlaufend ab 0.
     *
     * @param string[] $array
     * @return string[]
     */
    protected static function normalizeArray(array $array): array
    {
        $array = array_filter($array, 'trim');
        array_map('trim', $array);
        $array = array_unique($array);
        return array_values($array);
    }

    /**
     * Hilfsfunktion für die Tabs.
     *
     * Erzeugt das Tab-Menü. Optional wird der äußere Container wieder entfernt.
     * Der stört, wenn das Menü in einen Panel-Header kommt.
     */
    public function tabsetNavigation(bool $stripContainer = false): string
    {
        $tabs = [];
        foreach ($this->tabTitle as $k => $v) {
            $tabs[] = [
                'linkClasses' => [],
                'itemClasses' => [],
                'linkAttr' => ['data-toggle' => 'tab'],
                'itemAttr' => ['role' => 'presentation'],
                'href' => '#' . md5($v),
                'title' => $v,
                'icon' => false,
                'active' => $k === $this->activeTab,
            ];
        }
        $fragment = new rex_fragment();
        $fragment->setVar('left', $tabs, false);
        $HTML = $fragment->parse('core/navigations/content.php');
        if ($stripContainer) {
            $HTML = preg_replace(['/^<div.*?>/', '/<\/div>$/'], '', $HTML) ?? '';
        }
        return preg_replace('/^(<\w+\s)/', '$1id="' . $this->tabNavId . '"', $HTML) ?? '';
    }
}
