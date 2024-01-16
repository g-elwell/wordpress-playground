<?php

namespace Tests;

use \Brain\Monkey\Functions;

final class ExampleTest extends \PluginTestCase {
	public function setUp() : void {
        global $post;
		parent::setUp();

        // Build up an WP_Post object.
        $this->wp_term = $this->getMockBuilder( 'WP_Term' )
			->disableArgumentCloning()
			->getMock();

        Functions\when( 'in_the_loop' )->justReturn( true );

        Functions\when( 'get_post_time' )->justReturn( '2021-02-10T10:20:30Z' );
        Functions\when( 'get_the_date' )->justReturn( 'February 10th 2021' );
	}

    public function testExampleInt(): void
    {
        $this->assertIsInt((int) '123');
    }
}
