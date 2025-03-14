<?php

namespace Uncanny_Automator\Sniffs\Strings;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

/**
 * Validates string quoting style.
 *
 * @package Uncanny_Automator
 * @subpackage Sniffs\Strings
 */
class StringQuotingSniff implements Sniff {

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

		// Check for escaped single quotes in single-quoted strings
		if ("'" === $token['content'][0] && false !== strpos($token['content'], "\\'")) {
			$fix = $phpcs_file->addFixableError(
				'Use double quotes for strings containing single quotes instead of escaping them',
				$stack_ptr,
				'EscapedQuotes'
			);

			if (true === $fix) {
				// Convert to double-quoted string
				$string = trim($token['content'], "'");
				$string = str_replace("\\'", "'", $string);
				$new_content = '"' . $string . '"';
				$phpcs_file->fixer->replaceToken($stack_ptr, $new_content);
			}
		}
	}
} 