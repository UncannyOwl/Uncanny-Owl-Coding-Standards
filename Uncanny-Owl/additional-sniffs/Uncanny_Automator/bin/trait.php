<?php

// Test class constant in traits (8.2+)
trait TestTrait {
	public const TEST = 'value';                     // Should trigger error: constant in trait (8.2+)
}
