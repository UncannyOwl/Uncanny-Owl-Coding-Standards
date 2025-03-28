<?php

namespace Uncanny_Automator\Sniffs\Commenting;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Ensures functions have proper documentation.
 *
 * @package Uncanny_Automator
 * @subpackage Sniffs\Commenting
 */
class FunctionCommentAutoFixSniff implements Sniff {

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
		
		// Skip anonymous functions or closures
		if (!isset($tokens[$stackPtr]['parenthesis_opener'])) {
			return;
		}
		
		// Try to get the function name - if we can't, it's not a regular function declaration
		try {
			$functionName = $phpcsFile->getDeclarationName($stackPtr);
			if ($functionName === null) {
				return;
			}
		} catch (\Exception $e) {
			return;
		}
		
		// Skip if this is a constructor (handled by ConstructorCommentSniff)
		if ($functionName === '__construct') {
			return;
		}
		
		// Skip other magic methods
		if (strpos($functionName, '__') === 0) {
			return;
		}
		
		// Skip if the function has a scope_opener but not a scope_closer (like in interfaces)
		if (!isset($tokens[$stackPtr]['scope_opener']) || !isset($tokens[$stackPtr]['scope_closer'])) {
			return;
		}
		
		// Check for class definitions
		$classToken = $phpcsFile->findPrevious(T_CLASS, $stackPtr - 1, max(0, $stackPtr - 5));
		$interfaceToken = $phpcsFile->findPrevious(T_INTERFACE, $stackPtr - 1, max(0, $stackPtr - 5));
		
		// If we found a class/interface token close to this function, it's likely a class declaration, not a function
		if ($classToken !== false || $interfaceToken !== false) {
			$className = $phpcsFile->getDeclarationName($classToken !== false ? $classToken : $interfaceToken);
			
			// If the function name is the same as the class name, it's a class declaration
			if ($className === $functionName) {
				return;
			}
		}
		
		// Find the opening and closing parenthesis
		$openingParenthesis = $tokens[$stackPtr]['parenthesis_opener'];
		$closingParenthesis = $tokens[$stackPtr]['parenthesis_closer'];
		
		// Skip if we couldn't find parentheses
		if ($openingParenthesis === null || $closingParenthesis === null) {
			return;
		}
		
		// Find any modifiers (public, private, protected, static, etc.)
		$modifiers = [];
		$checkPos = $stackPtr - 1;
		$limit = max(0, $stackPtr - 10); // Only look at 10 tokens before to be safe
		
		while ($checkPos > $limit) {
			$checkPos = $phpcsFile->findPrevious(
				[T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_ABSTRACT, T_FINAL],
				$checkPos,
				$limit
			);
			
			if ($checkPos === false) {
				break;
			}
			
			$modifiers[$tokens[$checkPos]['code']] = $checkPos;
			$checkPos--;
		}
		
		// Check if there's already a docblock for this function
		$commentEnd = false;
		$firstModifier = !empty($modifiers) ? min(array_values($modifiers)) : $stackPtr;
		$docBlockEnd = $phpcsFile->findPrevious(T_DOC_COMMENT_CLOSE_TAG, $firstModifier - 1, max(0, $firstModifier - 20));
		
		if ($docBlockEnd !== false) {
			$docBlockStart = $phpcsFile->findPrevious(T_DOC_COMMENT_OPEN_TAG, $docBlockEnd - 1, max(0, $docBlockEnd - 100));
			
			if ($docBlockStart !== false) {
				// Check if the docblock is close enough to be considered for this function
				if (($tokens[$firstModifier]['line'] - $tokens[$docBlockEnd]['line']) <= 2) {
					return; // Docblock exists and is close, so skip
				}
			}
		}
		
		// Add the fixable error
		$fix = $phpcsFile->addFixableError(
			'Function %s() is missing a doc comment.',
			$stackPtr,
			'MissingFunctionComment',
			[$functionName]
		);
		
