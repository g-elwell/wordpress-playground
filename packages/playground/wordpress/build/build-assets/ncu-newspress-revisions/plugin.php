<?php
/**
 * Plugin Name:       Revisions
 * Description:       An alternative method of displaying WordPress post revisions from the existing core WordPress system <a href="https://newsnet.newscorp.com" target="_blank">Powered by NewsPress</a>
 * Version:           3.4.2
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            NewsPress
 * Update URI:        https://newsnet.newscorp.com
 * Text Domain:       newspress
 */

namespace NewsPress\Revisions;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once rtrim( \dirname( __FILE__ ) ) . '/vendor/autoload_packages.php';

add_action( 'plugins_loaded', __NAMESPACE__ . '\\setup', 0 );
