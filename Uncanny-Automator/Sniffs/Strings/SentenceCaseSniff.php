<?php

namespace Uncanny_Automator\Sniffs\Strings;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

/**
 * Validates sentence case in strings.
 *
 * @package Uncanny_Automator
 * @subpackage Sniffs\Strings
 */
class SentenceCaseSniff implements Sniff {

	/**
	 * Translation functions to check.
	 *
	 * @var array
	 */
	private $translation_functions = array(
		'__',
		'_e',
		'_x',
		'_ex',
		'esc_html__',
		'esc_html_e',
		'esc_html_x',
		'esc_attr__',
		'esc_attr_e',
		'esc_attr_x',
	);

	/**
	 * Words that should remain capitalized.
	 *
	 * @var array
	 */
	private $exceptions = array(
		// Core exceptions that should always be preserved
		'WordPress',
		'PHP',
		'API',
		'REST',
		'HTTP',
		'HTTPS',
		'ID',
		'URL',
		'HTML',
		'CSS',
		'JavaScript',
		'JSON',
		'XML',
		'SQL',
		'MySQL',
		'I',
		// Days
		'Monday',
		'Tuesday',
		'Wednesday',
		'Thursday',
		'Friday',
		'Saturday',
		'Sunday',
		// Months
		'January',
		'February',
		'March',
		'April',
		'May',
		'June',
		'July',
		'August',
		'September',
		'October',
		'November',
		'December',
		// Uncanny Products
		'Uncanny',
		'Uncanny Owl',
		'Uncanny Automator',
		'Uncanny Automator Pro',
		'Automator',
		'Automator Pro',
		// Service/Integration Names
		'ActiveCampaign',
		'AWeber',
		'Bitly',
		'Brevo',
		'Campaign Monitor',
		'ClickUp',
		'Constant Contact',
		'ConvertKit',
		'Discord',
		'Drip',
		'Facebook',
		'Facebook Groups',
		'Facebook Pages',
		'GetResponse',
		'Google',
		'Google Calendar',
		'Google Contacts',
		'Google Sheets',
		'GoTo Training',
		'GoTo Webinar',
		'Help Scout',
		'HubSpot',
		'Instagram',
		'Keap',
		'LinkedIn',
		'LinkedIn Pages',
		'Mailchimp',
		'MailerLite',
		'Mautic',
		'Microsoft',
		'Microsoft Teams',
		'Notion',
		'Ontraport',
		'OpenAI',
		'Sendy',
		'Slack',
		'Stripe',
		'Telegram',
		'Threads',
		'Trello',
		'Twilio',
		'WhatsApp',
		'X',
		'Twitter',
		'Zoho',
		'Zoho Campaigns',
		'Zoom',
		'Zoom Meetings',
		'Zoom Webinars',
	);

	/**
	 * Project-specific exceptions loaded from configuration.
	 *
	 * @var array
	 */
	private $project_exceptions = array();

	/**
	 * Initialize the sniff by loading project-specific exceptions.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->load_project_exceptions();
	}

	/**
	 * Load project-specific exceptions from configuration file.
	 *
	 * The configuration file should be named 'sentence-case-exceptions.php' and placed
	 * in the project root or a '.phpcs' directory. It should return an array of
	 * strings that should be preserved in their original case.
	 *
	 * Example configuration file:
	 * <?php
	 * return array(
	 *     'Uncanny Automator',
	 *     'Automator',
	 *     'Uncanny Automator Pro',
	 *     'Automator Pro',
	 *     'Uncanny Owl',
	 * );
	 *
	 * @return void
	 */
	private function load_project_exceptions() {
		$config_paths = array(
			'sentence-case-exceptions.php',
			'.phpcs/sentence-case-exceptions.php',
		);

		foreach ($config_paths as $path) {
			if (file_exists($path)) {
				$exceptions = include $path;
				if (is_array($exceptions)) {
					$this->project_exceptions = array_merge($this->project_exceptions, $exceptions);
				}
				break;
			}
		}
	}

	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return array
	 */
	public function register() {
		return array(T_CONSTANT_ENCAPSED_STRING);
	}

