<?php

/**
 * Test class to verify PHP compatibility sniffs
 * Target: PHP 7.3 compatibility
 *
 * This file intentionally uses features from PHP 7.4+ to test sniffs
 */
class Compatibility_Test {
	private const TEST = 'test';
	// PHP 7.4 Features
	private string $typed_property = 'test';          // Should trigger error: typed property (7.4+)
	private array $items           = array();                        // Should trigger error: typed property (7.4+)

	// PHP 8.0 Types
	private mixed $mixed_type;                        // Should trigger error: mixed type (8.0+)
	private ?string $nullable_type;                   // Should trigger error: nullable standalone type (8.0+)
	private UnionType|null $union_type;              // Should trigger error: union types (8.0+)

	public function test_php74_features() {
		// Arrow function
		$double = fn( $x ) => $x * 2;                  // Should trigger error: arrow function (7.4+)

		// Null coalescing assignment
		$array        = array();
		$array['key'] ??= 'default';                 // Should trigger error: null coalescing (7.4+)

		// Array spread
		$array1 = array( 1, 2, 3 );
		$array2 = array( 0, ...$array1, 4 );                // Should trigger error: array spread (7.4+)

		// Numeric literal separator
		$million = 1_000_000;                        // Should trigger error: numeric separator (7.4+)
	}

	// PHP 8.0 Features
	#[Attribute]                                      // Should trigger error: attributes (8.0+)
	public function test_php80_features(
		private string $promoted = '',                // Should trigger error: constructor promotion (8.0+)
		public int $promoted2 = 0                     // Should trigger error: constructor promotion (8.0+)
	) {
		// Named arguments
		$this->some_function( name: 'John', age: 30 ); // Should trigger error: named arguments (8.0+)

		// Nullsafe operator
		$result = $object?->method()?->property;      // Should trigger error: nullsafe operator (8.0+)

		// Match expression
		$result = match ( $value ) {                     // Should trigger error: match expression (8.0+)
			1 => 'one',
			2 => 'two',
			default => 'other'
		};

		// Throw expression
		$value = $this->validate() ?? throw new Exception(); // Should trigger error: throw expression (8.0+)

		// Non-capturing catches
		try {
			// Some code
		} catch ( TypeError ) {                        // Should trigger error: non-capturing catch (8.0+)
			// Handle error
		}

		// Trailing comma in parameter lists
		$result = $this->some_function(
			'test',
			123,                                      // Should trigger error: trailing comma (8.0+)
		);
	}

	// PHP 8.1 Features
	public readonly string $read_only;                // Should trigger error: readonly property (8.1+)

	public function test_php81_features(): never {
		// Should trigger error: never type (8.1+)
		// First class callable syntax
		$callable = strlen( ... );                      // Should trigger error: first class callable (8.1+)

		// Pure intersection types
		private Countable&Iterator $iterator;         // Should trigger error: intersection types (8.1+)

		// Array unpacking with string keys
		$array1 = array( 'key' => 'value' );
		$array2 = array(
			'other' => 'val',
			...$array1,
		);    // Should trigger error: string key array unpacking (8.1+)

		throw new Exception( 'never returns' );
	}

	// PHP 8.2 Features
	readonly class ReadOnlyClass {
					// Should trigger error: readonly class (8.2+)
		public function test( null $param ) {}          // Should trigger error: standalone null type (8.2+)
		public function test2( false $param ) {}        // Should trigger error: standalone false type (8.2+)
	}

	// PHP 8.3 Features
	class Test_83 {
		public const string TYPE = 'test';            // Should trigger error: typed constants (8.3+)

		public function test() {
			$class = new readonly class() {};           // Should trigger error: readonly anonymous class (8.3+)
			$const = self::{CONSTANT};                // Should trigger error: dynamic class constant (8.3+)
		}
	}

	private function some_function( string $name, int $age ) {
		return "$name is $age years old";
	}
}
