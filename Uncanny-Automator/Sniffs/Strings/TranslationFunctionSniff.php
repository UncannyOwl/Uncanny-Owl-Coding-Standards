<?php

namespace Uncanny_Automator\Sniffs\Strings;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

/**
 * Validates translation function usage.
 *
 * @package Uncanny_Automator
 * @subpackage Sniffs\Strings
 */
class TranslationFunctionSniff implements Sniff {

	/**
	 * Non-context translation functions that should trigger an error in integrations.
	 *
	 * @var array
	 */
	private $non_context_functions = array(
		'__',
		'_e',
		'_x',
		'esc_html__',
		'esc_html_e',
		'esc_attr__',
		'esc_attr_e',
	);

	/**
	 * Translation functions that require escaping.
	 *
	 * @var array
	 */
	private $unescaped_functions = array(
		'__',
		'_e',
	);

	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return array
	 */
	public function register(): array {
		return array(T_STRING);
	}

	/**
	 * Processes this test when one of its tokens is encountered.
	 *
	 * @param File $phpcs_file The PHP_CodeSniffer file where the token was found.
	 * @param int  $stack_ptr  The position in the PHP_CodeSniffer file's token stack where the token was found.
	 *
	 * @return void
	 */
	public function process(File $phpcs_file, $stack_ptr): void {
		$tokens = $phpcs_file->getTokens();
		$function_name = $tokens[$stack_ptr]['content'];

		// Check for unescaped translation functions
		if (in_array($function_name, $this->unescaped_functions, true)) {
			$phpcs_file->addError(
				'Translation function should use esc_html__(), esc_attr__(), or esc_html_x() for proper escaping and context',
				$stack_ptr,
				'UnescapedTranslation'
			);
		}

		// Check for context in integration files
		$filename = $phpcs_file->getFilename();
		if (false !== strpos($filename, '/src/integrations/')) {
			if (in_array($function_name, $this->non_context_functions, true)) {
				$context_alternative = $this->get_context_alternative($function_name, $phpcs_file);
				$phpcs_file->addError(
					'Use %s() with context instead of %s() in integration strings. This helps translators better understand the context of the string. Choose the appropriate escaping function (esc_html_x(), esc_attr_x(), etc.) based on the content type.',
					$stack_ptr,
					'NoContext',
					array($context_alternative, $function_name)
				);
			}
		}
	}

	/**
	 * Get the recommended context function alternative.
	 *
	 * @param string $function The current function name.
	 * @param File   $phpcs_file The PHP_CodeSniffer file where the token was found.
	 * @return string The recommended context function.
	 */
	private function get_context_alternative($function, File $phpcs_file): string {
		$filename = $phpcs_file->getFilename();
		$is_integration = false !== strpos($filename, '/src/integrations/');

		if ($is_integration) {
			switch ($function) {
				case '_e':
				case 'esc_html_e':
					return 'echo esc_html_x';
				case 'esc_attr__':
					return 'esc_attr_x';
				case 'esc_attr_e':
					return 'echo esc_attr_x';
				case 'esc_html__':
				case '__':
				case '_x':
					return 'esc_html_x';
				default:
					return 'esc_html_x';
			}
		} else {
			switch ($function) {
				case '__':
					return 'esc_html__';
				case '_e':
					return 'esc_html_e';
				case '_x':
					return 'esc_html_x';
				default:
					return 'esc_html__';
			}
		}
	}
} 