<?php

namespace Uncanny_Automator\Sniffs\Commenting;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

class FunctionDocBlockSniff implements Sniff {
	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return array
	 */
	public function register() {
		return array(T_FUNCTION);
	}

	/**
	 * Processes this test, when one of its tokens is encountered.
	 *
	 * @param File $phpcsFile The file being scanned.
	 * @param int  $stackPtr  The position of the current token in the stack.
	 *
	 * @return void
	 */
	public function process(File $phpcsFile, $stackPtr) {
		$tokens = $phpcsFile->getTokens();

		// Skip if not a valid function
		if (!isset($tokens[$stackPtr]['scope_opener'])) {
			return;
		}

		// Get function's scope
		$function = $tokens[$stackPtr];
		
		// Get the previous non-whitespace token
		$commentEnd = $phpcsFile->findPrevious(T_WHITESPACE, ($stackPtr - 1), null, true);
		
		// No comment found
		if ($commentEnd === false || $tokens[$commentEnd]['code'] !== T_DOC_COMMENT_CLOSE_TAG) {
			$phpcsFile->addError(
				'Missing function doc comment',
				$stackPtr,
				'Missing'
			);
			return;
		}

		// Get the start of the doc comment
		$commentStart = $tokens[$commentEnd]['comment_opener'];

		// Check for @param tags
		$params = $phpcsFile->getMethodParameters($stackPtr);
		$commentParams = array();
		
		for ($i = $commentStart; $i <= $commentEnd; $i++) {
			if ($tokens[$i]['code'] === T_DOC_COMMENT_TAG && $tokens[$i]['content'] === '@param') {
				$commentParams[] = $i;
			}
		}

		if (count($params) > 0 && empty($commentParams)) {
			$phpcsFile->addError(
				'Missing @param tag in function doc comment',
				$stackPtr,
				'MissingParamTag'
			);
		}

		// Check for @return tag
		$hasReturn = false;
		for ($i = $commentStart; $i <= $commentEnd; $i++) {
			if ($tokens[$i]['code'] === T_DOC_COMMENT_TAG && $tokens[$i]['content'] === '@return') {
				$hasReturn = true;
				break;
			}
		}

		if (!$hasReturn) {
			$phpcsFile->addError(
				'Missing @return tag in function doc comment',
				$stackPtr,
				'MissingReturn'
			);
		}
	}
} 