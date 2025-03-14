<?php

namespace Uncanny_Automator\Sniffs\WhiteSpace;

use PHP_CodeSniffer\Standards\Squiz\Sniffs\WhiteSpace\SuperfluousWhitespaceSniff as SquizSuperfluous;

/**
 * Extends Squiz SuperfluousWhitespaceSniff to set a lower priority.
 * This ensures whitespace fixes are applied after other sniffs.
 *
 * @package Uncanny_Automator
 * @subpackage Sniffs\WhiteSpace
 */
class SuperfluousWhitespaceSniff extends SquizSuperfluous {

	/**
	 * Priority value for this sniff.
	 * Lower numbers are processed last.
	 *
	 * @var int
	 */
	public $priority = -100;
} 