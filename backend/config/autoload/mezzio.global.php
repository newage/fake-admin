<?php

declare(strict_types=1);

use Laminas\ConfigAggregator\ConfigAggregator;

return [
    // Cache disabled: the config holds OAuth keys from environment variables (see oauth.global.php).
    // Toggle the configuration cache. Set this to boolean false, or remove the
    // directive, to disable configuration caching. Toggling development mode
    // will also disable it by default; clear the configuration cache using
    // `composer clear-config-cache`.
    ConfigAggregator::ENABLE_CACHE => false,

    // Enable debugging; typically used to provide debugging information within templates.
    'debug'  => false,
];
