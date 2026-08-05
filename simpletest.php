<?php

/**
 * Test function.
 *
 * @param mixed $value The value.
 * @return mixed
 */
function test_function( $value ) {
	return $value;
}

// translators: This is a test message
echo esc_html__( 'Test message', 'uncanny-automator' );

// This should trigger errors for missing translator comment and escaping
echo __( 'Another test message', 'uncanny-automator' );

// This should now only show a warning that can be ignored
echo esc_html__( 'Test with wrong textdomain', 'wrong-textdomain' );

$some_array = array(
	'key' => 'value',
	'key2' => 'value2',
	'key3' => 'value3',
);

echo 'Some text';

$value = 'test';

$is_true = true;
$string  = 'test';
$array   = array(
	'key' => 'value',
	'key2' => 'value2',
	'key3' => 'value3',
);

$object       = new stdClass();
$object->key  = 'value';
$object->key2 = 'value2';
$object->key3 = 'value3';

// Basic condition tests
if ( true == $is_true ) {
	echo 'This is true';
}

if ( true == $is_true && 'test' == $string ) {
	echo 'This is true';
}

if ( true == $is_true || 'test' == $string ) {
	echo 'This is true';
}

if ( true == $is_true || ( 'test' == $string && 'dfsdfsd' == $string ) ) {
	echo 'This is true';
}

if ( true == $is_true && ( 'test' == $string && 'dfsdfsd' == $string ) ) {
	echo 'This is true';
}

if ( $array['key'] == 'value' ) {
	echo 'This is true';
}

if ( $object->key == 'value' ) {
	echo 'This is true';
}

// More complex test cases for array access
$multi_array = array(
	'level1' => array(
		'level2' => array(
			'level3' => 'deep_value',
		),
	),
);

// Multi-level array access
if ( $multi_array['level1']['level2']['level3'] == 'deep_value' ) {
	echo 'Deep array access works';
}

// Complex object access
$complex_object                   = new stdClass();
$complex_object->property         = new stdClass();
$complex_object->property->nested = 'nested_value';

if ( $complex_object->property->nested == 'nested_value' ) {
	echo 'Nested object property access works';
}

// Array of objects
$array_of_objects = array(
	(object) array(
		'id' => 1,
		'name' => 'First',
	),
	(object) array(
		'id' => 2,
		'name' => 'Second',
	),
);

if ( $array_of_objects[0]->name == 'First' ) {
	echo 'Array of objects works';
}

// Different comparison operators
if ( 'test' === $string ) {
	echo 'Identical comparison';
}

if ( 'not_test' !== $string ) {
	echo 'Not identical comparison';
}

if ( 'not_test' != $string ) {
	echo 'Not equal comparison';
}

// Multiple conditions
if ( 'test' == $string && true === $is_true && $array['key'] == 'value' ) {
	echo 'Multiple conditions work';
}

// Function result comparison
if ( test_function( 'test_value' ) == 'test_value' ) {
	echo 'Function result comparison works';
}

// Using constants
define( 'TEST_CONSTANT', 'constant_value' );

if ( $string == TEST_CONSTANT ) {
	echo 'Constant comparison';
}

// Complex expressions
$a = 5;
$b = 10;

if ( ( $a + $b ) == 15 ) {
	echo 'Expression comparison works';
}

// Nested conditions with multiple levels
if ( true == $is_true && ( 'test' == $string || ( $array['key'] == 'value' && $object->key == 'value' ) ) ) {
	echo 'Complex nested conditions work';
}
