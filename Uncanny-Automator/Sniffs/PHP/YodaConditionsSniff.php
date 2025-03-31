<?php

namespace Uncanny_Automator\Sniffs\PHP;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Util\Tokens;

class YodaConditionsSniff implements Sniff {
	/**
	 * Returns the token types that this sniff is interested in.
	 *
	 * @return array
	 */
	public function register() {
		return array(
			T_IS_EQUAL,
			T_IS_IDENTICAL,
			T_IS_NOT_EQUAL,
			T_IS_NOT_IDENTICAL,
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
		
		// Only proceed if we're in an if statement
		if ( ! $this->is_in_condition( $phpcs_file, $stack_ptr ) ) {
			return;
		}
		
		// Get left and right sides of the comparison
		$left_token = $phpcs_file->findPrevious( Tokens::$emptyTokens, $stack_ptr - 1, null, true );
		$right_token = $phpcs_file->findNext( Tokens::$emptyTokens, $stack_ptr + 1, null, true );
		
		if ( false === $left_token || false === $right_token ) {
			return;
		}

		// Find the entire left side expression
		$left_side_info = $this->get_expression_info( $phpcs_file, $left_token, $stack_ptr - 1 );
		if ( $left_side_info['is_complex'] ) {
			return; // Skip complex expressions on the left
		}
        
		// If the left side is already a literal or constant, this is already a Yoda condition
		if ( $this->is_literal_or_constant( $tokens[$left_token] ) ) {
			return;
		}
		
		// If the right side is not a literal or constant, we don't need to swap it
		if ( ! $this->is_literal_or_constant( $tokens[$right_token] ) ) {
			return;
		}
		
		// Add an error message
		$fix = $phpcs_file->addFixableError(
			'Use Yoda conditions when checking a variable against a literal or constant',
			$stack_ptr,
			'NotYoda'
		);
		
		if ( $fix === true ) {
			// Get the content of both sides
			$left_content = $phpcs_file->getTokensAsString( $left_side_info['start'], $left_side_info['end'] - $left_side_info['start'] + 1 );
			$right_content = $tokens[$right_token]['content'];
			
			// Start the changeset
			$phpcs_file->fixer->beginChangeset();
			
			// Replace the left side with the right content
			for ( $i = $left_side_info['start']; $i <= $left_side_info['end']; $i++ ) {
				$phpcs_file->fixer->replaceToken( $i, '' );
			}
			$phpcs_file->fixer->addContentBefore( $stack_ptr, $right_content );
			
			// Replace the right side with the left content
			$phpcs_file->fixer->replaceToken( $right_token, $left_content );
			
			// End the changeset
			$phpcs_file->fixer->endChangeset();
		}
	}
	
	/**
	 * Check if we're inside a conditional statement.
	 * 
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the token.
	 * 
	 * @return bool Whether we're in a condition.
	 */
	private function is_in_condition( File $phpcs_file, $stack_ptr ) {
		$tokens = $phpcs_file->getTokens();
		
		if ( ! empty( $tokens[$stack_ptr]['nested_parenthesis'] ) ) {
			foreach ( $tokens[$stack_ptr]['nested_parenthesis'] as $start => $end ) {
				if ( isset( $tokens[$start]['parenthesis_owner'] ) ) {
					$owner = $tokens[$start]['parenthesis_owner'];
					if ( in_array( $tokens[$owner]['code'], array( T_IF, T_ELSEIF, T_WHILE, T_FOR, T_FOREACH ), true ) ) {
						return true;
					}
				}
			}
		}
		
		return false;
	}

	/**
	 * Gets information about an expression.
	 * 
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $start_ptr  The start token.
	 * @param int  $end_ptr    The end token.
	 * 
	 * @return array Information about the expression.
	 */
	private function get_expression_info( File $phpcs_file, $start_ptr, $end_ptr ) {
		$tokens = $phpcs_file->getTokens();
		$info = array(
			'start' => $start_ptr,
			'end' => $start_ptr,
			'is_complex' => false,
		);
		
		// Simple variable
		if ( $tokens[$start_ptr]['code'] === T_VARIABLE ) {
			$info['end'] = $start_ptr;
			
			// Check if it's an array access
			$next_ptr = $phpcs_file->findNext( Tokens::$emptyTokens, $start_ptr + 1, $end_ptr + 1, true );
			if ( $next_ptr !== false && $tokens[$next_ptr]['code'] === T_OPEN_SQUARE_BRACKET ) {
				// Find the closing bracket
				if ( isset( $tokens[$next_ptr]['bracket_closer'] ) ) {
					$info['end'] = $tokens[$next_ptr]['bracket_closer'];
				} else {
					$info['is_complex'] = true;
					return $info;
				}
			} 
			// Check if it's an object property
			elseif ( $next_ptr !== false && $tokens[$next_ptr]['code'] === T_OBJECT_OPERATOR ) {
				$property_ptr = $phpcs_file->findNext( Tokens::$emptyTokens, $next_ptr + 1, $end_ptr + 1, true );
				if ( $property_ptr !== false && $tokens[$property_ptr]['code'] === T_STRING ) {
					$info['end'] = $property_ptr;
				} else {
					$info['is_complex'] = true;
					return $info;
				}
			}
			
			return $info;
		}

		// It's not a variable or we don't understand the expression
		$info['is_complex'] = true;
		return $info;
	}
	
	/**
	 * Checks if a token is a literal or constant.
	 *
	 * @param array $token The token to check.
	 * 
	 * @return bool
	 */
	private function is_literal_or_constant( $token ) {
		$literal_tokens = array(
			T_LNUMBER,     // Integer literal
			T_DNUMBER,     // Float literal
			T_CONSTANT_ENCAPSED_STRING, // String literal
			T_TRUE,        // true
			T_FALSE,       // false
			T_NULL,        // null
		);

		return in_array( $token['code'], $literal_tokens, true );
	}
} 