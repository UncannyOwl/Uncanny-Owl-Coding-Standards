<?php

namespace Uncanny_Automator\Sniffs\PHP;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

class PHP74FeaturesSniff implements Sniff {
	/**
	 * Returns the token types that this sniff is interested in.
	 *
	 * @return array
	 */
	public function register(): array {
		return array(
			T_VARIABLE,      // For typed properties
			T_COALESCE_EQUAL, // For ??=
			T_ELLIPSIS,     // For array spread
		);
	}

	/**
	 * Processes this sniff when one of its tokens is encountered.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token in the stack.
	 *
	 * @return void
	 */
	public function process( File $phpcs_file, $stack_ptr ): void {
		$tokens = $phpcs_file->getTokens();
		$token  = $tokens[ $stack_ptr ];

		switch ( $token['code'] ) {
			case T_COALESCE_EQUAL:
				$error = 'The null coalescing assignment operator (??=) requires PHP 7.4+';
				$phpcs_file->addError( $error, $stack_ptr, 'NullCoalescingAssignment' );
				break;

			case T_ELLIPSIS:
				// Check if it's used in array context
				if ( $this->is_array_spread( $phpcs_file, $stack_ptr ) ) {
					$error = 'Array spread operator in array expressions requires PHP 7.4+';
					$phpcs_file->addError( $error, $stack_ptr, 'ArraySpread' );
				}
				break;

			case T_VARIABLE:
				// Check for typed properties
				if ( $this->has_type_declaration( $phpcs_file, $stack_ptr ) ) {
					$error = 'Typed properties require PHP 7.4+';
					$phpcs_file->addError( $error, $stack_ptr, 'TypedProperty' );
				}
				break;
		}
	}

	/**
	 * Check if the spread operator is used in array context.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token in the stack.
	 *
	 * @return bool
	 */
	private function is_array_spread( File $phpcs_file, $stack_ptr ): bool {
		$tokens = $phpcs_file->getTokens();
		$prev   = $phpcs_file->findPrevious( T_WHITESPACE, ( $stack_ptr - 1 ), null, true );

		return isset( $tokens[ $prev ] ) && $tokens[ $prev ]['code'] === T_ARRAY;
	}

	/**
	 * Check if a property has a type declaration.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token in the stack.
	 *
	 * @return bool
	 */
	private function has_type_declaration( File $phpcs_file, $stack_ptr ): bool {
		$tokens = $phpcs_file->getTokens();

		// Look for class context
		if ( empty( $tokens[ $stack_ptr ]['conditions'] ) ) {
			return false;
		}

		// Check if we're inside a function
		$function_ptr = $phpcs_file->findPrevious( T_FUNCTION, $stack_ptr );
		if ( $function_ptr !== false && isset($tokens[$function_ptr])) {
			// Check if the variable is within the function's parameters
			if ( isset( $tokens[ $function_ptr ]['parenthesis_opener'] ) && 
				isset( $tokens[ $function_ptr ]['parenthesis_closer'] ) ) {
				if ( $stack_ptr > $tokens[ $function_ptr ]['parenthesis_opener'] && 
					$stack_ptr < $tokens[ $function_ptr ]['parenthesis_closer'] ) {
					// This is a function parameter, not a property
					return false;
				}
			}
		}

		// Check if it's a property declaration
		$prev = $phpcs_file->findPrevious(
			[ T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ],
			( $stack_ptr - 1 ),
			null,
			true
		);

		if ( $prev === false || !isset($tokens[$prev])) {
			return false;
		}

		// If there's a type before the property
		if ( in_array( $tokens[ $prev ]['code'], [ T_STRING, T_ARRAY, T_CALLABLE ], true ) ) {
			// Make sure this is actually a property and not a method parameter
			return !$this->is_method_parameter( $phpcs_file, $stack_ptr );
		}

		return false;
	}

	/**
	 * Check if a variable token is a method parameter.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token in the stack.
	 *
	 * @return bool
	 */
	private function is_method_parameter( File $phpcs_file, $stack_ptr ): bool {
		$tokens = $phpcs_file->getTokens();

		// Find the previous non-whitespace token
		$prev_token = $phpcs_file->findPrevious(
			T_WHITESPACE,
			( $stack_ptr - 1 ),
			null,
			true
		);

		// If we're inside parentheses and after a function declaration, this is a parameter
		if ( isset( $tokens[ $stack_ptr ]['nested_parenthesis'] ) ) {
			foreach ( $tokens[ $stack_ptr ]['nested_parenthesis'] as $open => $close ) {
				$function = $phpcs_file->findPrevious( T_FUNCTION, ( $open - 1 ) );
				if ( $function !== false ) {
					return true;
				}
			}
		}

		return false;
	}
}
