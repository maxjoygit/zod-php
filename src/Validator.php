<?php

namespace maxjoy\Validator;

use Exception;

use DateTime;

class ValidationError extends Exception {}

abstract class Schema {
    protected $isOptional = false;

    abstract public function validate($value);

    public function optional() {
        $this->isOptional = true;
        return $this;
    }

    protected function checkOptional($value) {
        return $this->isOptional && ($value === null || $value === '');
    }
}

class StringSchema extends Schema {
    private $minLength = 0;
    private $maxLength = PHP_INT_MAX;
    private $pattern = null;

    public function min($length) {
        $this->minLength = $length;
        return $this;
    }

    public function max($length) {
        $this->maxLength = $length;
        return $this;
    }

    public function matches($pattern) {
        $this->pattern = $pattern;
        return $this;
    }

    public function validate($value) {
        if ($this->checkOptional($value)) {
            return null;
        }

        if (!is_string($value)) {
            throw new ValidationError("Expected string, got " . gettype($value));
        }
        if (strlen($value) < $this->minLength) {
            throw new ValidationError("String must be at least {$this->minLength} characters long");
        }
        if (strlen($value) > $this->maxLength) {
            throw new ValidationError("String must be at most {$this->maxLength} characters long");
        }
        if ($this->pattern && !preg_match($this->pattern, $value)) {
            throw new ValidationError("String must match pattern: {$this->pattern}");
        }
        return $value;
    }
}

class NumberSchema extends Schema {
    private $min = PHP_INT_MIN;
    private $max = PHP_INT_MAX;

    public function min($value) {
        $this->min = $value;
        return $this;
    }

    public function max($value) {
        $this->max = $value;
        return $this;
    }

    public function validate($value) {
        if ($this->checkOptional($value)) {
            return null;
        }

        if (!is_numeric($value)) {
            throw new ValidationError("Expected number, got " . gettype($value));
        }
        if ($value < $this->min) {
            throw new ValidationError("Number must be at least {$this->min}");
        }
        if ($value > $this->max) {
            throw new ValidationError("Number must be at most {$this->max}");
        }
        return $value;
    }
}

class EmailSchema extends Schema {
    public function validate($value) {
        if ($this->checkOptional($value)) {
            return null;
        }

        if (!is_string($value)) {
            throw new ValidationError("Expected string for email, got " . gettype($value));
        }

        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationError("Invalid email format");
        }

        return $value;
    }
}

class TimestampSchema extends Schema {
    public function validate($value) {
        if ($this->checkOptional($value)) {
            return null;
        }

        if (!is_string($value)) {
            throw new ValidationError("Expected string for timestamp, got " . gettype($value));
        }

        $format = 'Y-m-d H:i:s';
        $d = DateTime::createFromFormat($format, $value);

        if (!$d || $d->format($format) !== $value) {
            throw new ValidationError("Invalid MySQL timestamp format. Expected format: YYYY-MM-DD HH:MM:SS");
        }

        return $value;
    }
}

class ArraySchema extends Schema {
    private $itemSchema;
    private $minItems = 0;
    private $maxItems = PHP_INT_MAX;
    private $isAssociative = false;

    public function items($schema) {
        $this->itemSchema = $schema;
        return $this;
    }

    public function min($count) {
        $this->minItems = $count;
        return $this;
    }

    public function max($count) {
        $this->maxItems = $count;
        return $this;
    }

    public function associative() {
        $this->isAssociative = true;
        return $this;
    }

    public function validate($value) {
        if ($this->checkOptional($value)) {
            return null;
        }

        if (!is_array($value)) {
            throw new ValidationError("Expected array, got " . gettype($value));
        }

        $count = count($value);
        if ($count < $this->minItems) {
            throw new ValidationError("Array must have at least {$this->minItems} items");
        }
        if ($count > $this->maxItems) {
            throw new ValidationError("Array must have at most {$this->maxItems} items");
        }

        $validated = [];
        foreach ($value as $key => $item) {
            if ($this->isAssociative && !is_string($key)) {
                throw new ValidationError("Expected string keys for associative array");
            }
            if (!$this->isAssociative && !is_int($key)) {
                throw new ValidationError("Expected integer keys for numbered array");
            }
            $validated[$key] = $this->itemSchema->validate($item);
        }

        return $validated;
    }
}

class AnySchema extends Schema {
    private $minLength = 0;
    private $maxLength = PHP_INT_MAX;
    private $pattern = null;

    public function min($length) {
        $this->minLength = $length;
        return $this;
    }

    public function max($length) {
        $this->maxLength = $length;
        return $this;
    }

    public function matches($pattern) {
        $this->pattern = $pattern;
        return $this;
    }

    public function validate($value) {
        if ($this->checkOptional($value)) {
            return null;
        }
        if (strlen($value) < $this->minLength) {
            throw new ValidationError("String must be at least {$this->minLength} characters long");
        }
        if (strlen($value) > $this->maxLength) {
            throw new ValidationError("String must be at most {$this->maxLength} characters long");
        }
        if ($this->pattern && !preg_match($this->pattern, $value)) {
            throw new ValidationError("String must match pattern: {$this->pattern}");
        }
        return $value;
    }

}

class ObjectSchema extends Schema {
    private $shape = [];

    public function schema(array $shape) {
        $this->shape = $shape;
        return $this;
    }

    public function validate($value) {
        if ($this->checkOptional($value)) {
            return null;
        }

        if (!is_array($value)) {
            throw new ValidationError("Expected object, got " . gettype($value));
        }
        $validated = [];
        foreach ($this->shape as $key => $schema) {
            if (!isset($value[$key]) && !$schema->isOptional) {
                throw new ValidationError("Missing required field: $key");
            }
            if (isset($value[$key]) || !$schema->isOptional) {
                $validated[$key] = $schema->validate($value[$key] ?? null);
            }
        }
        return $validated;
    }
}

class Validator {
    public static function string() {
        return new StringSchema();
    }

    public static function number() {
        return new NumberSchema();
    }

    public static function email() {
        return new EmailSchema();
    }

    public static function timestamp() {
        return new TimestampSchema();
    }

    public static function array() {
        return new ArraySchema();
    }

    public static function any() {
        return new AnySchema();
    }

    public static function object() {
        return new ObjectSchema();
    }
}