		if ($fix === true) {
			// Get the function's indentation
			$indent = $this->getIndentation($phpcsFile, $firstModifier);
			
			// Generate the docblock
			$docBlock = $this->generateDocBlock($phpcsFile, $stackPtr, $functionName, $indent);
			
			// Apply the fix
			$phpcsFile->fixer->beginChangeset();
			
			// Find the insertion point (before the first modifier or the function keyword)
			$insertionPoint = $firstModifier;
			
			// Deal with whitespace properly
			// First, find the line before our insertion point
			$prevLine = $phpcsFile->findPrevious([T_WHITESPACE, T_COMMENT], $insertionPoint - 1, null, true);
			
			if ($prevLine !== false) {
				// Add a newline after the previous line of code
				$phpcsFile->fixer->addContent($prevLine, $phpcsFile->eolChar);
				
				// If there is any whitespace between previous line and our insertion point, remove it
				for ($i = $prevLine + 1; $i < $insertionPoint; $i++) {
					if ($tokens[$i]['code'] === T_WHITESPACE) {
						$phpcsFile->fixer->replaceToken($i, '');
					}
				}
			}
			
			// Add the docblock with proper indentation, followed by exactly one newline
			$phpcsFile->fixer->addContentBefore($insertionPoint, $docBlock . $phpcsFile->eolChar . $indent);
			
			$phpcsFile->fixer->endChangeset();
		}
	}
	
	/**
	 * Get the indentation for a token.
	 *
	 * @param File $phpcsFile The file being scanned.
	 * @param int $stackPtr The position of the token.
	 * @return string The indentation string.
	 */
	protected function getIndentation(File $phpcsFile, $stackPtr) {
		$tokens = $phpcsFile->getTokens();
		
		// Find the first token on this line
		$firstTokenOnLine = $stackPtr;
		while (isset($tokens[$firstTokenOnLine - 1]) && 
			   $tokens[$firstTokenOnLine - 1]['line'] === $tokens[$stackPtr]['line']) {
			$firstTokenOnLine--;
		}
		
		// Check if there's whitespace at the start of the line
		$indent = '';
		if ($tokens[$firstTokenOnLine]['code'] === T_WHITESPACE) {
			$indent = $tokens[$firstTokenOnLine]['content'];
			// Make sure we only get the leading whitespace, not newlines
			$indent = preg_replace('/[\r\n]+/', '', $indent);
		}
		
		return $indent;
	}
	
	/**
	 * Generate a doc block for a function.
	 *
	 * @param File $phpcsFile The file being scanned.
	 * @param int $stackPtr The position of the function token.
	 * @param string $functionName The name of the function.
	 * @param string $indent The indentation to use.
	 * @return string The formatted docblock.
	 */
	protected function generateDocBlock(File $phpcsFile, $stackPtr, $functionName, $indent) {
		$tokens = $phpcsFile->getTokens();
		
		// Format the function name for the description
		$description = $this->formatFunctionName($functionName);
		
		// Get the function's parameters
		$params = $this->getParameters($phpcsFile, $stackPtr);
		
		// Check if the function returns anything
		$returnType = $this->getReturnType($phpcsFile, $stackPtr);
		$hasReturnValue = $this->hasReturnStatement($phpcsFile, $stackPtr);
		
		// Start building the docblock with proper indentation
		$docBlock = [];
		$docBlock[] = $indent . '/**';
		$docBlock[] = $indent . ' * ' . $description . '.';
		
		// Add parameter documentation
		if (!empty($params)) {
			$docBlock[] = $indent . ' *';
			foreach ($params as $param) {
				$docBlock[] = $indent . ' * @param ' . $param['type'] . ' $' . $param['name'] . ' ' . $param['description'];
			}
		}
		
		// Add return documentation if needed
		if ($hasReturnValue || ($returnType !== 'void' && $returnType !== '')) {
			if (empty($params)) {
				$docBlock[] = $indent . ' *';
			}
			$docBlock[] = $indent . ' * @return ' . ($returnType ?: 'mixed');
		}
		
		$docBlock[] = $indent . ' */';
		
		return implode($phpcsFile->eolChar, $docBlock);
	}
	
	/**
	 * Format a function name for the docblock description.
	 *
	 * @param string $functionName The function name.
	 * @return string The formatted description.
	 */
	protected function formatFunctionName($functionName) {
		// Convert snake_case to sentence case
		$formatted = str_replace('_', ' ', $functionName);
		$formatted = ucfirst($formatted);
		
		return $formatted;
	}
	
	/**
	 * Get the parameters for a function.
	 *
	 * @param File $phpcsFile The file being scanned.
	 * @param int $stackPtr The position of the function token.
	 * @return array The parameters with their types and descriptions.
	 */
	protected function getParameters(File $phpcsFile, $stackPtr) {
		$tokens = $phpcsFile->getTokens();
		
		// Skip if we don't have parenthesis information
		if (!isset($tokens[$stackPtr]['parenthesis_opener']) || !isset($tokens[$stackPtr]['parenthesis_closer'])) {
			return [];
		}
		
		$opener = $tokens[$stackPtr]['parenthesis_opener'];
		$closer = $tokens[$stackPtr]['parenthesis_closer'];
		
		$params = [];
		$paramStart = $opener;
		
		// Loop through each token between the parentheses
		for ($i = $paramStart + 1; $i < $closer; $i++) {
			// Looking for variable names
			if ($tokens[$i]['code'] === T_VARIABLE) {
				$paramName = ltrim($tokens[$i]['content'], '$');
				
				// Try to find a type hint
				$typeHint = 'mixed';
				$prev = $phpcsFile->findPrevious([T_COMMA, T_OPEN_PARENTHESIS], $i - 1, $opener);
				
				if ($prev !== false) {
					$typeToken = $phpcsFile->findNext(
						[T_STRING, T_NS_SEPARATOR, T_ARRAY_HINT, T_CALLABLE, T_SELF, T_PARENT],
						$prev + 1,
						$i
					);
					
					if ($typeToken !== false) {
						$typeHint = $tokens[$typeToken]['content'];
					}
				}
				
				// Get a description based on the parameter name
				$description = $this->getParameterDescription($paramName);
				
				$params[] = [
					'name' => $paramName,
					'type' => $typeHint,
					'description' => $description,
				];
			}
		}
		
		return $params;
	}
	
	/**
	 * Check if a function has a return statement with a value.
	 *
	 * @param File $phpcsFile The file being scanned.
	 * @param int $stackPtr The position of the function token.
	 * @return bool True if the function returns a value.
	 */
	protected function hasReturnStatement(File $phpcsFile, $stackPtr) {
		$tokens = $phpcsFile->getTokens();
		
		// Skip if we don't have scope information
		if (!isset($tokens[$stackPtr]['scope_opener']) || !isset($tokens[$stackPtr]['scope_closer'])) {
			return false;
		}
		
		$scopeOpener = $tokens[$stackPtr]['scope_opener'];
		$scopeCloser = $tokens[$stackPtr]['scope_closer'];
		
		// Look for return statements in the function body
		$current = $scopeOpener;
		
		while (($returnToken = $phpcsFile->findNext([T_RETURN], $current + 1, $scopeCloser)) !== false) {
			// Check if this return has a value
			$nextToken = $phpcsFile->findNext(Tokens::$emptyTokens, $returnToken + 1, $scopeCloser, true);
			
			if ($nextToken !== false && $tokens[$nextToken]['code'] !== T_SEMICOLON) {
				return true; // Found a return with a value
			}
			
			$current = $returnToken;
		}
		
		return false;
	}
	
	/**
	 * Get the return type for a function.
	 *
	 * @param File $phpcsFile The file being scanned.
	 * @param int $stackPtr The position of the function token.
	 * @return string The return type.
	 */
	protected function getReturnType(File $phpcsFile, $stackPtr) {
		$tokens = $phpcsFile->getTokens();
		
		// Skip if we don't have parenthesis information
		if (!isset($tokens[$stackPtr]['parenthesis_closer'])) {
			return 'void';
		}
		
		$closer = $tokens[$stackPtr]['parenthesis_closer'];
		
		// Look for a return type after the closing parenthesis
		$colon = $phpcsFile->findNext([T_COLON], $closer + 1, $closer + 5);
		
		if ($colon !== false) {
			// Look for the type after the colon
			$type = $phpcsFile->findNext(
				[T_STRING, T_NS_SEPARATOR, T_CALLABLE, T_SELF, T_PARENT, T_ARRAY_HINT, T_NULLABLE],
				$colon + 1,
				$colon + 5
			);
			
			if ($type !== false) {
				return $tokens[$type]['content'];
			}
		}
		
		// If no return type declaration, check if the function returns a value
		if ($this->hasReturnStatement($phpcsFile, $stackPtr)) {
			return 'mixed';
		}
		
		return 'void';
	}
	
	/**
	 * Get a description for a parameter based on its name.
	 *
	 * @param string $name The parameter name.
	 * @return string The parameter description.
	 */
	protected function getParameterDescription($name) {
		// Common parameter descriptions based on name
		$descriptions = [
			'id' => 'The ID.',
			'user_id' => 'The user ID.',
			'post_id' => 'The post ID.',
			'comment_id' => 'The comment ID.',
			'term_id' => 'The term ID.',
			'type' => 'The type.',
			'name' => 'The name.',
			'title' => 'The title.',
			'slug' => 'The slug.',
			'description' => 'The description.',
			'content' => 'The content.',
			'text' => 'The text.',
			'html' => 'The HTML content.',
			'url' => 'The URL.',
			'link' => 'The link.',
			'path' => 'The path.',
			'file' => 'The file.',
			'dir' => 'The directory.',
			'directory' => 'The directory.',
			'size' => 'The size.',
			'width' => 'The width.',
			'height' => 'The height.',
			'length' => 'The length.',
			'count' => 'The count.',
			'number' => 'The number.',
			'index' => 'The index.',
			'position' => 'The position.',
			'order' => 'The order.',
			'key' => 'The key.',
			'value' => 'The value.',
			'data' => 'The data.',
			'args' => 'The arguments.',
			'params' => 'The parameters.',
			'options' => 'The options.',
			'settings' => 'The settings.',
			'config' => 'The configuration.',
			'meta' => 'The meta data.',
			'context' => 'The context.',
			'format' => 'The format.',
			'style' => 'The style.',
			'class' => 'The class.',
			'object' => 'The object.',
			'instance' => 'The instance.',
			'callback' => 'The callback function.',
			'handler' => 'The handler function.',
			'function' => 'The function.',
			'method' => 'The method.',
			'action' => 'The action.',
			'filter' => 'The filter.',
			'query' => 'The query.',
			'search' => 'The search term.',
			'request' => 'The request.',
			'response' => 'The response.',
			'result' => 'The result.',
			'output' => 'The output.',
			'input' => 'The input.',
			'source' => 'The source.',
			'target' => 'The target.',
			'destination' => 'The destination.',
			'from' => 'The source.',
			'to' => 'The destination.',
			'start' => 'The start.',
			'end' => 'The end.',
			'begin' => 'The beginning.',
			'finish' => 'The finish.',
			'first' => 'The first item.',
			'last' => 'The last item.',
			'prefix' => 'The prefix.',
			'suffix' => 'The suffix.',
			'delimiter' => 'The delimiter.',
			'separator' => 'The separator.',
			'message' => 'The message.',
			'error' => 'The error.',
			'exception' => 'The exception.',
			'status' => 'The status.',
			'state' => 'The state.',
			'condition' => 'The condition.',
			'flag' => 'The flag.',
			'use' => 'Whether to use.',
			'enabled' => 'Whether enabled.',
			'disabled' => 'Whether disabled.',
			'active' => 'Whether active.',
			'visible' => 'Whether visible.',
			'hidden' => 'Whether hidden.',
			'show' => 'Whether to show.',
			'hide' => 'Whether to hide.',
			'recursive' => 'Whether to process recursively.',
			'force' => 'Whether to force.',
			'skip' => 'Whether to skip.',
			'overwrite' => 'Whether to overwrite.',
			'replace' => 'Whether to replace.',
			'default' => 'The default value.',
			'fallback' => 'The fallback value.',
		];
		
		// Try to find a specific match
		if (isset($descriptions[$name])) {
			return $descriptions[$name];
		}
		
		// Look for partial matches
		foreach ($descriptions as $key => $description) {
			if (strpos($name, $key) !== false) {
				return $description;
			}
		}
		
		// No match found, return a generic description
		return 'The ' . str_replace('_', ' ', $name) . '.';
	}
} 