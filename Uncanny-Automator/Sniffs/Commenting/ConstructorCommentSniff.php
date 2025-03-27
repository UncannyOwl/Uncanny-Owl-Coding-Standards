<?php

namespace Uncanny_Automator\Sniffs\Commenting;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Ensures constructors have proper documentation.
 *
 * @package Uncanny_Automator
 * @subpackage Sniffs\Commenting
 */
class ConstructorCommentSniff implements Sniff {

	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return array
	 */
	public function register() {
		return array( T_FUNCTION );
	}

	/**
	 * Processes this test when one of its tokens is encountered.
	 *
	 * @param File $phpcsFile The PHP_CodeSniffer file where the token was found.
	 * @param int  $stackPtr  The position in the PHP_CodeSniffer file's token stack where the token was found.
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		$tokens = $phpcsFile->getTokens();
		$functionToken = $tokens[$stackPtr];

		// Check if this is a constructor
		$className = $this->getClassName( $phpcsFile, $stackPtr );
		if ( empty( $className ) ) {
			return;
		}

		$functionName = $phpcsFile->getDeclarationName( $stackPtr );
		if ( $functionName !== '__construct' ) {
			return;
		}

		// Check for existing comment
		$commentStart = $phpcsFile->findPrevious( T_DOC_COMMENT_OPEN_TAG, $stackPtr - 1 );
		if ( false !== $commentStart ) {
			return; // Comment exists, skip
		}

		// Create the fix
		$fix = $phpcsFile->addFixableWarning(
			'Constructor is missing documentation comment.',
			$stackPtr,
			'MissingConstructorComment'
		);

		if ( $fix === true ) {
			// Get the indentation of the function
			$indent = '';
			$indentToken = $phpcsFile->findPrevious( T_WHITESPACE, $stackPtr - 1, null, true );
			if ( false !== $indentToken ) {
				$indent = $tokens[$indentToken]['content'];
			}

			// Check if constructor returns anything
			$hasReturn = $this->hasReturnStatement( $phpcsFile, $stackPtr );

			// Get constructor parameters
			$params = $this->getParameters( $phpcsFile, $stackPtr );

			// Create the comment block
			$comment = array(
				$indent . '/**',
				$indent . ' * Constructor.',
			);

			// Add parameter documentation if there are parameters
			if ( ! empty( $params ) ) {
				$comment[] = $indent . ' *';
				foreach ( $params as $param ) {
					$comment[] = $indent . ' * @param ' . $param['type'] . ' $' . $param['name'] . ' ' . $param['description'];
				}
			}

			// Only add @return if the constructor returns something
			if ( $hasReturn ) {
				$comment[] = $indent . ' *';
				$comment[] = $indent . ' * @return mixed';
			}

			$comment[] = $indent . ' */';