	/**
	 * Processes this test when one of its tokens is encountered.
	 *
	 * @param File $phpcs_file The PHP_CodeSniffer file where the token was found.
	 * @param int  $stack_ptr  The position in the PHP_CodeSniffer file's token stack where the token was found.
	 *
	 * @return void
	 */
	public function process(File $phpcs_file, $stack_ptr) {
		$tokens = $phpcs_file->getTokens();
		$token = $tokens[$stack_ptr];

		// Get the string content without quotes
		$string = trim($token['content'], "\"'");

		// Skip empty strings
		if (empty($string) || strlen($string) < 2) {
			return;
		}

		// Check if this string is part of a translation function
		$prev_token = $phpcs_file->findPrevious(T_STRING, $stack_ptr - 2, $stack_ptr - 5);
		if (false === $prev_token) {
			return;
		}

		$function_name = $tokens[$prev_token]['content'];
		if (!in_array($function_name, $this->translation_functions, true)) {
			return;
		}

		// Skip if string should not be checked
		if ($this->should_skip_string($string)) {
			return;
		}

		// Check sentence case
		$words = explode(' ', $string);
		$word_count = count($words);
		$capitalized_words = array();

		for ($i = 1; $i < $word_count; $i++) {
			$word = $words[$i];
			if (!$this->is_exception($word) && '' !== $word && ctype_upper($word[0])) {
				$capitalized_words[] = $word;
			}
		}

		if (!empty($capitalized_words)) {
			$fix = $phpcs_file->addFixableError(
				sprintf(
					'String "%s" contains incorrectly capitalized words: "%s". Use sentence case instead: "%s"',
					$string,
					implode('", "', $capitalized_words),
					$this->convert_to_sentence_case($string)
				),
				$stack_ptr,
				'CapitalizedWords'
			);

			if (true === $fix) {
				$quote = $token['content'][0];
				if ("'" === $quote && false !== strpos($string, "'")) {
					$quote = '"';
				}
				$new_content = $quote . $this->convert_to_sentence_case($string) . $quote;
				$phpcs_file->fixer->replaceToken($stack_ptr, $new_content);
			}
		}
	}

	/**
	 * Check if the string should be skipped for sentence case validation.
	 *
	 * @param string $string The string to check.
	 * @return bool
	 */
	private function should_skip_string($string) {
		// Skip URLs
		if (filter_var($string, FILTER_VALIDATE_URL)) {
			return true;
		}

		// Skip file paths
		if (false !== strpos($string, '/') || false !== strpos($string, '\\')) {
			return true;
		}

		// Skip strings with special characters, but allow apostrophes
		if (preg_match('/[^a-zA-Z0-9\s\']/', $string)) {
			return true;
		}

		return false;
	}

	/**
	 * Check if the word is an exception that should remain capitalized.
	 *
	 * @param string $word The word to check.
	 * @return bool
	 */
	private function is_exception($word) {
		// Remove any apostrophes for checking exceptions
		$word = str_replace("'", '', $word);
		
		// Check both core and project-specific exceptions
		return in_array($word, $this->exceptions, true) || in_array($word, $this->project_exceptions, true);
	}

	/**
	 * Convert a string to sentence case.
	 *
	 * @param string $string The string to convert.
	 * @return string
	 */
	private function convert_to_sentence_case($string) {
		$words = explode(' ', $string);
		
		// Capitalize first word
		$words[0] = ucfirst($words[0]);
		
		$word_count = count($words);
		
		// Convert rest to lowercase unless they're exceptions
		for ($i = 1; $i < $word_count; $i++) {
			if (!$this->is_exception($words[$i])) {
				$words[$i] = strtolower($words[$i]);
			}
		}
		
		return implode(' ', $words);
	}
} 