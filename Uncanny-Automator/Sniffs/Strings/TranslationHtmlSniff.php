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

		// First, determine if this string is part of a translation function
		$in_translation_func = false;
		$i = $stack_ptr;
		$function_ptr = false;
		
		// Look backwards until we find a function name or something that would break the chain
		while ($i > 0) {
			// Skip whitespace and commas
			if (in_array($tokens[$i]['code'], array(T_WHITESPACE, T_COMMA), true)) {
				$i--;
				continue;
			}
			
			// If we find a function name, check if it's a translation function
			if ($tokens[$i]['code'] === T_STRING) {
				if (in_array($tokens[$i]['content'], $this->translation_functions, true)) {
					$in_translation_func = true;
					$function_ptr = $i;
					break;
				} else {
					// Found some other function, we're not in a translation context
					return;
				}
			}
			
			// Any other token type means we're not directly in a translation function
			break;
		}
		
		// If we're not in a translation function, no need to check
		if (!$in_translation_func) {
			return;
		}

		// Now examine the string content for HTML
		$string_content = substr($token['content'], 1, -1); // Strip outer quotes
		
		// Check if this translation string has placeholders
		$has_placeholders = (bool) preg_match('/%(\d+\$)?[sdf]/', $string_content);
		
		// Now check if we're inside a formatting context (printf/sprintf)
		$in_formatting_context = $this->check_formatting_context($phpcs_file, $stack_ptr);
		
		// If the string has placeholders AND we're in a formatting context, this is a valid use
		if ($has_placeholders && $in_formatting_context) {
			return;
		}

		// Now check for HTML in the translation string
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

	/**
	 * Determine if a token is inside a formatting context (printf/sprintf)
	 *
	 * @param File $phpcs_file The PHP_CodeSniffer file.
	 * @param int  $stack_ptr  The position to check from.
	 *
	 * @return bool Whether the token is inside a formatting context
	 */
	private function check_formatting_context(File $phpcs_file, $stack_ptr) {
		$tokens = $phpcs_file->getTokens();
		$filename = basename($phpcs_file->getFilename());
		
		// Get the full content of the file
		$file_content = file_get_contents($phpcs_file->getFilename());
		$relevant_lines = explode("\n", $file_content);
		$token_line = $tokens[$stack_ptr]['line'];
		
		// Look back up to 5 lines to find 'echo sprintf' or similar patterns
		for ($i = $token_line - 1; $i > max(0, $token_line - 5); $i--) {
			if (isset($relevant_lines[$i - 1])) {
				$line = trim($relevant_lines[$i - 1]);
				if (strpos($line, 'echo sprintf') !== false || 
				    strpos($line, 'printf') !== false ||
				    strpos($line, 'sprintf') !== false) {
					return true;
				}
			}
		}
		
		// Start from the current token and look backwards
		$i = $stack_ptr;
		$open_parentheses = 0;
		$max_lookback = 100; // Avoid infinite loops
		$count = 0;
		
		while ($i > 0 && $count < $max_lookback) {
			$count++;
			
			// If we hit a semicolon or open tag, we've gone too far back
			if (in_array($tokens[$i]['code'], array(T_SEMICOLON, T_OPEN_TAG), true)) {
				break;
			}
			
			// Track parentheses depth
			if ($tokens[$i]['code'] === T_OPEN_PARENTHESIS) {
				$open_parentheses++;
				
				// If we're opening a new set of parentheses, check if it belongs to echo/printf/sprintf
				$prev = $phpcs_file->findPrevious(array(T_WHITESPACE), $i - 1, null, true);
				if (false !== $prev) {
					if ($tokens[$prev]['code'] === T_STRING) {
						$function_name = strtolower($tokens[$prev]['content']);
						if ($function_name === 'sprintf' || $function_name === 'printf') {
							return true;
						}
					} elseif ($tokens[$prev]['code'] === T_ECHO) {
						// This is an echo statement - need to check if there's a sprintf inside
						$next = $phpcs_file->findNext(array(T_WHITESPACE), $i + 1, null, true);
						if (false !== $next && 
							$tokens[$next]['code'] === T_STRING && 
							strtolower($tokens[$next]['content']) === 'sprintf') {
							return true;
						}
					}
				}
			} elseif ($tokens[$i]['code'] === T_CLOSE_PARENTHESIS) {
				$open_parentheses--;
			}
			
			// Special handling for different formatting patterns
			if ($tokens[$i]['code'] === T_ECHO) {
				// Look ahead for sprintf
				$next = $phpcs_file->findNext(array(T_WHITESPACE), $i + 1, null, true);
				if (false !== $next && 
					$tokens[$next]['code'] === T_STRING && 
					strtolower($tokens[$next]['content']) === 'sprintf') {
					return true;
				}
			}
			
			$i--;
		}
		
		return false;
	}
} 