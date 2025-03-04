<?php

namespace PHP_CodeSniffer\Standards\Uncanny_Automator\Sniffs\PHP;

if ( class_exists( '\PHP_CodeSniffer\Standards\Uncanny_Automator\Sniffs\PHP\ForbiddenFunctionsSniff', false ) ) {
	return;
}

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

class ForbiddenFunctionsSniff implements Sniff {
	/**
	 * Functions that are not allowed
	 *
	 * @var array
	 */
	protected $forbidden_functions = array(
		'elog' => 'Please use proper logging methods instead of elog()',
	);

	/**
	 * Returns the token types that this sniff is interested in.
	 *
	 * @return array
	 */
	public function register() {
		return array( T_STRING );
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
		$tokens        = $phpcs_file->getTokens();
		$function_name = strtolower( $tokens[ $stack_ptr ]['content'] );

		if ( isset( $this->forbidden_functions[ $function_name ] ) ) {
			$error = sprintf(
				'Function %s() is not allowed. %s',
				$function_name,
				$this->forbidden_functions[ $function_name ]
			);
			$phpcs_file->addError( $error, $stack_ptr, 'ForbiddenFunction' );
		}
	}
}
