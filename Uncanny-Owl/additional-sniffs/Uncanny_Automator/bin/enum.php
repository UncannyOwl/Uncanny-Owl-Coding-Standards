<?php
// Test enum (PHP 8.1)
enum Status {
	// Should trigger error: enum (8.1+)
	case DRAFT;
	case PUBLISHED;
	case ARCHIVED;
}
