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
	 * Core reserved words that should always maintain specific casing.
	 *
	 * @var array
	 */
	private $core_reserved = array(
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
	 * Project-specific reserved words loaded from configuration.
	 *
	 * @var array
	 */
	private $project_reserved = array();

	/**
	 * Initialize the sniff by loading project-specific reserved words.
	 */
	public function __construct() {
		$this->load_project_reserved();
	}

	/**
	 * Load project-specific reserved words from configuration file.
	 */
	private function load_project_reserved() {
		$config_paths = array(
			'sentence-case-exceptions.php',
			'.phpcs/sentence-case-exceptions.php',
		);

		foreach ( $config_paths as $path ) {
			if ( file_exists( $path ) ) {
				$exceptions = include $path;
				if ( is_array( $exceptions ) ) {
					$this->project_reserved = array_merge( $this->project_reserved, $exceptions );
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
		return array( T_CONSTANT_ENCAPSED_STRING );
	}

	/**
	 * Processes this test when one of its tokens is encountered.
	 *
	 * @param File $phpcs_file The PHP_CodeSniffer file where the token was found.
	 * @param int  $stack_ptr  The position in the PHP_CodeSniffer file's token stack where the token was found.
	 */
	public function process( File $phpcs_file, $stack_ptr ) {
		$tokens = $phpcs_file->getTokens();
		$token = $tokens[ $stack_ptr ];

		// Get the string content without quotes
		$string = trim( $token['content'], "\"'" );

		// Skip empty strings
		if ( empty( $string ) ) {
			return;
		}

		// Check if this string is part of a translation function
		$prev_token = $phpcs_file->findPrevious( T_STRING, $stack_ptr - 2, $stack_ptr - 5 );
		if ( false === $prev_token ) {
			return;
		}

		$function_name = $tokens[ $prev_token ]['content'];
		if ( ! in_array( $function_name, $this->translation_functions, true ) ) {
			return;
		}

		// Find all words that need case correction
		$corrections = $this->find_case_corrections( $string );

		if ( ! empty( $corrections ) ) {
			$error_msg = sprintf(
				'Reserved words have incorrect case: %s',
				implode(
					', ',
					array_map(
						function ( $word, $correct ) {
							return sprintf( '"%s" should be "%s"', $word, $correct );
						},
						array_keys( $corrections ),
						$corrections
					)
				)
			);

			$fix = $phpcs_file->addFixableError(
				$error_msg,
				$stack_ptr,
				'IncorrectReservedWordCase'
			);

			if ( $fix ) {
				$fixed_string = $this->apply_corrections( $string, $corrections );
				$phpcs_file->fixer->replaceToken(
					$stack_ptr,
					sprintf( '"%s"', $fixed_string )
				);
			}
		}
	}

	/**
	 * Find words that need case correction in a string.
	 *
	 * @param string $string The string to check.
	 * @return array Array of incorrect => correct case pairs.
	 */
	private function find_case_corrections( $string ) {
		$corrections = array();
		$words = str_word_count( $string, 1 );

		// Check for URLs and add them to corrections if not lowercase
		if ( preg_match_all( '/(https?:\/\/[^\s]+)/i', $string, $matches ) ) {
			foreach ( $matches[0] as $url ) {
				$lowercase_url = strtolower( $url );
				if ( $url !== $lowercase_url ) {
					$corrections[ $url ] = $lowercase_url;
				}
			}
			// Skip checking other words if URL is found
			return $corrections;
		}

		// Skip checking specific strings that should maintain their original case
		$skip_specific_strings = array(
			'automator_send_email',
			'Automator_send_email', // Handle both cases
		);

		foreach ($skip_specific_strings as $skip_string) {
			if (false !== stripos($string, $skip_string)) {
				// If the exact string is found, skip case correction for this entire string
				return array();
			}
		}

		// Check if string contains hook names (filter/action hooks)
		$contains_hook = false;
		$hook_patterns = array(
			'/`[a-zA-Z0-9_]+`/', // Backticked terms
			'/[`\'"]automator_[\w_]+[\'"]/', // Direct hook references
			'/apply_filters\s*\(\s*[\'"]automator_[\w_]+[\'"]/',
			'/do_action\s*\(\s*[\'"]automator_[\w_]+[\'"]/',
			'/has_filter\s*\(\s*[\'"]automator_[\w_]+[\'"]/',
			'/add_filter\s*\(\s*[\'"]automator_[\w_]+[\'"]/',
			'/add_action\s*\(\s*[\'"]automator_[\w_]+[\'"]/',
			'/remove_filter\s*\(\s*[\'"]automator_[\w_]+[\'"]/',
			'/remove_action\s*\(\s*[\'"]automator_[\w_]+[\'"]/',
		);

		// Extract all hook names and backticked terms
		$protected_terms = array();
		foreach ($hook_patterns as $pattern) {
			if (preg_match_all($pattern, $string, $matches)) {
				$contains_hook = true;
				foreach ($matches[0] as $match) {
					// Strip quotes and backticks
					$term = trim($match, '`\'"');
					$protected_terms[] = $term;
					// Also protect parts of hook names
					if (strpos($term, '_') !== false) {
						$protected_terms = array_merge($protected_terms, explode('_', $term));
					}
				}
			}
		}

		// Normalize protected terms
		$protected_terms = array_unique(array_map('trim', $protected_terms));
		
		// Intelligent modal verb detection for 'may'
		$is_modal_may = function($string, $position) {
			$words = explode(' ', strtolower($string));
			$word_count = count($words);
			
			// Find the position of 'may' in the words array
			$may_pos = -1;
			foreach ($words as $i => $word) {
				if (trim($word, '.,!?') === 'may') {
					$may_pos = $i;
					break;
				}
			}
			
			if ($may_pos === -1) {
				return false;
			}
			
			// Check if it's the first word in the sentence
			if ($may_pos === 0) {
				// If it's first, check if followed by a pronoun or article
				if ($word_count > 1) {
					$next_word = $words[$may_pos + 1];
					$pronouns = array('i', 'you', 'he', 'she', 'it', 'we', 'they');
					return in_array($next_word, $pronouns, true);
				}
				return false;
			}
			
			// Check words before 'may'
			if ($may_pos > 0) {
				$prev_word = $words[$may_pos - 1];
				// Common subjects that precede modal verbs
				$subjects = array(
					'i', 'you', 'he', 'she', 'it', 'we', 'they',
					'this', 'that', 'these', 'those',
					'user', 'users', 'recipe', 'recipes', 'trigger', 'triggers',
					'action', 'actions', 'integration', 'integrations',
					'error', 'errors', 'warning', 'warnings',
					'process', 'processes', 'system', 'systems',
					'plugin', 'plugins', 'setting', 'settings',
					'value', 'values', 'option', 'options',
					'data', 'information',
					'page', 'reloading', // Added for "reloading the page may fix"
				);
				
				if (in_array($prev_word, $subjects, true)) {
					return true;
				}
				
				// Check for determiners before potential subjects
				$determiners = array('the', 'a', 'an', 'some', 'any', 'each', 'every');
				if ($may_pos > 1 && in_array($prev_word, $determiners, true)) {
					$two_words_before = $words[$may_pos - 2];
					// Allow phrases like "reloading the page may fix"
					if ($two_words_before === 'page' || $two_words_before === 'reloading') {
						return true;
					}
					return !in_array($two_words_before, array('of', 'in', 'during', 'until'));
				}
			}
			
			// Check words after 'may'
			if ($may_pos < $word_count - 1) {
				$next_word = $words[$may_pos + 1];
				// Common verbs that follow 'may'
				$modal_following = array(
					'be', 'have', 'need', 'want', 'take', 'get', 'become',
					'appear', 'seem', 'look', 'sound', 'feel',
					'cause', 'lead', 'result', 'vary', 'differ',
					'include', 'contain', 'require', 'receive',
					'work', 'function', 'operate', 'run', 'stop',
					'start', 'begin', 'end', 'continue', 'remain',
					'change', 'affect', 'impact', 'influence',
					'not', 'also', 'still', 'already', 'now',
					'fix', // Added for "may fix"
				);
				
				if (in_array($next_word, $modal_following, true)) {
					return true;
				}
			}
			
			// Special case: Check for "may fix" pattern specifically
			if ($may_pos < $word_count - 1 && $words[$may_pos + 1] === 'fix') {
				return true;
			}
			
			return false;
		};
		
		// Find all file extensions in the string
		$file_extensions = array();
		if (preg_match_all('/\.\w+\b/i', $string, $matches)) {
			$file_extensions = array_map('strtolower', $matches[0]);
		}
		
		foreach ( $words as $word ) {
			// Skip words that are part of hook names or are backticked
			if ($contains_hook) {
				$word_lower = strtolower($word);
				foreach ($protected_terms as $protected) {
					if (strcasecmp($word, $protected) === 0) {
						continue 2; // Skip to next word
					}
					// Also check if the word is part of a protected term
					if (stripos($protected, $word_lower) !== false) {
						continue 2; // Skip to next word
					}
				}
			}
			
			// Special handling for multiplier notations (2x, 3x, 10x, etc.)
			if (preg_match('/^\d+x$/i', $word)) {
				if ($word !== strtolower($word)) {
					$corrections[$word] = strtolower($word);
				}
				continue;
			}
			
			// Special handling for 'may' vs 'May'
			if (strcasecmp($word, 'may') === 0) {
				if ($is_modal_may($string, array_search($word, $words))) {
					// In modal verb context, 'may' should remain lowercase
					if ($word !== 'may') {
						$corrections[$word] = 'may';
					}
					continue;
				}
			}

			// Check core reserved words
			foreach ( $this->core_reserved as $reserved ) {
				// Skip checking "automator" if the string contains hook names
				if ( $contains_hook && strcasecmp( $reserved, 'Automator' ) === 0 && 
					(strpos( strtolower( $word ), 'automator_' ) === 0 || strpos( $word, 'Automator_' ) === 0) ) {
					continue;
				}
				
				// Skip 'May' check if we're in a modal verb context
				if ($reserved === 'May' && strcasecmp($word, 'may') === 0 && $is_modal_may($string, array_search($word, $words))) {
					continue;
				}

				// Skip case correction if the word is part of a file extension
				$word_as_extension = '.' . strtolower($word);
				if (in_array($word_as_extension, $file_extensions, true)) {
					continue;
				}

				// Skip case correction if the word is within backticks
				if ($contains_hook && preg_match('/`[^`]*' . preg_quote($word, '/') . '[^`]*`/', $string)) {
					continue;
				}
				
				// Skip 'X' check if it's part of a multiplier notation
				if ($reserved === 'X' && preg_match('/^\d+x$/i', $word)) {
					continue;
				}
				
				if ( strcasecmp( $word, $reserved ) === 0 && $word !== $reserved ) {
					$corrections[ $word ] = $reserved;
				}
			}

			// Check project reserved words
			foreach ( $this->project_reserved as $reserved ) {
				// Skip case correction if the word is part of a file extension
				$word_as_extension = '.' . strtolower($word);
				if (in_array($word_as_extension, $file_extensions, true)) {
					continue;
				}

				if ( strcasecmp( $word, $reserved ) === 0 && $word !== $reserved ) {
					$corrections[ $word ] = $reserved;
				}
			}
		}

		return $corrections;
	}

	/**
	 * Apply case corrections to a string.
	 *
	 * @param string $string The original string.
	 * @param array  $corrections Array of incorrect => correct case pairs.
	 * @return string The corrected string.
	 */
	private function apply_corrections( $string, $corrections ) {
		$result = $string;
		foreach ( $corrections as $incorrect => $correct ) {
			$result = str_ireplace( $incorrect, $correct, $result );
		}
		return $result;
	}
} 