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

		// Get function name
		$functionName = $phpcsFile->getDeclarationName($stackPtr);
		if ($functionName === null) {
			return;
		}

		// Find the closest comment before the function
		$commentEnd = $phpcsFile->findPrevious(
			array(T_DOC_COMMENT_CLOSE_TAG, T_COMMENT),
			($stackPtr - 1),
			null,
			false,
			null,
			true
		);

		// No comment found at all
		if ($commentEnd === false) {
			$phpcsFile->addError(
				'Missing function doc comment for function %s',
				$stackPtr,
				'Missing',
				array($functionName)
			);
			return;
		}

		// If it's not a doc comment or not immediately before the function, report error
		if ($tokens[$commentEnd]['code'] !== T_DOC_COMMENT_CLOSE_TAG) {
			$phpcsFile->addError(
				'Missing function doc comment for function %s',
				$stackPtr,
				'Missing',
				array($functionName)
			);
			return;
		}

		// Get the start of the doc comment
		$commentStart = $tokens[$commentEnd]['comment_opener'];

		// Get the comment's content
		$comment = '';
		for ($i = $commentStart; $i <= $commentEnd; $i++) {
			$comment .= $tokens[$i]['content'];
		}

		// If it's not a proper doc block (doesn't start with /**), report error
		if (strpos(trim($comment), '/**') !== 0) {
			$phpcsFile->addError(
				'Doc comment must start with /** for function %s',
				$stackPtr,
				'InvalidFormat',
				array($functionName)
			);
			return;
		}

		// At this point, we have a valid doc block, so accept it
		return;
	}
} 