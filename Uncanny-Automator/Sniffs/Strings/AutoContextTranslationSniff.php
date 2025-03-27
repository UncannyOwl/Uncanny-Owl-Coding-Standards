<?php

namespace Uncanny_Automator\Sniffs\Strings;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

/**
 * Sniff for checking translation function usage in integration files.
 *
 * @package Uncanny_Automator
 * @subpackage Sniffs\Strings
 */
class AutoContextTranslationSniff implements Sniff {

	/**
	 * Translation functions that require context.
	 *
	 * @var array
	 */
	private $translation_functions = array(
		'__',
		'_e',
		'_x',
		'esc_html__',
		'esc_html_e',
		'esc_attr__',
		'esc_attr_e',
	);

	/**
	 * Escaped translation functions that can be auto-fixed.
	 *
	 * @var array
	 */
	private $escaped_functions = array(
		'_x',
		'esc_html__',
		'esc_html_e',
		'esc_attr__',
		'esc_attr_e',
	);

	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return array
	 */
	public function register() {
		return array( T_STRING );
	}

	/**
	 * Processes this test when one of its tokens is encountered.
	 *
	 * @param File $phpcs_file The PHP_CodeSniffer file where the token was found.
	 * @param int  $stack_ptr  The position in the PHP_CodeSniffer file's token stack where the token was found.
	 */
	public function process( File $phpcs_file, $stack_ptr ) {
		$tokens = $phpcs_file->getTokens();
		$token = $tokens[ $stack_ptr ];

		// Check if this is a translation function
		if ( ! in_array( $token['content'], $this->translation_functions, true ) ) {
			return;
		}

		// Check if this is in an integration file
		$file_path = $phpcs_file->getFilename();
		if ( strpos( $file_path, '/integrations/' ) === false ) {
			return;
		}

		// Get the function call
		$function_call = $this->get_function_call( $phpcs_file, $stack_ptr );
		if ( empty( $function_call ) ) {
			return;
		}

		// Check if context is provided
		if ( ! $this->has_context( $function_call ) ) {
			// Handle escaped functions differently
			if ( in_array( $token['content'], $this->escaped_functions, true ) ) {
				$error = 'Use %s with context instead of %s in integration strings. This helps translators better understand the context of the string.';
				$data = array(
					$this->get_context_function( $token['content'] ),
					$token['content'],
				);

				$fix = $phpcs_file->addFixableError( $error, $stack_ptr, 'MissingContext', $data );

				if ( $fix ) {
					$this->fix_function_call( $phpcs_file, $stack_ptr, $function_call );
				}
			} else {
				// For non-escaped functions, just show an error
				$error = 'Use %s with context instead of %s in integration strings. This helps translators better understand the context of the string. Choose the appropriate escaping function (%s) based on the content type.';
				$data = array(
					$this->get_context_function( $token['content'] ),
					$token['content'],
					$this->get_context_function( $token['content'] ),
				);

				$phpcs_file->addError( $error, $stack_ptr, 'MissingContext', $data );
			}
		}
	}

	/**
	 * Get the full function call including arguments.
	 *
	 * @param File $phpcs_file The PHP_CodeSniffer file where the token was found.
	 * @param int  $stack_ptr  The position in the PHP_CodeSniffer file's token stack where the token was found.
	 * @return string|null The function call or null if not found.
	 */
	private function get_function_call( File $phpcs_file, $stack_ptr ) {
		$tokens = $phpcs_file->getTokens();
		$start = $stack_ptr;
		$end = $stack_ptr;

		// Find the opening parenthesis
		$open_paren = $phpcs_file->findNext( T_OPEN_PARENTHESIS, $stack_ptr + 1 );
		if ( false === $open_paren ) {
			return null;
		}

		// Find the closing parenthesis
		$close_paren = $phpcs_file->findNext( T_CLOSE_PARENTHESIS, $open_paren + 1 );
		if ( false === $close_paren ) {
			return null;
		}

		// Get the full function call
		$function_call = '';
		for ( $i = $start; $i <= $close_paren; $i++ ) {
			$function_call .= $tokens[ $i ]['content'];
		}

		return $function_call;
	}

