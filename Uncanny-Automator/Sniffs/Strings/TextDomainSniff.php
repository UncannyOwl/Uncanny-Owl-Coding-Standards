<?php

namespace Uncanny_Automator\Sniffs\Strings;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

/**
 * Validates text domains in translation functions.
 *
 * @package Uncanny_Automator
 * @subpackage Sniffs\Strings
 */
class TextDomainSniff implements Sniff {

	/**
	 * Core text domains that are always valid.
	 *
	 * @var array
	 */
	private $core_textdomains = array(
		'uncanny-automator',
		'uncanny-automator-pro',
		'uncanny-automator-elite'
	);

	/**
	 * Additional text domains loaded from configuration.
	 *
	 * @var array
	 */
	private $additional_textdomains = array();

	/**
	 * Text domain patterns (regex) for dynamic validation.
	 *
	 * @var array
	 */
	private $textdomain_patterns = array();

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
	 * Initialize the sniff by loading configuration.
	 */
	public function __construct() {
		$this->load_configuration();
	}

	/**
	 * Load configuration from the PHPCS config file.
	 */
	private function load_configuration() {
		$config_paths = array(
			'.phpcs/phpcs-config.php',
			'phpcs-config.php',
		);

		foreach ( $config_paths as $path ) {
			if ( file_exists( $path ) ) {
				$config = include $path;
				if ( is_array( $config ) && isset( $config['text_domains'] ) ) {
					$domains = $config['text_domains'];

					// Load additional text domains
					if ( isset( $domains['additional'] ) && is_array( $domains['additional'] ) ) {
						$this->additional_textdomains = array_merge(
							$this->additional_textdomains,
							$domains['additional']
						);
					}

					// Load text domain patterns
					if ( isset( $domains['patterns'] ) && is_array( $domains['patterns'] ) ) {
						$this->textdomain_patterns = array_merge(
							$this->textdomain_patterns,
							$domains['patterns']
						);
					}
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
	 *
	 * @return void
	 */
	public function process( File $phpcs_file, $stack_ptr ) {
		$tokens = $phpcs_file->getTokens();

		// Check if this string is part of a translation function
		$prev_token = $phpcs_file->findPrevious( T_STRING, $stack_ptr - 2, $stack_ptr - 5 );
		if ( false === $prev_token ) {
			return;
		}

		$function_name = $tokens[ $prev_token ]['content'];
		if ( ! in_array( $function_name, $this->translation_functions, true ) ) {
			return;
		}

		// Check textdomain
		$parameters = $this->get_function_parameters( $phpcs_file, $prev_token );
		if ( ! empty( $parameters ) ) {
			$textdomain = end( $parameters );
			if ( empty( $textdomain ) || ! isset( $textdomain['raw'] ) ) {
				$phpcs_file->addError(
					'Missing textdomain',
					$stack_ptr,
					'MissingTextdomain'
				);
			} else {
				// Remove quotes and any whitespace
				$textdomain_value = trim( $textdomain['raw'], " \t\n\r\0\x0B\"'" );
				if ( ! $this->is_valid_textdomain( $textdomain_value ) ) {
					$phpcs_file->addError(
						sprintf(
							'Invalid textdomain "%s". Must be one of the allowed domains or match allowed patterns.',
							$textdomain_value
						),
						$stack_ptr,
						'InvalidTextdomain'
					);
				}
			}
		}
	}

	/**
	 * Check if a text domain is valid.
	 *
	 * @param string $textdomain The text domain to check.
	 * @return bool Whether the text domain is valid.
	 */
	private function is_valid_textdomain( $textdomain ) {
		// Check core text domains
		if ( in_array( $textdomain, $this->core_textdomains, true ) ) {
			return true;
		}

		// Check additional text domains
		if ( in_array( $textdomain, $this->additional_textdomains, true ) ) {
			return true;
		}

		// Check against patterns
		foreach ( $this->textdomain_patterns as $pattern ) {
			if ( preg_match( $pattern, $textdomain ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get function parameters.
	 *
	 * @param File $phpcs_file The PHP_CodeSniffer file.
	 * @param int  $stack_ptr  The position in the stack.
	 * @return array
	 */
	private function get_function_parameters( File $phpcs_file, $stack_ptr ) {
		$tokens = $phpcs_file->getTokens();

		$open_paren = $phpcs_file->findNext( T_OPEN_PARENTHESIS, $stack_ptr, null, false, null, true );
		if ( ! isset( $tokens[ $open_paren ]['parenthesis_closer'] ) ) {
			return array();
		}

		$close_paren = $tokens[ $open_paren ]['parenthesis_closer'];
		$parameters  = array();
		$current     = array(
			'start' => $open_paren + 1,
			'raw'   => '',
		);

		for ( $i = $open_paren + 1; $i <= $close_paren - 1; $i++ ) {
			$token = $tokens[ $i ];

			// Handle string tokens
			if ( T_CONSTANT_ENCAPSED_STRING === $token['code'] ) {
				$current['raw'] .= $token['content'];
				continue;
			}

			// Handle commas outside of strings
			if ( T_COMMA === $token['code'] ) {
				$parameters[] = $current;
				$current     = array(
					'start' => $i + 1,
					'raw'   => '',
				);
				continue;
			}

			// Add other tokens
			$current['raw'] .= $token['content'];
		}

		$parameters[] = $current;

		return $parameters;
	}
} 
