<?php

namespace Uncanny_Automator\Sniffs\Strings;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

/**
 * Comprehensive string validation sniff for Uncanny Automator.
 * Enforces:
 * - Sentence case everywhere
 * - Proper escaping functions
 * - Context functions in integrations
 * - Translator comments for dynamic strings
 * - Valid textdomains
 *
 * @package Uncanny_Automator
 * @subpackage Sniffs\Strings
 */
class IntegrationStringSentenceCaseSniff implements Sniff {

	/**
	 * Valid text domains for the plugin.
	 *
	 * @var array
	 */
	private $valid_textdomains = array(
		'uncanny-automator',
		'uncanny-automator-pro',
	);

	/**
	 * Non-context translation functions that should trigger a warning in integrations.
	 *
	 * @var array
	 */
	private $non_context_functions = array(
		'__',
		'_e',
		'esc_html__',
		'esc_html_e',
		'esc_attr__',
		'esc_attr_e',
	);

	/**
	 * Translation functions that require escaping.
	 *
	 * @var array
	 */
	private $unescaped_functions = array(
		'__',
		'_e',
		'_x',
		'_ex',
	);

	/**
	 * Recommended context translation functions to use instead.
	 *
	 * @var array
	 */
	private $context_functions = array(
		'_x',
		'_ex',
		'esc_html_x',
		'esc_attr_x',
	);

	/**
	 * Placeholder patterns that require translator comments.
	 *
	 * @var array
	 */
	private $placeholder_patterns = array(
		'%s',  // String
		'%d',  // Integer
		'%f',  // Float
		'%u',  // Unsigned integer
		'%%',  // Literal percent
		'%1$s', // Positional string
		'%2$s',
		'%3$s',
		'%4$s',
		'%5$s',
		'%1$d', // Positional integer
		'%2$d',
		'%3$d',
		'%4$d',
		'%5$d',
	);

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
		$token  = $tokens[ $stack_ptr ];

		// Check for escaped single quotes in single-quoted strings
		if ( $token['content'][0] === "'" && strpos( $token['content'], "\\'" ) !== false ) {
			$fix = $phpcs_file->addFixableError(
				'Use double quotes for strings containing single quotes instead of escaping them',
				$stack_ptr,
				'EscapedQuotes'
			);

			if ( true === $fix ) {
				// Convert to double-quoted string
				$string = trim( $token['content'], "'" );
				$string = str_replace( "\\'", "'", $string );
				$new_content = '"' . $string . '"';
				$phpcs_file->fixer->replaceToken( $stack_ptr, $new_content );
				return;
			}
		}

		// Get the string content without quotes
		$string = trim( $token['content'], "\"'" );

		// Skip empty strings
		if ( empty( $string ) || strlen( $string ) < 2 ) {
			return;
		}

		// Check if this string is part of a translation function
		$prev_token = $phpcs_file->findPrevious( T_STRING, $stack_ptr - 2, $stack_ptr - 5 );
		if ( false === $prev_token ) {
			return;
		}

		$function_name = $tokens[ $prev_token ]['content'];

