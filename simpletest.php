<?php

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
$string = 'test';
$array = array(
	'key' => 'value',
	'key2' => 'value2',
	'key3' => 'value3',
);

if ( $is_true == true && $string == 'test' ) {
	echo 'This is true';
} 

if ( $is_true == true || $string == 'test' ) {
	echo 'This is true';
} 

if ( $is_true == true || ( $string == 'test' && $string == 'dfsdfsd' ) ) {
	echo 'This is true';
} 

if ( $is_true == true && ( $string == 'test' && $string == 'dfsdfsd' ) ) {
	echo 'This is true';
} 

if ( $array['key'] == 'value' ) {
	echo 'This is true';
} 
