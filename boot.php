<?php

namespace Igor\Rexstan;

use rex;
use rex_addon;
use rex_view;

/**
 * @var rex_addon $this
 */

if (rex::isBackend() && 'rexstan' === \rex_be_controller::getCurrentPagePart(1)) {
    rex_view::addCssFile($this->getAssetsUrl('style.min.css'));
    rex_view::addJsFile($this->getAssetsUrl('script.js'));
}
