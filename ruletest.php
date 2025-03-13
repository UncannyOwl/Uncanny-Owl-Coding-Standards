<?php

// 1. Typed Properties (PHP 7.4+)
class test_typed_properties {
    public int $count; // PHP 7.4+ (should fail)
    public ?string $user_name = null; // PHP 7.4+ (should fail)
}

// 2. Arrow Function (PHP 7.4+)
$arrow_function = fn($x) => $x * 2; // PHP 7.4+ (should fail)

// 3. Null Coalescing Assignment (??=) (PHP 7.4+)
$data_value = null;
$data_value ??= "default_value"; // PHP 7.4+ (should fail)

// 4. Trailing Comma in Function Calls (PHP 7.3 doesn't support)
function test_trailing_comma($param_one, $param_two,) { // PHP 7.3 doesn't support trailing commas in function calls
    return $param_one + $param_two;
}

// 5. Named Arguments (PHP 8.0+)
function greet_user($user_name, $user_age) {
    return "Hello, $user_name! You are $user_age years old.";
}
echo greet_user(user_name: "John", user_age: 30); // PHP 8.0+ (should fail)

// 6. Union Types (PHP 8.0+)
function sum_numbers(int|float $num_one, int|float $num_two): int|float { // PHP 8.0+ (should fail)
    return $num_one + $num_two;
}

// 7. Match Expression (PHP 8.0+)
$test_value = 2;
$result_value = match ($test_value) { // PHP 8.0+ (should fail)
    1 => 'one',
    2 => 'two',
    3 => 'three',
};

// 8. Attributes (PHP 8.0+)
#[Deprecated] // PHP 8.0+ (should fail)
function old_function() {
    return "This function is deprecated.";
}

// 9. Constructor Property Promotion (PHP 8.0+)
class test_property_promotion {
    public function __construct(
        public string $user_name, // PHP 8.0+ (should fail)
        private int $user_age // PHP 8.0+ (should fail)
    ) {}
}

// 10. Mixed Type Declaration (PHP 8.0+)
function handle_data(mixed $data_value): mixed { // PHP 8.0+ (should fail, needs custom sniff)
    return $data_value;
}

// 11. Stringable Interface (PHP 8.0+)
class my_stringable_class implements Stringable { // PHP 8.0+ (should fail)
    public function __toString(): string {
        return "String representation";
    }
}

// 12. Nullsafe Operator (PHP 8.0+)
$user_profile = null;
$user_name = $user_profile?->account?->name; // PHP 8.0+ (should fail)

// 13. Static Return Type (PHP 8.0+)
class base_class {
    public static function create_instance(): static { // PHP 8.0+ (should fail)
        return new static();
    }
}

// 14. Throw Expressions (PHP 8.0+)
$value_check = 10;
$is_valid_value = $value_check > 0 ? true : throw new Exception("Value must be positive"); // PHP 8.0+ (should fail)

// 15. Weak Maps (PHP 8.0+)
$weak_map = new WeakMap(); // PHP 8.0+ (should fail)
$object_instance = new stdClass();
$weak_map[$object_instance] = "Some data";

$some_array = [
    'key' => 'value',
    'key2' => 'value2',
    'key3' => 'value3',
];

function space_test($value){
    return $value;
}