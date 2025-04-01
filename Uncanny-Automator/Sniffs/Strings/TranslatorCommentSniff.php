<?php

namespace Uncanny_Automator\Sniffs\Strings;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

/**
 * Validates translator comments for strings with placeholders.
 *
 * @package Uncanny_Automator
 * @subpackage Sniffs\Strings
 */
class TranslatorCommentSniff implements Sniff {

	/**
	 * Priority value for this sniff.
	 * Higher numbers are processed first.
	 *
	 * @var int
	 */
	public $priority = 100;

	/**
	 * Minimum comment length.
	 *
	 * @var int
	 */
	const MIN_COMMENT_LENGTH = 10;

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
	 * Whether we're inside a sprintf call.
	 *
	 * @var bool
	 */
	private $found_sprintf = false;

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
		return array( T_STRING );
	}

	/**
	 * Processes this test when one of its tokens is encountered.
	 *
	 * @param File $phpcs_file The PHP_CodeSniffer file where the token was found.
	 * @param int  $stack_ptr  The position in the PHP_CodeSniffer file's token stack where the token was found.
	 */
	public function process( File $phpcs_file, $stack_ptr ) {
		$tokens = $phpcs_file->getTokens();

		// Only check translation functions
		$function_name = $tokens[ $stack_ptr ]['content'];
		if ( ! in_array( $function_name, $this->translation_functions, true ) ) {
			return;
		}

		// Make sure it's actually a function call
		$prev_token = $phpcs_file->findPrevious( T_WHITESPACE, $stack_ptr - 1, null, true );
		if ( false !== $prev_token ) {
			// Skip if it's a method call or part of another construct
			if ( in_array( $tokens[ $prev_token ]['code'], array( T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NS_SEPARATOR ), true ) ) {
				return;
			}
		}

		// Find opening parenthesis
		$open_paren = $phpcs_file->findNext( T_WHITESPACE, $stack_ptr + 1, null, true );
		if ( false === $open_paren || T_OPEN_PARENTHESIS !== $tokens[ $open_paren ]['code'] ) {
			return;
		}

		// Find the string argument
		$string_ptr = $phpcs_file->findNext( T_CONSTANT_ENCAPSED_STRING, $open_paren + 1 );
		if ( false === $string_ptr ) {
			return;
		}

		$string = $tokens[ $string_ptr ]['content'];

		// Check if this is inside a sprintf
		$parent_ptr = $stack_ptr;
		$this->found_sprintf = false;
		while ( $parent_ptr > 0 ) {
			$parent_ptr--;
			if ( ! isset( $tokens[ $parent_ptr ] ) ) {
				continue;
			}

			$token = $tokens[ $parent_ptr ];

			// Skip whitespace
			if ( $token['code'] === T_WHITESPACE ) {
				continue;
			}

			// If we hit a newline or semicolon, we've gone too far
			if ( in_array( $token['code'], array( T_SEMICOLON, T_CLOSE_CURLY_BRACKET ), true ) ) {
				break;
			}

			// Check for sprintf
			if ( $token['code'] === T_STRING && $token['content'] === 'sprintf' ) {
				$this->found_sprintf = true;
				break;
			}
		}

		// If no placeholders, we don't care about translator comments at all
		if ( ! $this->has_placeholders( $string ) ) {
			return;
		}

		// Look for a translator comment
		$found_translator_comment = false;
		$prev_ptr = $stack_ptr;
		
		while ( $prev_ptr > 0 ) {
			$prev_ptr--;
			if ( ! isset( $tokens[ $prev_ptr ] ) ) {
				continue;
			}

			$token = $tokens[ $prev_ptr ];

			// Skip whitespace
			if ( $token['code'] === T_WHITESPACE ) {
				continue;
			}

			// If we hit a newline or semicolon, we've gone too far
			if ( in_array( $token['code'], array( T_SEMICOLON, T_CLOSE_CURLY_BRACKET ), true ) ) {
				break;
			}

			// Check for translator comments
			if ( in_array( $token['code'], array( T_COMMENT, T_DOC_COMMENT_STRING, T_DOC_COMMENT_TAG ), true ) ) {
				$comment = trim( $token['content'], '/* ' );
				if ( $this->is_valid_translator_comment( $comment ) ) {
					$found_translator_comment = true;
					
					$comment_text = trim( substr( $comment, strpos( $comment, ':' ) + 1 ) );
					if ( strlen( $comment_text ) < self::MIN_COMMENT_LENGTH ) {
						$phpcs_file->addWarning(
							'Translator comment should be more descriptive. Example: "// translators: %1$s is the query string"',
							$prev_ptr,
							'InsufficientTranslatorComment'
						);
					}
					
					break;
				}
			}
		}

		if ( ! $found_translator_comment ) {
			$phpcs_file->addError(
				'String with placeholders must have a translator comment. Add a "// translators:" or "/* translators: */" comment.',
				$stack_ptr,
				'MissingTranslatorComment'
			);
		}
	}

	/**
	 * Check if a string contains placeholders.
	 *
	 * @param string $string The string to check.
	 * @return bool
	 */
	private function has_placeholders( $string ) {
		// Remove quotes from the string
		$string = trim( $string, "'\"" );
		
		// Check for any of our placeholder patterns
		foreach ( $this->placeholder_patterns as $pattern ) {
			if ( false !== strpos( $string, $pattern ) ) {
				// Make sure it's not part of a word (e.g. "%string%" shouldn't match)
				if ( preg_match( '/[a-zA-Z0-9]' . preg_quote( $pattern, '/' ) . '|' . preg_quote( $pattern, '/' ) . '[a-zA-Z0-9]/', $string ) ) {
					continue;
				}
				return true;
			}
		}
		
		// Only check for dynamic content if we're inside a sprintf
		if ( $this->found_sprintf ) {
			// Check for dynamic content patterns
			$dynamic_patterns = array(
				'{{.*?}}',  // Matches {{anything}}
				'%s',       // Generic string placeholder
				'%d',       // Generic number placeholder
				'%f',       // Generic float placeholder
			);
			
			foreach ( $dynamic_patterns as $pattern ) {
				if ( preg_match( '/' . $pattern . '/', $string ) ) {
					return true;
				}
			}
		}
		
		return false;
	}

	/**
	 * Check if a comment is a valid translator comment.
	 *
	 * @param string $comment The comment to check.
	 * @return bool
	 */
	private function is_valid_translator_comment( $comment ) {
		$comment = trim( $comment, '/* ' );
		if ( strpos( $comment, 'translators:' ) !== 0 && strpos( $comment, 'translator:' ) !== 0 ) {
			return false;
		}

		// Remove the requirement for "is" and adjust the length requirement if needed
		$comment_text = trim( substr( $comment, strpos( $comment, ':' ) + 1 ) );
		return strlen( $comment_text ) >= self::MIN_COMMENT_LENGTH;
	}

	/**
	 * Get all placeholders from a string.
	 *
	 * @param string $string The string to check.
	 * @return array Array of found placeholders.
	 */
	private function get_placeholders( $string ) {
		$found = array();
		foreach ( $this->placeholder_patterns as $pattern ) {
			if ( false !== strpos( $string, $pattern ) ) {
				$found[] = $pattern;
			}
		}
		return $found;
	}

	/**
	 * Generate a translator comment based on placeholders.
	 *
	 * @param array $placeholders Array of placeholders found in the string.
	 * @return string Generated translator comment.
	 */
	private function generate_translator_comment( $placeholders ) {
		$comment = '// translators: ';
		$descriptions = array();

		foreach ( $placeholders as $placeholder ) {
			switch ( $placeholder ) {
				case '%s':
					$descriptions[] = 'placeholder for a string';
					break;
				case '%d':
					$descriptions[] = 'placeholder for a number';
					break;
				case '%f':
					$descriptions[] = 'placeholder for a float';
					break;
				case '%u':
					$descriptions[] = 'placeholder for an unsigned number';
					break;
				case '%%':
					$descriptions[] = 'literal percent sign';
					break;
				default:
					// Handle positional placeholders
					if ( preg_match( '/^%(\d+)\$([sd])$/', $placeholder, $matches ) ) {
						$position = $matches[1];
						$type = 's' === $matches[2] ? 'string' : 'number';
						$descriptions[] = sprintf( '%s placeholder at position %d', $type, $position );
					}
					break;
			}
		}

		$comment .= implode( ', ', $descriptions );
		return $comment . "\n";
	}
}
