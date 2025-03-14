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

		// Check if this string is part of a translation function
		$prev_token = $phpcs_file->findPrevious( T_STRING, $stack_ptr - 2, $stack_ptr - 5 );
		if ( false === $prev_token ) {
			return;
		}

		$function_name = $tokens[ $prev_token ]['content'];
		if ( ! in_array( $function_name, $this->translation_functions, true ) ) {
			return;
		}

		$string = trim( $tokens[ $stack_ptr ]['content'], "\"'" );

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
