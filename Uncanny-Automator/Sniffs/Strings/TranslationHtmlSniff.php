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

		// Check if we're inside a translation function
		$is_translation = false;
		$function_ptr = $phpcs_file->findPrevious(T_STRING, ($stack_ptr - 1), null, false);
		if (false !== $function_ptr) {
			$function_name = $tokens[$function_ptr]['content'];
			if (in_array($function_name, $this->translation_functions, true)) {
				$is_translation = true;
			}
		}

		if ($is_translation) {
			$string_content = substr($token['content'], 1, -1); // Strip outer quotes

			// Check for any HTML-like content
			if (
				// HTML tags
				preg_match('/<[^>]*>/', $string_content) ||
				// Common HTML attributes
				strpos($string_content, 'href=') !== false ||
				strpos($string_content, 'src=') !== false ||
				strpos($string_content, 'target=') !== false ||
				strpos($string_content, 'class=') !== false ||
				strpos($string_content, 'id=') !== false ||
				// Common HTML entities
				strpos($string_content, '&nbsp;') !== false ||
				strpos($string_content, '&quot;') !== false ||
				strpos($string_content, '&lt;') !== false ||
				strpos($string_content, '&gt;') !== false ||
				strpos($string_content, '&amp;') !== false
			) {
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
} 