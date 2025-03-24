<?php

namespace Uncanny_Automator\Sniffs\Strings;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

/**
 * Detects HTML in translation strings and requires using sprintf with %s.
 * This is an error-only sniff and cannot be auto-fixed.
 *
 * @package Uncanny_Automator
 * @subpackage Sniffs\Strings
 */
class TranslationHtmlSniff implements Sniff {

	/**
	 * WordPress translation functions to check.
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
		$token  = $tokens[$stack_ptr];
		$content = $token['content'];
		
		// Check if this string is the first parameter to a translation function
		$prev_non_whitespace = $phpcs_file->findPrevious(
			T_WHITESPACE,
			$stack_ptr - 1,
			null,
			true
		);
		
		// If previous token isn't an open parenthesis, this isn't the first parameter
		if (false === $prev_non_whitespace || $tokens[$prev_non_whitespace]['code'] !== T_OPEN_PARENTHESIS) {
			return;
		}
		
		// Get the function name
		$function_ptr = $phpcs_file->findPrevious(
			T_WHITESPACE,
			$prev_non_whitespace - 1,
			null,
			true
		);
		
		if (false === $function_ptr || $tokens[$function_ptr]['code'] !== T_STRING) {
			return;
		}
		
		$function_name = $tokens[$function_ptr]['content'];
		
		// If not a translation function, return early
		if (!in_array($function_name, $this->translation_functions, true)) {
			return;
		}
		
		// We've confirmed this is a translation string - now check for HTML
		$string_content = substr($content, 1, -1); // Remove quotes
		
		// Check if the string contains HTML. This regex will match any HTML tag like <tag>
		if (preg_match('/<[^>]*>/', $string_content)) {
			// Now check if we're in a formatting context with a placeholder
			$has_placeholder = (bool) preg_match('/%(\d+\$)?[sdf]/', $string_content);
			$in_formatting_context = $this->is_in_formatting_context($phpcs_file, $stack_ptr);
			
			// If we have HTML but either no placeholder or not in a formatting context, report error
			if (!$has_placeholder || !$in_formatting_context) {
				$error = 'HTML found in translation string. Use sprintf() with %%s placeholder instead. Example:' . PHP_EOL;
				$error .= 'sprintf(' . PHP_EOL;
				$error .= '    __(' . PHP_EOL;
				$error .= '        \'Text with %s link\',' . PHP_EOL;
				$error .= '        \'uncanny-automator\'' . PHP_EOL;
				$error .= '    ),' . PHP_EOL;
				$error .= '    \'<a href="url">link text</a>\'' . PHP_EOL;
				$error .= ');';
				
				$phpcs_file->addError(
					$error,
					$stack_ptr,
					'HTMLInTranslation'
				);
			}
		}
	}
	
	/**
	 * Check if we're in a formatting context (sprintf/printf)
	 *
	 * @param File $phpcs_file The PHP_CodeSniffer file
	 * @param int  $stack_ptr  The position to check from
	 * @return bool Whether we're in a formatting context
	 */
	private function is_in_formatting_context(File $phpcs_file, $stack_ptr) {
		$tokens = $phpcs_file->getTokens();
		
		// Find the statement this translation is a part of
		$statement_start = 0;
		for ($i = $stack_ptr; $i >= 0; $i--) {
			if (in_array($tokens[$i]['code'], array(T_SEMICOLON, T_OPEN_TAG), true)) {
				$statement_start = $i + 1;
				break;
			}
		}
		
		// Look for sprintf/printf at the beginning of the statement
		$first_token = $phpcs_file->findNext(T_WHITESPACE, $statement_start, null, true);
		if (false !== $first_token) {
			// Direct sprintf/printf call
			if ($tokens[$first_token]['code'] === T_STRING) {
				$function = strtolower($tokens[$first_token]['content']);
				if ($function === 'sprintf' || $function === 'printf') {
					return true;
				}
			}
			
			// Echo sprintf pattern
			if ($tokens[$first_token]['code'] === T_ECHO) {
				$next = $phpcs_file->findNext(T_WHITESPACE, $first_token + 1, null, true);
				if (false !== $next && 
					$tokens[$next]['code'] === T_STRING && 
					strtolower($tokens[$next]['content']) === 'sprintf') {
					return true;
				}
			}
		}
		
		return false;
	}
} 