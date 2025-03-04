<?php

namespace PHP_CodeSniffer\Standards\Uncanny_Automator\Sniffs\PHP;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

class DisallowUnionTypesSniff implements Sniff {
	/**
	 * Returns the token types that this sniff is interested in.
	 *
	 * @return array
	 */
	public function register() {
		return array( T_TYPE_UNION );
	}

	/**
	 * Processes this sniff when one of its tokens is encountered.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token in the stack.
	 *
	 * @return void
	 */
	public function process( File $phpcs_file, $stack_ptr ) {
		$error = 'Union types are not allowed as they require PHP 8.0+';
		$phpcs_file->addError( $error, $stack_ptr, 'Found' );
	}
}
