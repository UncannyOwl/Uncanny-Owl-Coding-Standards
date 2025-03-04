<?php

namespace PHP_CodeSniffer\Standards\Uncanny_Automator\Sniffs\PHP;

if ( class_exists( '\PHP_CodeSniffer\Standards\Uncanny_Automator\Sniffs\PHP\ForbiddenPHP8FeaturesSniff', false ) ) {
	return;
}

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

class ForbiddenPHP8FeaturesSniff implements Sniff {

	/**
	 * PHP 8.0+ features that are not allowed
	 *
	 * @var array
	 */
	protected $forbidden_features = array(
		'mixed',
		'never',
		'readonly',
		'enum',
		'match',
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
		$tokens  = $phpcs_file->getTokens();
		$content = strtolower( $tokens[ $stack_ptr ]['content'] );

		if ( in_array( $content, $this->forbidden_features, true ) ) {
			$error = sprintf(
				'PHP 8.0+ feature "%s" is not allowed as we need to maintain PHP 7.3 compatibility',
				$content
			);
			$phpcs_file->addError( $error, $stack_ptr, 'ForbiddenPHP8Feature' );
		}
	}
}
