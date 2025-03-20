<?php

namespace Uncanny_Automator\Sniffs\Strings;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

/**
 * Validates string quoting style within WordPress translation functions.
 *
 * @package Uncanny_Automator
 * @subpackage Sniffs\Strings
 */
class StringQuotingSniff implements Sniff {

	/**
	 * WordPress translation functions where we want to enforce quote style.
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
	 * Date/Time format characters that must maintain their case.
	 *
	 * @var array
	 */
	private $date_format_chars = array(
		// Day
		'd', 'D', 'j', 'l', 'N', 'S', 'w', 'z',
		// Week
		'W',
		// Month
		'F', 'm', 'M', 'n',
		// Year
		'L', 'o', 'Y', 'y',
		// Time
		'a', 'A', 'B', 'g', 'G', 'h', 'H', 'i', 'I', 's', 'u', 'v',
		// Timezone
		'e', 'I', 'O', 'P', 'p', 'T', 'Z',
		// Full Date/Time
		'c', 'r', 'U'
	);

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
		
		// Get file name/path and check if it's a Discord file - SKIP ALL DISCORD FILES
		$file_path = strtolower($phpcs_file->getFilename());
		if (strpos($file_path, 'discord') !== false || strpos($file_path, '/integrations/discord/') !== false) {
			return;
		}

		// Get the string's quote character
		$quote_char = $token['content'][0];
		
		// Only process single-quoted strings
		if ("'" !== $quote_char) {
			return;
		}

		// Skip if this is a namespace declaration or use statement
		$prev_non_whitespace = $phpcs_file->findPrevious(T_WHITESPACE, ($stack_ptr - 1), null, true);
		if (false !== $prev_non_whitespace) {
			$prev_token = $tokens[$prev_non_whitespace];
			if (in_array($prev_token['content'], array('namespace', 'use'), true)) {
				return;
			}
		}

		// Check if we're inside a translation function
		$is_translation = false;
		$function_ptr = $phpcs_file->findPrevious(T_STRING, ($stack_ptr - 1), null, false);
		if (false !== $function_ptr) {
			$function_name = $tokens[$function_ptr]['content'];
			if (in_array($function_name, $this->translation_functions, true)) {
				$is_translation = true;
			}
		}

		// Only fix strings within translation functions
		if ($is_translation) {
			$string_content = substr($token['content'], 1, -1); // Strip outer quotes
			
			// Skip if the string contains any double quotes or HTML-like content
			if (strpos($string_content, '"') !== false ||
				strpos($string_content, '<') !== false ||
				strpos($string_content, '>') !== false ||
				strpos($string_content, 'href') !== false ||
				strpos($string_content, 'src') !== false ||
				strpos($string_content, 'target') !== false ||
				strpos($string_content, '_blank') !== false ||
				strpos($string_content, 'http') !== false ||
				strpos($string_content, 'www.') !== false ||
				strpos($string_content, '.com') !== false ||
				strpos($string_content, '.org') !== false) {
				return;
			}
			
			// Don't convert strings with complex escaping patterns
			if (strpos($string_content, '\\\\') !== false || // Double backslash
				preg_match('/\\\\[^\'"]/', $string_content)) { // Other escaped characters besides quotes
				return;
			}
			
			// Don't convert strings with sprintf-style placeholders
			if (preg_match('/%\d+\$[sd]/', $string_content)) {
				return;
			}
			
			// Check if this looks like a date format string
			$date_format_pattern = implode('|', array_map('preg_quote', $this->date_format_chars));
			if (preg_match('/^[\\\\\'"\s]*([' . $date_format_pattern . ']\s*[^a-zA-Z0-9]*\s*)+[\\\\\'"\s]*$/', $string_content)) {
				return;
			}
			
			// Additional check: Only convert if there are actually escaped single quotes
			if (strpos($string_content, "\\'") === false) {
				return;
			}
			
			$fix = $phpcs_file->addFixableError(
				'Use double quotes for strings containing single quotes in translation functions instead of escaping them',
				$stack_ptr,
				'EscapedQuotes'
			);

			if (true === $fix) {
				// Convert to double-quoted string
				$string = trim($token['content'], "'");
				
				// First, check if we have any existing double quotes that need escaping
				$string = str_replace('"', '\\"', $string);
				
				// Handle variable interpolation characters ($ needs to be escaped in double quotes)
				$string = preg_replace('/(%)(\d+)(\$)([sd])/', '$1$2\\\\$3$4', $string);
				
				// Escape any standalone $ that might be interpreted as variable interpolation
				$string = preg_replace('/(?<!\\\)\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)/', '\\\\$$1', $string);
				
				// Now replace escaped single quotes with unescaped ones
				$string = str_replace("\\'", "'", $string);
				
				$new_content = '"' . $string . '"';
				$phpcs_file->fixer->replaceToken($stack_ptr, $new_content);
			}
		}
	}
} 