			// Add the comment
			$phpcsFile->fixer->beginChangeset();
			$phpcsFile->fixer->addNewlineBefore( $stackPtr );
			$phpcsFile->fixer->addContentBefore( $stackPtr, implode( $phpcsFile->eolChar, $comment ) . $phpcsFile->eolChar );
			$phpcsFile->fixer->endChangeset();
		}
	}

	/**
	 * Get the class name for the current function.
	 *
	 * @param File $phpcsFile The PHP_CodeSniffer file where the token was found.
	 * @param int  $stackPtr  The position in the PHP_CodeSniffer file's token stack where the token was found.
	 * @return string|null The class name or null if not found.
	 */
	protected function getClassName( File $phpcsFile, $stackPtr ) {
		$tokens = $phpcsFile->getTokens();

		// Find the class declaration
		$classPtr = $phpcsFile->findPrevious( T_CLASS, $stackPtr - 1 );
		if ( false === $classPtr ) {
			return null;
		}

		// Get the class name
		$classNamePtr = $phpcsFile->findNext( T_STRING, $classPtr + 1 );
		if ( false === $classNamePtr ) {
			return null;
		}

		return $tokens[$classNamePtr]['content'];
	}

	/**
	 * Check if the constructor has a return statement.
	 *
	 * @param File $phpcsFile The PHP_CodeSniffer file where the token was found.
	 * @param int  $stackPtr  The position in the PHP_CodeSniffer file's token stack where the token was found.
	 * @return bool True if the constructor returns something.
	 */
	protected function hasReturnStatement( File $phpcsFile, $stackPtr ) {
		$tokens = $phpcsFile->getTokens();
		$functionToken = $tokens[$stackPtr];

		// Get the function's scope
		$scopeStart = $functionToken['scope_opener'];
		$scopeEnd = $functionToken['scope_closer'];

		// Look for return statements
		$current = $scopeStart;
		while ( $current < $scopeEnd ) {
			$current = $phpcsFile->findNext( T_RETURN, $current + 1, $scopeEnd );
			if ( false === $current ) {
				break;
			}

			// Check if this return has a value
			$nextToken = $phpcsFile->findNext( Tokens::$emptyTokens, $current + 1, $scopeEnd, true );
			if ( false !== $nextToken && $tokens[$nextToken]['code'] !== T_SEMICOLON ) {
				return true;
			}

			$current++;
		}

		return false;
	}

	/**
	 * Get the constructor parameters.
	 *
	 * @param File $phpcsFile The PHP_CodeSniffer file where the token was found.
	 * @param int  $stackPtr  The position in the PHP_CodeSniffer file's token stack where the token was found.
	 * @return array Array of parameter information.
	 */
	protected function getParameters( File $phpcsFile, $stackPtr ) {
		$tokens = $phpcsFile->getTokens();
		$functionToken = $tokens[$stackPtr];

		// Get the function's parameters
		$params = array();
		$paramStart = $phpcsFile->findNext( T_OPEN_PARENTHESIS, $stackPtr );
		$paramEnd = $phpcsFile->findNext( T_CLOSE_PARENTHESIS, $paramStart );

		$current = $paramStart;
		while ( $current < $paramEnd ) {
			// Find the next parameter
			$current = $phpcsFile->findNext( array( T_VARIABLE, T_STRING ), $current + 1, $paramEnd );
			if ( false === $current ) {
				break;
			}

			// Get parameter type
			$type = 'mixed';
			$typeToken = $phpcsFile->findPrevious( array( T_STRING, T_NS_SEPARATOR ), $current - 1, $paramStart );
			if ( false !== $typeToken ) {
				$type = $tokens[$typeToken]['content'];
			}

			// Get parameter name
			$name = $tokens[$current]['content'];
			$name = ltrim( $name, '$' );

			// Get parameter description based on name
			$description = $this->getParameterDescription( $name );

			$params[] = array(
				'type' => $type,
				'name' => $name,
				'description' => $description,
			);

			$current++;
		}

		return $params;
	}

	/**
	 * Get a description for a parameter based on its name.
	 *
	 * @param string $name The parameter name.
	 * @return string The parameter description.
	 */
	protected function getParameterDescription( $name ) {
		// Common parameter descriptions based on name
		$descriptions = array(
			'args' => 'Arguments array.',
			'data' => 'Data array.',
			'value' => 'Value to process.',
			'id' => 'ID of the item.',
			'type' => 'Type of the item.',
			'name' => 'Name of the item.',
			'title' => 'Title of the item.',
			'description' => 'Description of the item.',
			'status' => 'Status of the item.',
			'options' => 'Options array.',
			'settings' => 'Settings array.',
			'config' => 'Configuration array.',
			'helper' => 'Helper object.',
			'integration' => 'Integration object.',
			'trigger' => 'Trigger object.',
			'action' => 'Action object.',
			'recipe' => 'Recipe object.',
			'user' => 'User object.',
			'post' => 'Post object.',
			'term' => 'Term object.',
			'comment' => 'Comment object.',
			'product' => 'Product object.',
			'order' => 'Order object.',
			'customer' => 'Customer object.',
			'form' => 'Form object.',
			'field' => 'Field object.',
			'file' => 'File object.',
			'image' => 'Image object.',
			'media' => 'Media object.',
			'url' => 'URL string.',
			'path' => 'Path string.',
			'key' => 'Key string.',
			'token' => 'Token string.',
			'code' => 'Code string.',
			'message' => 'Message string.',
			'label' => 'Label string.',
			'text' => 'Text string.',
			'content' => 'Content string.',
			'html' => 'HTML string.',
			'json' => 'JSON string.',
			'xml' => 'XML string.',
			'date' => 'Date string.',
			'time' => 'Time string.',
			'email' => 'Email address.',
			'phone' => 'Phone number.',
			'address' => 'Address string.',
			'country' => 'Country code.',
			'state' => 'State code.',
			'city' => 'City name.',
			'zip' => 'ZIP code.',
			'price' => 'Price value.',
			'amount' => 'Amount value.',
			'quantity' => 'Quantity value.',
			'count' => 'Count value.',
			'limit' => 'Limit value.',
			'offset' => 'Offset value.',
			'page' => 'Page number.',
			'per_page' => 'Items per page.',
			'orderby' => 'Order by field.',
			'order' => 'Sort order.',
			'search' => 'Search term.',
			'filter' => 'Filter criteria.',
			'callback' => 'Callback function.',
			'handler' => 'Handler function.',
			'processor' => 'Processor function.',
			'validator' => 'Validator function.',
			'formatter' => 'Formatter function.',
			'parser' => 'Parser function.',
			'generator' => 'Generator function.',
			'logger' => 'Logger object.',
			'debug' => 'Debug flag.',
			'verbose' => 'Verbose flag.',
			'strict' => 'Strict flag.',
			'required' => 'Required flag.',
			'optional' => 'Optional flag.',
			'default' => 'Default value.',
			'fallback' => 'Fallback value.',
			'parent' => 'Parent object.',
			'child' => 'Child object.',
			'root' => 'Root object.',
			'base' => 'Base object.',
			'core' => 'Core object.',
			'main' => 'Main object.',
			'primary' => 'Primary object.',
			'secondary' => 'Secondary object.',
			'current' => 'Current object.',
			'previous' => 'Previous object.',
			'next' => 'Next object.',
			'first' => 'First object.',
			'last' => 'Last object.',
			'active' => 'Active flag.',
			'enabled' => 'Enabled flag.',
			'disabled' => 'Disabled flag.',
			'visible' => 'Visible flag.',
			'hidden' => 'Hidden flag.',
			'public' => 'Public flag.',
			'private' => 'Private flag.',
			'protected' => 'Protected flag.',
			'static' => 'Static flag.',
			'final' => 'Final flag.',
			'abstract' => 'Abstract flag.',
			'interface' => 'Interface name.',
			'trait' => 'Trait name.',
			'class' => 'Class name.',
			'method' => 'Method name.',
			'function' => 'Function name.',
			'property' => 'Property name.',
			'constant' => 'Constant name.',
			'variable' => 'Variable name.',
			'parameter' => 'Parameter name.',
			'argument' => 'Argument name.',
			'return' => 'Return value.',
			'result' => 'Result value.',
			'output' => 'Output value.',
			'input' => 'Input value.',
			'data' => 'Data value.',
			'info' => 'Information value.',
			'meta' => 'Meta value.',
			'config' => 'Configuration value.',
			'settings' => 'Settings value.',
			'options' => 'Options value.',
			'preferences' => 'Preferences value.',
			'properties' => 'Properties value.',
			'attributes' => 'Attributes value.',
			'parameters' => 'Parameters value.',
			'arguments' => 'Arguments value.',
			'values' => 'Values array.',
			'items' => 'Items array.',
			'list' => 'List array.',
			'array' => 'Array value.',
			'object' => 'Object value.',
			'string' => 'String value.',
			'integer' => 'Integer value.',
			'float' => 'Float value.',
			'boolean' => 'Boolean value.',
			'null' => 'Null value.',
			'void' => 'Void value.',
			'mixed' => 'Mixed value.',
			'resource' => 'Resource value.',
			'callable' => 'Callable value.',
			'iterable' => 'Iterable value.',
			'array' => 'Array value.',
			'object' => 'Object value.',
			'string' => 'String value.',
			'int' => 'Integer value.',
			'float' => 'Float value.',
			'bool' => 'Boolean value.',
			'null' => 'Null value.',
			'void' => 'Void value.',
			'mixed' => 'Mixed value.',
			'resource' => 'Resource value.',
			'callable' => 'Callable value.',
			'iterable' => 'Iterable value.',
		);

		// Try to find a matching description
		foreach ( $descriptions as $key => $description ) {
			if ( strpos( $name, $key ) !== false ) {
				return $description;
			}
		}

		// Default description if no match found
		return 'Parameter value.';
	}
} 