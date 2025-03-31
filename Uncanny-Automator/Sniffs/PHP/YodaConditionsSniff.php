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

		// Don't process if the comparison is inside parentheses and is part of a larger condition
		if ( false === $this->should_process_comparison( $phpcs_file, $stack_ptr ) ) {
			return;
		}

		// Get tokens on the left and right of the comparison operator
		$left_ptr = $this->find_start_of_comparison_side( $phpcs_file, $stack_ptr - 1 );
		if ( false === $left_ptr ) {
			return;
		}

		$right_ptr = $this->find_end_of_comparison_side( $phpcs_file, $stack_ptr + 1 );
		if ( false === $right_ptr ) {
			return;
		}

		// If left side is already a literal/constant, this is already a Yoda condition
		if ( $this->is_literal_or_constant( $tokens[$left_ptr] ) ) {
			return;
		}

		// If right side is not a literal/constant, not a case we want to fix
		$right_offset = 0;
		$right_token = $phpcs_file->findNext( Tokens::$emptyTokens, $stack_ptr + 1, null, true );
		if ( ! $this->is_literal_or_constant( $tokens[$right_token] ) ) {
			return;
		}

		// Find all the tokens that make up the left and right sides
		$left_side_tokens = $this->get_side_tokens( $phpcs_file, $left_ptr, $stack_ptr - 1 );
		$right_side_tokens = $this->get_side_tokens( $phpcs_file, $stack_ptr + 1, $right_ptr );

		// Add the error
		$fix = $phpcs_file->addFixableError(
			'Use Yoda conditions when checking a variable against a literal or constant',
			$stack_ptr,
			'NotYoda'
		);

		if ( $fix === true ) {
			// Get the content of both sides
			$left_content = '';
			foreach ( $left_side_tokens as $token ) {
				$left_content .= $tokens[$token]['content'];
			}
			
			$right_content = '';
			foreach ( $right_side_tokens as $token ) {
				$right_content .= $tokens[$token]['content'];
			}
			
			// Perform the swap
			$phpcs_file->fixer->beginChangeset();
			
			// Replace left side with right content
			foreach ( $left_side_tokens as $token ) {
				$phpcs_file->fixer->replaceToken( $token, '' );
			}
			$phpcs_file->fixer->addContentBefore( $stack_ptr, $right_content );
			
			// Replace right side with left content
			foreach ( $right_side_tokens as $token ) {
				$phpcs_file->fixer->replaceToken( $token, '' );
			}
			$phpcs_file->fixer->addContent( $stack_ptr, ' ' . $left_content );
			
			$phpcs_file->fixer->endChangeset();
		}
	}

	/**
	 * Checks if we should process this comparison.
	 * 
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token in the stack.
	 * 
	 * @return bool Whether to process this comparison.
	 */
	private function should_process_comparison( File $phpcs_file, $stack_ptr ) {
		$tokens = $phpcs_file->getTokens();
		
		// Only process comparisons that aren't already part of a more complex expression
		if ( isset( $tokens[$stack_ptr]['nested_parenthesis'] ) ) {
			foreach ( $tokens[$stack_ptr]['nested_parenthesis'] as $open => $close ) {
				// Check if the opening parenthesis is part of a conditional
				$prev = $phpcs_file->findPrevious( Tokens::$emptyTokens, $open - 1, null, true );
				if ( $prev !== false && in_array( $tokens[$prev]['code'], array( T_IF, T_ELSEIF, T_FOR, T_FOREACH, T_WHILE ), true ) ) {
					// This is part of a conditional statement, proceed
					return true;
				}
			}
		}
		
		return true;
	}

	/**
	 * Finds the start of the comparison side.
	 * 
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $start_ptr  The position to start looking from.
	 * 
	 * @return int|false The start of the comparison side, or false on failure.
	 */
	private function find_start_of_comparison_side( File $phpcs_file, $start_ptr ) {
		$tokens = $phpcs_file->getTokens();
		
		$ptr = $phpcs_file->findPrevious( Tokens::$emptyTokens, $start_ptr, null, true );
		if ( false === $ptr ) {
			return false;
		}
		
		// Handle array access and object properties
		if ( $tokens[$ptr]['code'] === T_CLOSE_SQUARE_BRACKET ) {
			if ( isset( $tokens[$ptr]['bracket_opener'] ) ) {
				// Find the variable before the bracket
				$bracket_open = $tokens[$ptr]['bracket_opener'];
				$var_ptr = $phpcs_file->findPrevious( Tokens::$emptyTokens, $bracket_open - 1, null, true );
				if ( $var_ptr !== false && $tokens[$var_ptr]['code'] === T_VARIABLE ) {
					return $var_ptr;
				}
			}
		} elseif ( $tokens[$ptr]['code'] === T_STRING ) {
			// Check if this is an object property
			$prev = $phpcs_file->findPrevious( Tokens::$emptyTokens, $ptr - 1, null, true );
			if ( $prev !== false && $tokens[$prev]['code'] === T_OBJECT_OPERATOR ) {
				$var_ptr = $phpcs_file->findPrevious( Tokens::$emptyTokens, $prev - 1, null, true );
				if ( $var_ptr !== false && $tokens[$var_ptr]['code'] === T_VARIABLE ) {
					return $var_ptr;
				}
			}
		}
		
		return $ptr;
	}

	/**
	 * Finds the end of the comparison side.
	 * 
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $start_ptr  The position to start looking from.
	 * 
	 * @return int|false The end of the comparison side, or false on failure.
	 */
	private function find_end_of_comparison_side( File $phpcs_file, $start_ptr ) {
		$tokens = $phpcs_file->getTokens();
		
		$ptr = $phpcs_file->findNext( Tokens::$emptyTokens, $start_ptr, null, true );
		if ( false === $ptr ) {
			return false;
		}
		
		// For literals, just return them directly
		if ( $this->is_literal_or_constant( $tokens[$ptr] ) ) {
			return $ptr;
		}
		
		// For variables, handle array access and object properties
		if ( $tokens[$ptr]['code'] === T_VARIABLE ) {
			$next = $phpcs_file->findNext( Tokens::$emptyTokens, $ptr + 1, null, true );
			if ( $next !== false ) {
				if ( $tokens[$next]['code'] === T_OPEN_SQUARE_BRACKET && isset( $tokens[$next]['bracket_closer'] ) ) {
					return $tokens[$next]['bracket_closer'];
				} elseif ( $tokens[$next]['code'] === T_OBJECT_OPERATOR ) {
					$next_next = $phpcs_file->findNext( Tokens::$emptyTokens, $next + 1, null, true );
					if ( $next_next !== false && $tokens[$next_next]['code'] === T_STRING ) {
						return $next_next;
					}
				}
			}
		}
		
		return $ptr;
	}

	/**
	 * Gets all tokens that make up a side of a comparison.
	 * 
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $start_ptr  The position to start from.
	 * @param int  $end_ptr    The position to end at.
	 * 
	 * @return array Array of token pointers.
	 */
	private function get_side_tokens( File $phpcs_file, $start_ptr, $end_ptr ) {
		$tokens = $phpcs_file->getTokens();
		$side_tokens = array();
		
		for ( $i = $start_ptr; $i <= $end_ptr; $i++ ) {
			if ( $tokens[$i]['code'] !== T_WHITESPACE ) {
				$side_tokens[] = $i;
			}
		}
		
		return $side_tokens;
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