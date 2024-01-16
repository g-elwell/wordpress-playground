<?php

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Brain\Monkey\Functions;

/**
 * An abstraction over WP_Mock to do things fast
 * It also uses the snapshot trait
 */
class PluginTestCase extends \PHPUnit\Framework\TestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * Setup which calls \WP_Mock setup
	 *
	 * @return void
	 */
	protected function setUp() : void {
		Monkey\setUp();
		parent::setUp();
		global $post;

		/**
		 * Mock WP translation function and return first argument.
		 */
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'esc_attr' )->returnArg( 1 );
		Functions\when( 'esc_html' )->returnArg( 1 );

        /**
         * Top_Walker extends core WordPerss Walker_Nav_Menu
         * class, so we need to mock it to allow it to be available.
         */
		$this->getMockBuilder( 'Walker_Nav_Menu' )
			->setMethods( [ 'start_el' ] )
			->disableArgumentCloning()
			->getMock();
	}

	/**
	 * Teardown which calls \WP_Mock tearDown
	 *
	 * @return void
	 */
	protected function tearDown() : void {
		Monkey\tearDown();
		parent::tearDown();
		global $post;
		$post = null;
	}
}