		// Check for unescaped translation functions
		if ( in_array( $function_name, $this->unescaped_functions, true ) ) {
			$phpcs_file->addError(
				'Translation function should use esc_html__ or esc_attr__ for proper escaping',
				$stack_ptr,
				'UnescapedTranslation'
			);
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
				if ( ! in_array( $textdomain_value, $this->valid_textdomains, true ) ) {
					$phpcs_file->addError(
						sprintf(
							'Invalid textdomain. Must be one of: %s',
							implode( ', ', $this->valid_textdomains )
						),
						$stack_ptr,
						'InvalidTextdomain'
					);
				}
			}
		}

		// Check for context in integration files
		$filename = $phpcs_file->getFilename();
		if ( false !== strpos( $filename, '/src/integrations/' ) ) {
			if ( in_array( $function_name, $this->non_context_functions, true ) ) {
				$context_alternative = $this->get_context_alternative( $function_name );
				$phpcs_file->addWarning(
					'Use %s() with context instead of %s() in integration strings. This helps translators better understand the context of the string.',
					$stack_ptr,
					'NoContext',
					array( $context_alternative, $function_name )
				);
			}
		}

		// Check for translator comments on strings with placeholders
		if ( $this->has_placeholders( $string ) ) {
			$comment_ptr = $phpcs_file->findPrevious( T_COMMENT, $stack_ptr - 1, $stack_ptr - 3 );
			if ( false === $comment_ptr || false === strpos( $tokens[ $comment_ptr ]['content'], 'translators:' ) ) {
				$phpcs_file->addError(
					'String with placeholders must have a translator comment. Add a "// translators:" comment.',
					$stack_ptr,
					'MissingTranslatorComment'
				);
			}
		}

		// Skip sentence case check for URLs, file paths, or special characters
		if ( $this->should_skip_string( $string ) ) {
			return;
		}

		// Check sentence case
		$words = explode( ' ', $string );
		$word_count = count( $words );
		$capitalized_words = array();

		for ( $i = 1; $i < $word_count; $i++ ) {
			$word = $words[ $i ];
			if ( ! $this->is_exception( $word ) && '' !== $word && ctype_upper( $word[0] ) ) {
				$capitalized_words[] = $word;
			}
		}

		if ( ! empty( $capitalized_words ) ) {
			$fix = $phpcs_file->addFixableError(
				sprintf(
					'String "%s" contains incorrectly capitalized words: "%s". Use sentence case instead: "%s"',
					$string,
					implode( '", "', $capitalized_words ),
					$this->convert_to_sentence_case( $string )
				),
				$stack_ptr,
				'CapitalizedWords'
			);

			if ( true === $fix ) {
				$quote = $token['content'][0];
				if ( $quote === "'" && strpos( $string, "'" ) !== false ) {
					$quote = '"';
				}
				$new_content = $quote . $this->convert_to_sentence_case( $string ) . $quote;
				$phpcs_file->fixer->replaceToken( $stack_ptr, $new_content );
			}
		}
	}

	/**
	 * Check if a string contains placeholders that require translator comments.
	 *
	 * @param string $string The string to check.
	 * @return bool
	 */
	private function has_placeholders( $string ) {
		foreach ( $this->placeholder_patterns as $pattern ) {
			if ( false !== strpos( $string, $pattern ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get the recommended context function alternative.
	 *
	 * @param string $function The current function name.
	 * @return string The recommended context function.
	 */
	private function get_context_alternative( $function ) {
		switch ( $function ) {
			case '__':
				return '_x';
			case '_e':
				return '_ex';
			case 'esc_html__':
				return 'esc_html_x';
			case 'esc_html_e':
				return 'esc_html_x';
			case 'esc_attr__':
				return 'esc_attr_x';
			case 'esc_attr_e':
				return 'esc_attr_x';
			default:
				return '_x';
		}
	}

	/**
	 * Check if the string should be skipped for sentence case validation.
	 *
	 * @param string $string The string to check.
	 * @return bool
	 */
	private function should_skip_string( $string ) {
		// Skip URLs
		if ( filter_var( $string, FILTER_VALIDATE_URL ) ) {
			return true;
		}

		// Skip file paths
		if ( false !== strpos( $string, '/' ) || false !== strpos( $string, '\\' ) ) {
			return true;
		}

		// Skip strings with special characters, but allow apostrophes
		if ( preg_match( '/[^a-zA-Z0-9\s\']/', $string ) ) {
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
	private function is_exception( $word ) {
		// Remove any apostrophes for checking exceptions
		$word = str_replace( "'", '', $word );
		
		$exceptions = array(
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
			'Monday',
			'Tuesday',
			'Wednesday',
			'Thursday',
			'Friday',
			'Saturday',
			'Sunday',
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
		);

		return in_array( $word, $exceptions, true );
	}

	/**
	 * Convert a string to sentence case.
	 *
	 * @param string $string The string to convert.
	 * @return string
	 */
	private function convert_to_sentence_case( $string ) {
		$words = explode( ' ', $string );
		
		// Capitalize first word
		$words[0] = ucfirst( $words[0] );
		
		$word_count = count( $words );
		
		// Convert rest to lowercase unless they're exceptions
		for ( $i = 1; $i < $word_count; $i++ ) {
			if ( ! $this->is_exception( $words[ $i ] ) ) {
				$words[ $i ] = strtolower( $words[ $i ] );
			}
		}
		
		return implode( ' ', $words );
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

		$in_string = false;
		$string_char = '';

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