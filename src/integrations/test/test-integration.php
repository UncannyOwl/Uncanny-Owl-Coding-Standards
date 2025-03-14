<?php

namespace Uncanny_Automator;

/**
 * Test Integration
 *
 * @package Uncanny_Automator
 * @since   1.0.0
 */
class Test_Integration {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public $integration = 'TEST';

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->setup_strings();
	}

	/**
	 * Set up various strings to test our sentence case sniff
	 *
	 * @return void
	 */
	private function setup_strings() {
		define( 'TEST_INTEGRATION', true );

		// This should pass - no placeholders, no translator comment needed
		esc_html__( 'This is a correct sentence case string', 'uncanny-automator' );

		// This should pass - has context
		esc_html_x( 'This is a correct sentence case string', 'Label for settings field', 'uncanny-automator' );

		// This should fail - Title Case
		esc_html__( 'This is not correct title case', 'uncanny-automator' );

		// This should only fail for Title Case, but has proper context
		esc_html_x( 'This is not correct title case', 'Button label', 'uncanny-automator' );

		// This should pass - proper case with special terms
		esc_html__( 'Send WordPress REST API request', 'uncanny-automator' );

		// This should pass - has context and proper case with special terms
		esc_html_x( 'Send WordPress REST API request', 'API connection button label', 'uncanny-automator' );

		// This should pass - proper case with days and months
		esc_html__( 'Schedule for Monday in January', 'uncanny-automator' );

		// This should pass - has context
		esc_html_x( 'Schedule for Monday in January', 'Calendar scheduling option', 'uncanny-automator' );

		// This should fail - incorrect capitalization
		esc_html__( 'Send email to user', 'uncanny-automator' );

		// This should only fail for capitalization, has proper context
		esc_html_x( 'Send email to user', 'Action button label', 'uncanny-automator' );

		// This should pass - URL with context
		esc_html_x( 'Visit https://UncannyOwl.com/Docs', 'Documentation link text', 'uncanny-automator' );

		// This should fail - escaped single quote
		esc_html__( "User's Profile & Settings", 'uncanny-automator' );

		// This should pass - proper quotes and has context
		esc_html_x( "User's profile and settings", 'Profile page title', 'uncanny-automator' );

		// This should fail - escaped quotes
		esc_html__( "The user's and admin's settings", 'uncanny-automator' );

		// This should pass - proper quotes and has context
		esc_html_x( "The user's and admin's settings", 'Settings page description', 'uncanny-automator' );

		// translators: %1$s is the user's name, %2$s is their role
		esc_html__( 'Welcome %1$s, you are logged in as %2$s', 'uncanny-automator' );

		// This should pass - no placeholders, no translator comment needed
		esc_html__( 'Simple string without placeholders', 'uncanny-automator' );
	}
}
