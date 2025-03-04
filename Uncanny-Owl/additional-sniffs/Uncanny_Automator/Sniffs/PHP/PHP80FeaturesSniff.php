<?php

namespace PHP_CodeSniffer\Standards\Uncanny_Automator\Sniffs\PHP;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

class PHP80FeaturesSniff implements Sniff {
	/**
	 * Returns the token types that this sniff is interested in.
	 *
	 * @return array
	 */
	public function register() {
		return array(
			T_PARAM_NAME,          // For named arguments
			T_PUBLIC,              // For constructor property promotion
			T_PROTECTED,
			T_PRIVATE,
			T_NULLSAFE_OBJECT_OPERATOR, // For nullsafe operator
			T_MATCH,              // For match expression
			T_ATTRIBUTE,          // For attributes
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
	public function process( File $phpcs_file, $stack_ptr ) {
		$tokens = $phpcs_file->getTokens();
		$token  = $tokens[ $stack_ptr ];

		switch ( $token['code'] ) {
			case T_PARAM_NAME:
				$error = 'Named arguments are not allowed as they require PHP 8.0+';
				$phpcs_file->addError( $error, $stack_ptr, 'NamedArgument' );
				break;

			case T_NULLSAFE_OBJECT_OPERATOR:
				$error = 'Nullsafe operator (?->) is not allowed as it requires PHP 8.0+';
				$phpcs_file->addError( $error, $stack_ptr, 'NullsafeOperator' );
				break;

			case T_MATCH:
				$error = 'Match expression is not allowed as it requires PHP 8.0+';
				$phpcs_file->addError( $error, $stack_ptr, 'MatchExpression' );
				break;

			case T_ATTRIBUTE:
				$error = 'Attributes are not allowed as they require PHP 8.0+';
				$phpcs_file->addError( $error, $stack_ptr, 'Attribute' );
				break;

			case T_PUBLIC:
			case T_PROTECTED:
			case T_PRIVATE:
				if ( $this->is_constructor_property_promotion( $phpcs_file, $stack_ptr ) ) {
					$error = 'Constructor property promotion is not allowed as it requires PHP 8.0+';
					$phpcs_file->addError( $error, $stack_ptr, 'ConstructorPropertyPromotion' );
				}
				break;
		}
	}

	/**
	 * Check if this is a constructor property promotion.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token in the stack.
	 *
	 * @return bool
	 */
	private function is_constructor_property_promotion( File $phpcs_file, $stack_ptr ) {
		$tokens = $phpcs_file->getTokens();

		// Check if we're in a function
		$function = $phpcs_file->findNext( T_FUNCTION, $stack_ptr + 1 );
		if ( $function === false ) {
			return false;
		}

		// Check if it's a constructor
		$function_name = $phpcs_file->findNext( T_STRING, $function );
		if ( $function_name === false || $tokens[ $function_name ]['content'] !== '__construct' ) {
			return false;
		}

		// Check if the visibility modifier is inside the constructor's parameters
		if ( isset( $tokens[ $function ]['parenthesis_opener'] ) && isset( $tokens[ $function ]['parenthesis_closer'] ) ) {
			return $stack_ptr > $tokens[ $function ]['parenthesis_opener']
				&& $stack_ptr < $tokens[ $function ]['parenthesis_closer'];
		}

		return false;
	}
}
