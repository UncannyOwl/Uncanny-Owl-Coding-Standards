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
		// Preserve the original quote style
		$original_quotes = substr( $token['content'], 0, 1 );

		// Skip empty strings
		if ( empty( $string ) ) {
			return;
		}

		// Skip URLs, formats, and code-like strings
		if ( $this->should_skip_string( $string ) ) {
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
					sprintf( '%s%s%s', $original_quotes, $fixed_string, $original_quotes )
				);
			}
		}
	}

	/**
	 * Determine if a string should be skipped for case checking.
	 *
	 * @param string $string The string to check.
	 * @return bool True if the string should be skipped.
	 */
	private function should_skip_string( $string ) {
		// Skip URLs
		if ( preg_match( '~^https?://~i', $string ) ||
			strpos( $string, '.com' ) !== false ||
			strpos( $string, '.org' ) !== false ||
			strpos( $string, '.net' ) !== false ||
			strpos( $string, '.edu' ) !== false ) {
			return true;
		}

		// Skip date and time format strings (common formats)
		if ( preg_match( '/^[YyFmMdjlDwWNztsLco\s,@\-:.\/]+$/i', $string ) ) {
			return true;
		}

		// Skip strings that appear to be time format-related
		if ( preg_match( '/[HhGg]:[im]/i', $string ) ) {
			return true;
		}

		// Skip strings that appear to be code or constants
		if ( preg_match( '/^[A-Z_][A-Z0-9_]+$/i', $string ) ||
			preg_match( '/\w+_\w+/', $string ) ) {
			return true;
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

		// Skip HTML content entirely
		if ( preg_match( '/<\/?[a-z][^>]*>/i', $string ) ) {
			return array();
		}

		// Skip URLs
		if ( preg_match( '/https?:\/\//i', $string ) ) {
			return array();
		}

		// Extract full words with word boundaries
		preg_match_all( '/\b([a-zA-Z0-9]+)\b/', $string, $matches );
		$words = $matches[1];

		foreach ( $words as $word ) {
			// Skip non-alphabetic words or single characters (except 'I')
			if ( ! ctype_alpha( $word ) || ( strlen( $word ) === 1 && $word !== 'i' && $word !== 'I' ) ) {
				continue;
			}

			// Check against core reserved words - exact matches only
			foreach ( $this->core_reserved as $reserved ) {
				// Only check if it's an exact case-insensitive match for the whole word
				if ( strcasecmp( $word, $reserved ) === 0 && $word !== $reserved ) {
					// Skip protocol parts of URLs
					if ( in_array( $reserved, array( 'HTTP', 'HTTPS' ), true ) && 
						 preg_match( '/\b' . preg_quote( $word, '/' ) . ':\/\//i', $string ) ) {
						continue;
					}

					// Skip HTML tags
					if ( preg_match( '/<' . preg_quote( $word, '/' ) . '>/i', $string ) ||
						 preg_match( '/<\/' . preg_quote( $word, '/' ) . '>/i', $string ) ) {
						continue;
					}

					// Avoid changing 'id' within other words
					if ( $reserved === 'ID' && !preg_match( '/\bid\b/i', $string ) ) {
						continue;
					}
					
					// Only make the correction if it's a standalone whole word
					$corrections[ $word ] = $reserved;
					break;
				}
			}

			// Check project reserved words - exact matches only
			if ( ! isset( $corrections[ $word ] ) ) {
				foreach ( $this->project_reserved as $reserved ) {
					if ( strcasecmp( $word, $reserved ) === 0 && $word !== $reserved ) {
						$corrections[ $word ] = $reserved;
						break;
					}
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
			// Use word boundary in replacement to avoid changing parts of words
			$result = preg_replace( '/\b' . preg_quote( $incorrect, '/' ) . '\b/i', $correct, $result );
		}
		return $result;
	}
} 