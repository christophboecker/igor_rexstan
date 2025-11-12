<?php
/**
 * Test für den Workflow ...
 *
 * Nur zulässig wenn als Admin angemeldet.
 *
 * Hier kein Namespace, da sonst die API-Klasse nicht gefunden wird.
 *
 * Rückgaben sind zunächst HTTP-Fehlercodes:
 *      HTTP_FORBIDDEN          Kein Zugriffsberechtigung
 *      HTTP_BAD_REQUEST        Irgendwas mit den Parametern
 *      HTTP_OK                 weitere Details im Content
 *
 * Im Falle HTTP_OK enthält der Response ein Content-Array (JSON)
 *      rc          0 => keine Messages
 *                  1 => Fehler beim Ablauf
 *                  2 => Messages gefunden
 *      content     rc=0 => leer
 *                  rc=1 => phpstan-Meldung mit rex_view::error veepackt.
 *                  rc=2 => die gefundenen Meldungen (HTML)
 */

use ChristophBoecker\IgorRexstan\RexStanConfig;
use rex;
use rex_api_function;
use rex_fragment;
use rex_i18n;
use rex_path;
use rex_request;
use rex_response;
use rex_view;
use FriendsOfRedaxo\Rexstan\RexStan;

class rex_api_rexstan extends rex_api_function
{
    /**
     * @return never
     */
    public function execute(): void
    {
        $this->assureSameOrigin();
        $this->assureAccessRights();

        $action = rex_request::request('action', 'int', 0);
        switch ($action) {
            case 1:
                // Zieldatei abrufen
                $target = $this->getTargetFile();

                /**
                 * aktuelle Einstellung der Ziele (Addons) aus rex_config sichern.
                 */
                $addonPaths = rex_config::get('rexstan', 'addons', '');

                /**
                 * Aktion mit try..finally absichern.
                 */
                try {
                    /**
                     * $target als alleiniges Ziel setzen (Addons in rex_config)
                     * User-Config-Datei damit neu schreiben.
                     */
                    rex_config::set('rexstan', 'addons', $target);
                    RexStanConfig::config2neon();

                    /**
                     * Analyse durchführen.
                     * @var string|array{totals:array{errors:int,file_errors:int},files?:array<string,array{errors:int,messages:array<int,array{message:string,line:int,ignorable:bool,tip?:string}>}>,errors:string[]}
                     */
                    $phpstanResult = RexStan::runFromWeb();

                    /**
                     * Ziel wieder zurücksetzen in rex_config
                     * User-Config-Datei damit neu schreiben.
                     */
                    rex_config::set('rexstan', 'addons', $addonPaths);
                    RexStanConfig::config2neon();

                    // phpstan-Fehlermeldung weiterleiten
                    if (is_string($phpstanResult)) {
                        $error = rex_view::error('<h4>' . rex_i18n::msg('igor_rexstan_error_header') . '</h4>' . nl2br($phpstanResult));
                        $this->response(1, $error);
                    }
                    // phpstan findet keine Meldungen mehr
                    $fileResult = $phpstanResult['files'][$target] ?? [];
                    if (0 === count($fileResult)) {
                        $this->response(0, '');
                    }

                    // Ergebnis senden
                    $fragment = new rex_fragment();
                    $linkFile = preg_replace('/\s\(in context.*?$/', '', $target);
                    $fragment->setVar('showTip', '|1|' === rex_config::get('rexstan', 'tip', '|1|'));
                    $fragment->setVar('link', $linkFile, false);
                    $fragment->setVar('result', $fileResult, false);
                    $html = $fragment->parse('analysis_items.php');
                    $this->response(2, $html);
                } catch (Throwable $th) {
                    /**
                     * für alle Fälle auch hier noch mal.
                     */
                    rex_config::set('rexstan', 'addons', $addonPaths);
                    RexStanConfig::config2neon();
                    $this->abort(rex_response::HTTP_INTERNAL_ERROR, $th->getMessage());
                }
        }
        $this->abort(rex_response::HTTP_BAD_REQUEST, '0');
    }

    /**
     * Prüft, ob die abrufende Seiten von diesem Server kam
     * Ansonsten HTTP_SERVICE_UNAVAILABLE-Abbruch.
     */
    private function assureSameOrigin(): void
    {
        $httpReferer = rex_request::server('HTTP_REFERER', 'string', '');
        $httpHost = rex_request::server('HTTP_HOST', 'string', '');
        if ('' !== $httpReferer && parse_url($httpReferer, PHP_URL_HOST) !== $httpHost) {
            $this->abort(rex_response::HTTP_SERVICE_UNAVAILABLE);
        }
    }

    /**
     * Überprüft die Berechtigung (angemeldeter Admin im Backend)
     * Fehlende Berechtigung führt zu einem HTTP_FORBIDDEN-Abbruch.
     */
    private function assureAccessRights(): void
    {
        $user = rex::getUser();
        if (rex::isFrontend() || null === $user || !$user->isAdmin()) {
            $this->abort(rex_response::HTTP_FORBIDDEN);
        }
    }

    /**
     * ermittelt aus $_REQUEST['target'] den Pfadnamen der Target-Datei
     * Ungültige Namen oder fehlende Datei führen zu einem HTTP_BAD_REQUEST-Abbruch.
     */
    private function getTargetFile(): string
    {
        $target = trim(rex_request::request('target', 'string', ''));
        if ('' === $target) {
            $this->abort(rex_response::HTTP_BAD_REQUEST, '1');
        }
        $target = rex_path::src('addons/' . $target);
        $realPath = $this->normalizePath($target);
        if ($realPath !== $target) {
            $this->abort(rex_response::HTTP_BAD_REQUEST, '2');
        }
        return $target;
    }

    /**
     * Schickt eine valide Antwort mit HTTP-Code 200 als JSON-Array.
     * @return never
     */
    private function response(int $returnCode, string $content): void
    {
        $result = [
            'rc' => $returnCode,
            'content' => $content,
        ];
        rex_response::cleanOutputBuffers();
        rex_response::setStatus(rex_response::HTTP_OK);
        rex_response::sendJson($result);
        exit;
    }

    /**
     * Schickt eine Fehlermeldung mit einem HTTP-Code != 200 und bricht dann ab.
     * @return never
     */
    private function abort(string $http_code, string $message = ''): void
    {
        rex_response::cleanOutputBuffers();
        rex_response::setStatus($http_code);
        rex_response::sendContent($http_code . ('' < $message ? ' / ' . $message : ''));
        exit;
    }

    /**
     * entfernt ~, ./ und ../
     * realPath würde zusätzlich Symlinks auflösen, dass ist hier nicht gewünscht.
     */
    private function normalizePath(string $path): string
    {
        $patterns = ['~/{2,}~', '~/(\./)+~', '~([^/\.]+/(?R)*\.{2,}/)~', '~\.\./~'];
        $replacements = ['/', '/', '', ''];
        return (string) preg_replace($patterns, $replacements, $path);
    }
}
