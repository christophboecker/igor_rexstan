<?php

namespace Igor\Rexstan;

use rex_config;

/**
 * De-Installiere die zusätzlichen Config-Einträge im RexStan-Addon.
 */
rex_config::remove('rexstan', 'dont_analyse');
rex_config::remove('rexstan', 'dont_scan');
rex_config::remove('rexstan', 'clear_phpstan_cache');
rex_config::remove('rexstan', 'open');
rex_config::remove('rexstan', 'tip_key');
rex_config::remove('rexstan', 'tip');
