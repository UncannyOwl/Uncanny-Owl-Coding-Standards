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

		// Skip URLs
		if (preg_match('~^https?://~i', $string) || 
			strpos($string, '.com') !== false || 
			strpos($string, '.org') !== false || 
			strpos($string, '.net') !== false || 
			strpos($string, '.edu') !== false) {
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
	 * Determine if 'may' is being used as a modal verb in the given context.
	 *
	 * @param string $string The string containing the word 'may'.
	 * @param string $word   The actual word being checked.
	 * @return bool True if it's a modal verb, false if it's likely the month name.
	 */
	private function is_modal_may($string, $word) {
		if (strtolower($word) !== 'may') {
			return false;
		}
		
		$words = preg_split('/\s+/', strtolower($string));
		$word_count = count($words);
		
		// Find the position of 'may' in the words array
		$may_pos = -1;
		foreach ($words as $i => $word_in_context) {
			if (trim($word_in_context, '.,!?:;()[]{}') === 'may') {
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
				'page', 'reloading', 'feature', 'features',
				'which', 'what', 'who', 'when', 'where', 'how', 'why',
			);
			
			if (in_array($prev_word, $subjects, true)) {
				return true;
			}
			
			// Check for determiners before potential subjects
			$determiners = array('the', 'a', 'an', 'some', 'any', 'each', 'every', 'no', 'your', 'our', 'their', 'my', 'his', 'her', 'its');
			if ($may_pos > 1 && in_array($prev_word, $determiners, true)) {
				$two_words_before = $words[$may_pos - 2];
				
				// Allow common patterns with determiners
				$noun_determiners = array('page', 'user', 'data', 'plugin', 'system', 'recipe', 'reloading', 'feature');
				if (in_array($two_words_before, $noun_determiners, true)) {
					return true;
				}
				
				return !in_array($two_words_before, array('of', 'in', 'during', 'until', 'on', 'at', 'by', 'for'));
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
				'fix', 'help', 'make', 'see', 'find', 'use', 'try',
				'wish', 'like', 'prefer', 'show', 'do',
			);
			
			if (in_array($next_word, $modal_following, true)) {
				return true;
			}
		}
		
		// Likely modal contexts based on surrounding words
		if (strpos($string, ' can ') !== false || strpos($string, ' will ') !== false || strpos($string, ' should ') !== false) {
			return true; // Probably in a modal context if other modal verbs are present
		}
		
		// Last resort: if "may" isn't at beginning of a sentence, it's likely modal
		if ($may_pos > 0 && $may_pos < $word_count - 1) {
			// Check if previous character is lowercase (suggesting mid-sentence)
			if (preg_match('/\p{Ll}\s+may\b/ui', $string)) {
				return true;
			}
		}
		
		return false;
	}

	/**
	 * Find words that need case correction in a string.
	 *
	 * @param string $string The string to check.
	 * @return array Array of incorrect => correct case pairs.
	 */
	private function find_case_corrections( $string ) {
		$corrections = array();
		
		// Use a better word extraction approach that handles special characters properly
		preg_match_all('/\b[a-zA-Z0-9\'_-]+\b/', $string, $matches);
		$words = $matches[0];
		
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
		
		// Find all code sections (backticked content)
		$code_sections = array();
		preg_match_all('/`([^`]+)`/', $string, $code_matches);
		if (!empty($code_matches[1])) {
			$code_sections = $code_matches[1];
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
		
		// Extract all hook names, code blocks, and patterns to preserve
		$protected_patterns = array(
			// Hook patterns
			'/[`\'"]([a-zA-Z0-9_]+)_[\w_]+[\'"]/', // General hook pattern
			'/[`\'"]automator_[\w_]+[\'"]/', // Automator hook references
			'/apply_filters\s*\(\s*[\'"]([a-zA-Z0-9_]+)_[\w_]+[\'"]/',
			'/do_action\s*\(\s*[\'"]([a-zA-Z0-9_]+)_[\w_]+[\'"]/',
			'/has_filter\s*\(\s*[\'"]([a-zA-Z0-9_]+)_[\w_]+[\'"]/',
			'/add_filter\s*\(\s*[\'"]([a-zA-Z0-9_]+)_[\w_]+[\'"]/',
			'/add_action\s*\(\s*[\'"]([a-zA-Z0-9_]+)_[\w_]+[\'"]/',
			'/remove_filter\s*\(\s*[\'"]([a-zA-Z0-9_]+)_[\w_]+[\'"]/',
			'/remove_action\s*\(\s*[\'"]([a-zA-Z0-9_]+)_[\w_]+[\'"]/',
			// Code patterns
			'/`([^`]+)`/', // Backticked code
			// File extensions
			'/\.[a-zA-Z0-9]+\b/i', // Any file extension like .php, .json, etc.
			// HTML tags
			'/<\/?[a-zA-Z][^>]*>/' // HTML tags
		);
		
		// Extract all protected terms
		$protected_terms = array();
		foreach ($protected_patterns as $pattern) {
			if (preg_match_all($pattern, $string, $matches)) {
				foreach ($matches[0] as $i => $match) {
					// Get the actual captured term if available
					$term = isset($matches[1][$i]) ? $matches[1][$i] : $match;
					// Strip quotes, brackets and backticks
					$term = trim($term, '`\'"<>');
					$protected_terms[] = $term;
					
					// For hook names, protect each part
					if (strpos($term, '_') !== false) {
						$parts = explode('_', $term);
						$protected_terms = array_merge($protected_terms, $parts);
					}
				}
			}
		}
		
		// Explicitly protect the content of backticks
		foreach ($code_sections as $code) {
			$protected_terms[] = $code;
			// Also add individual words from code
			$code_words = preg_split('/\s+/', $code);
			$protected_terms = array_merge($protected_terms, $code_words);
		}
		
		// Find all file extensions
		$file_extensions = array();
		if (preg_match_all('/\.\w+\b/i', $string, $matches)) {
			foreach ($matches[0] as $ext) {
				$file_extensions[] = strtolower($ext);
				// Also add the extension without the dot
				$protected_terms[] = substr($ext, 1);
			}
		}
		
		// Add all WordPress hook prefixes to protected terms
		$hook_prefixes = array('wp', 'woocommerce', 'learndash', 'automator', 'uncanny');
		$protected_terms = array_merge($protected_terms, $hook_prefixes);
		
		// Clean and normalize protected terms
		$protected_terms = array_unique(array_map('trim', $protected_terms));
		
		// Process each word individually
		foreach ($words as $word) {
			$word_lower = strtolower($word);
			
			// Skip any word that's part of a protected term
			foreach ($protected_terms as $protected) {
				if (strcasecmp($word, $protected) === 0 || strcasecmp($protected, $word) === 0) {
					continue 2; // Skip to next word
				}
				
				// Also check if the word could be part of a protected term
				$protected_lower = strtolower($protected);
				if (strpos($protected_lower, $word_lower) === 0 || strpos($protected_lower, "_$word_lower") !== false) {
					continue 2; // Skip to next word
				}
			}
			
			// Skip words in code sections
			foreach ($code_sections as $code) {
				if (stripos($code, $word) !== false) {
					continue 2; // Skip to next word
				}
			}
			
			// Technical patterns that should maintain specific case
			
			// Special handling for version numbers (v1.0, etc.)
			if (preg_match('/^v\d+(\.\d+)*$/i', $word)) {
				if ($word !== strtolower($word)) {
					$corrections[$word] = strtolower($word);
				}
				continue;
			}
			
			// Special handling for multiplier notations (2x, 3x, 10x, etc.)
			if (preg_match('/^\d+x$/i', $word)) {
				if ($word !== strtolower($word)) {
					$corrections[$word] = strtolower($word);
				}
				continue;
			}
			
			// Special handling for IDs and similar patterns
			if (preg_match('/^(id|ids|url|urls)$/i', $word)) {
				// Check against our reserved words to find correct case
				foreach ($this->core_reserved as $reserved) {
					if (strcasecmp($word, $reserved) === 0) {
						if ($word !== $reserved) {
							$corrections[$word] = $reserved;
						}
						continue 2;
					}
				}
				continue;
			}
			
			// Special handling for 'may' vs 'May'
			if (strcasecmp($word, 'may') === 0) {
				if ($this->is_modal_may($string, $word)) {
					// In modal verb context, 'may' should remain lowercase
					if ($word !== 'may') {
						$corrections[$word] = 'may';
					}
					continue;
				}
			}
			
			// Check against core reserved words
			foreach ($this->core_reserved as $reserved) {
				// Skip 'May' check if we're in a modal verb context
				if ($reserved === 'May' && strcasecmp($word, 'may') === 0 && $this->is_modal_may($string, $word)) {
					continue;
				}
				
				// Skip case correction if the word is part of a file extension
				$word_as_extension = '.' . strtolower($word);
				if (in_array($word_as_extension, $file_extensions, true)) {
					continue;
				}
				
				// Skip 'X' check if it's part of a multiplier notation
				if ($reserved === 'X' && preg_match('/^\d+x$/i', $word)) {
					continue;
				}
				
				// Skip 'ID' check if it's part of another word
				if ($reserved === 'ID') {
					// ONLY match standalone 'id', never within another word like 'invalid'
					if (!preg_match('/^id$/i', $word)) {
						continue;
					}
				}
				
				// Check if word matches a reserved word (regardless of case)
				if (strcasecmp($word, $reserved) === 0) {
					// Word is a match but with wrong case
					if ($word !== $reserved) {
						$corrections[$word] = $reserved;
					}
					continue 2; // Continue to the next word
				}
			}
			
			// Check project reserved words
			foreach ($this->project_reserved as $reserved) {
				// Skip case correction if the word is part of a file extension
				$word_as_extension = '.' . strtolower($word);
				if (in_array($word_as_extension, $file_extensions, true)) {
					continue;
				}
				
				if (strcasecmp($word, $reserved) === 0 && $word !== $reserved) {
					$corrections[$word] = $reserved;
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