	/**
	 * Check if the function call includes a context parameter.
	 *
	 * @param string $function_call The function call to check.
	 * @return bool True if context is provided.
	 */
	private function has_context( $function_call ) {
		// If the function already ends with _x, it has context
		if ( preg_match( '/esc_(?:html|attr)_x\(/', $function_call ) ) {
			return true;
		}

		// For other functions, check if they have 3 parameters (text, domain, context)
		$comma_count = substr_count( $function_call, ',' );
		return $comma_count >= 2;
	}

	/**
	 * Get the appropriate context function name.
	 *
	 * @param string $function The original function name.
	 * @return string The context function name.
	 */
	private function get_context_function( $function ) {
		$context_functions = array(
			'__' => '_x',
			'_e' => '_ex',
			'_x' => 'esc_html_x',
			'esc_html__' => 'esc_html_x',
			'esc_html_e' => 'esc_html_ex',
			'esc_attr__' => 'esc_attr_x',
			'esc_attr_e' => 'esc_attr_ex',
		);

		return isset( $context_functions[ $function ] ) ? $context_functions[ $function ] : $function;
	}

	/**
	 * Fix the function call by adding context.
	 *
	 * @param File   $phpcs_file   The PHP_CodeSniffer file where the token was found.
	 * @param int    $stack_ptr    The position in the PHP_CodeSniffer file's token stack where the token was found.
	 * @param string $function_call The original function call.
	 */
	private function fix_function_call( File $phpcs_file, $stack_ptr, $function_call ) {
		$tokens = $phpcs_file->getTokens();
		$function_name = $tokens[ $stack_ptr ]['content'];
		$new_function = $this->get_context_function( $function_name );

		// Find the opening and closing parentheses
		$open_paren = $phpcs_file->findNext( T_OPEN_PARENTHESIS, $stack_ptr + 1 );
		$close_paren = $phpcs_file->findNext( T_CLOSE_PARENTHESIS, $open_paren + 1 );

		if ( false === $open_paren || false === $close_paren ) {
			return;
		}

		// Get the integration name from the file path
		$file_path = $phpcs_file->getFilename();
		$integration_name = $this->get_integration_name( $file_path );

		// Extract the original parameters
		$params = array();
		$current = $open_paren + 1;
		$param = '';

		while ( $current < $close_paren ) {
			$token = $tokens[ $current ];
			
			if ( T_CONSTANT_ENCAPSED_STRING === $token['code'] ) {
				$param = $token['content'];
				$params[] = $param;
			}
			
			$current++;
		}

		if ( count( $params ) < 2 ) {
			return;
		}

		// Apply the fix
		$phpcs_file->fixer->beginChangeset();
		
		// Replace the function name
		$phpcs_file->fixer->replaceToken( $stack_ptr, $new_function );
		
		// Replace everything between parentheses
		$current = $open_paren + 1;
		while ( $current < $close_paren ) {
			$phpcs_file->fixer->replaceToken( $current, '' );
			$current++;
		}
		
		// Add the new parameters in correct order: text, context, domain
		$new_params = $params[0] . ', ' . $integration_name . ', ' . $params[1];
		$phpcs_file->fixer->addContent( $open_paren, $new_params );
		
		$phpcs_file->fixer->endChangeset();
	}

	/**
	 * Get the integration name from the file path.
	 *
	 * @param string $file_path The file path.
	 * @return string The integration name.
	 */
	private function get_integration_name( $file_path ) {
		$path_parts = explode( '/', $file_path );
		$integration_index = array_search( 'integrations', $path_parts );
		
		if ( false !== $integration_index && isset( $path_parts[ $integration_index + 1 ] ) ) {
			$integration = $path_parts[ $integration_index + 1 ];
			// Remove dashes and capitalize each word
			$integration = ucwords( str_replace( '-', ' ', $integration ) );
			return "'" . $integration . "'";
		}

		return "'General'";
	}
} 