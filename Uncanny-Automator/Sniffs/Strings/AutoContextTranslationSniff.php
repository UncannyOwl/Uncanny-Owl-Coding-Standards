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
	 * Array to track reported lines to avoid duplicate reports.
	 *
	 * @var array
	 */
	private $reported_lines = array();

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
	 * Array keys where __() should be converted to esc_html_x().
	 *
	 * @var array
	 */
	private $html_exception_keys = array(
		'tokenName',
		'label',
		'description',
		'message',
	);

	/**
	 * Constructor to initialize properties.
	 */
	public function __construct() {
		$this->reported_lines = array();
	}

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

		// Get the file path and determine if this is in an integration file
		$file_path = $phpcs_file->getFilename();
		$is_integration_file = $this->is_integration_file($file_path);

		// For non-integration files:
		// - Skip auto-fixing for escaped functions (esc_*__)
		// - Still show error for non-escaped functions (__)
		if (!$is_integration_file && strpos($token['content'], 'esc_') === 0) {
			return;
		}

		// Get the function call
		$function_call = $this->get_function_call( $phpcs_file, $stack_ptr );
		if ( empty( $function_call ) ) {
			return;
		}

		// Check if we've already reported this line
		$line = $token['line'];
		if ( isset( $this->reported_lines[ $line ] ) ) {
			return;
		}

		// Check if context is provided
		if ( ! $this->has_context( $function_call ) ) {
			// Check if this is in an exception case
			$is_exception = $this->is_html_exception( $phpcs_file, $stack_ptr );
			
			// For integration files, we can auto-fix escaped functions
			// For non-integration files, we only show errors for non-escaped functions
			if ( ($is_integration_file && in_array($token['content'], $this->escaped_functions, true)) || $is_exception ) {
				$error = 'Use %s with context instead of %s in integration strings. This helps translators better understand the context of the string.';
				$data = array(
					$is_exception ? 'esc_html_x' : $this->get_context_function( $token['content'] ),
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

			// Mark this line as reported
			$this->reported_lines[ $line ] = true;
		}
	}

	/**
	 * Check if the file is in an integrations directory.
	 *
	 * @param string $file_path The file path to check.
	 * @return bool True if this is an integration file.
	 */
	private function is_integration_file($file_path) {
		// Normalize path for consistent matching
		$normalized_path = str_replace('\\', '/', $file_path);
		
		// Check if this is in an integrations folder
		return preg_match('|/(?:src/)?integrations/([^/]+)/|', $normalized_path) === 1;
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
		// If the function is already esc_*_x, it has context
		if ( preg_match( '/esc_(?:html|attr)_x\(/', $function_call ) ) {
			return true;
		}

		// For _x functions, we want to convert to esc_html_x but preserve context
		if ( preg_match( '/_x\(/', $function_call ) ) {
			return false;
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
			'esc_html_e' => 'esc_html_x',
			'esc_attr__' => 'esc_attr_x',
			'esc_attr_e' => 'esc_attr_x',
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

		// Extract the original parameters
		$params = array();
		$current = $open_paren + 1;
		$param = '';

		while ( $current < $close_paren ) {
			if (!isset($tokens[$current])) {
				$current++;
				continue;
			}
			
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
			if (isset($tokens[$current])) {
				$phpcs_file->fixer->replaceToken( $current, '' );
			}
			$current++;
		}
		
		// For _x functions, preserve the original context
		if ( $function_name === '_x' && count( $params ) >= 3 ) {
			$new_params = $params[0] . ', ' . $params[1] . ', ' . $params[2];
		} else {
			// For other functions, use the integration name as context
			$integration_name = $this->get_integration_name( $phpcs_file->getFilename() );
			$new_params = $params[0] . ', ' . $integration_name . ', ' . end( $params );
		}
		
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
		// Normalize path for consistent matching
		$normalized_path = str_replace('\\', '/', $file_path);
		
		// Extract integration name from path
		if (preg_match('|/(?:src/)?integrations/([^/]+)/|', $normalized_path, $matches)) {
			if (!empty($matches[1])) {
				$integration = $matches[1];
				// Remove dashes and capitalize each word
				$integration = ucwords(str_replace('-', ' ', $integration));
				return "'" . $integration . "'";
			}
		}
		
		// Look at the file path for special directories like email, api, etc.
		if (preg_match('|/src/core/services/([^/]+)/|', $normalized_path, $matches)) {
			if (!empty($matches[1])) {
				$service = $matches[1];
				// Format the service name (e.g., "email" -> "Email")
				$service = ucwords(str_replace('-', ' ', $service));
				return "'" . $service . "'";
			}
		}
		
		// If we can't find the integration folder, return Automator
		return "'Automator'";
	}

	/**
	 * Check if the function is in an exception case where it should be converted to esc_html_x.
	 *
	 * @param File $phpcs_file The PHP_CodeSniffer file where the token was found.
	 * @param int  $stack_ptr  The position in the PHP_CodeSniffer file's token stack where the token was found.
	 * @return bool True if this is an exception case.
	 */
	private function is_html_exception( File $phpcs_file, $stack_ptr ) {
		$tokens = $phpcs_file->getTokens();
		
		// Look back for array keys
		$current = $stack_ptr;
		while ( $current > 0 ) {
			$token = $tokens[ $current ];
			
			if ( T_CONSTANT_ENCAPSED_STRING === $token['code'] ) {
				$key = trim( $token['content'], "'\"" );
				if ( in_array( $key, $this->html_exception_keys, true ) ) {
					return true;
				}
			}
			
			// Stop if we hit a comma or array bracket
			if ( T_COMMA === $token['code'] || T_ARRAY === $token['code'] ) {
				break;
			}
			
			$current--;
		}
		
		return false;
	}
} 