<?php

// define fake ABSPATH
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() );
}

if( ! defined( 'VIP_GO_ENV' ) ) {
	define( 'VIP_GO_ENV', false );
}

require_once __DIR__ . '/../../vendor/autoload.php';
// require_once __DIR__ . '/assertions/HTML.php';

// Include the class for PluginTestCase
require_once __DIR__ . '/PluginTestCase.php';

// Since our plugin files are loaded with composer, we should be good to go
