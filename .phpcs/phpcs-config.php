<?php
/**
 * PHPCS Configuration for Uncanny Automator
 *
 * This file contains all custom configurations for PHP_CodeSniffer rules.
 *
 * @package Uncanny_Automator
 */

return array(
	// Text domain configuration
	'text_domains' => array(
		// Core text domains that are always valid
		'core' => array(
			'uncanny-automator',
			'uncanny-automator-pro',
		),
		// Additional text domains for specific integrations/add-ons
		'additional' => array(
			'my-automator-addon',        // Example add-on text domain
			'automator-integration-xyz', // Example integration text domain
		),
		// Text domain patterns (regex) for dynamic validation
		'patterns' => array(
			'/^automator-/',           // Allow any text domain starting with 'automator-'
			'/^uncanny-/',            // Allow any text domain starting with 'uncanny-'
		),
	),

	// Sentence case exceptions
	'sentence_case' => array(
		// Product Names
		'Uncanny Owl',
		'Uncanny Automator',
		'Uncanny Automator Pro',
		'Automator',
		'Automator Pro',
		
		// Feature Names
		'Recipe',
		'Recipes',
		'Trigger',
		'Triggers',
		'Action',
		'Actions',
		'Token',
		'Tokens',
		'Integration',
		'Integrations',
		
		// UI Elements
		'Dashboard',
		'Settings',
		'Tools',
		'Logs',
		'Activity Log',
		'Recipe Log',
		
		// Technical Terms
		'REST API',
		'OAuth',
		'OAuth2',
		'Webhook',
		'Webhooks',
		'JSON',
		'Base64',
		
		// Add your project-specific exceptions here
	),

	// Sniff-specific settings
	'settings' => array(
		'string_quoting' => array(
			'prefer_double_quotes' => true,
			'allow_single_quotes_for_simple_strings' => true,
		),
		'translator_comment' => array(
			'require_for_placeholders' => true,
			'require_for_html' => true,
		),
	),
); 