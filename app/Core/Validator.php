<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Input validator for web forms and REST API payloads.
 */
final class Validator
{
    private array $errors = [];

    public static function make(array $data, array $rules): self
    {
        $instance = new self();
        $instance->validate($data, $rules);
        return $instance;
    }

    public function validate(array $data, array $rules): bool
    {
        $this->errors = [];

        foreach ($rules as $field => $ruleSpec) {
            $ruleList = is_string($ruleSpec) ? explode('|', $ruleSpec) : (array) $ruleSpec;
            $value = $data[$field] ?? null;
            $isEmpty = $value === null || $value === '';

            foreach ($ruleList as $rule) {
                $parts = explode(':', (string) $rule, 2);
                $name = $parts[0];
                $param = $parts[1] ?? null;

                if ($name === 'nullable' && $isEmpty) {
                    break;
                }

                if ($name === 'required' && $isEmpty) {
                    $this->addError($field, __('validation.required', ['field' => $field]));
                    break;
                }

                if ($isEmpty) {
                    continue;
                }

                switch ($name) {
                    case 'email':
                        if (!filter_var((string) $value, FILTER_VALIDATE_EMAIL)) {
                            $this->addError($field, __('validation.email', ['field' => $field]));
                        }
                        break;
                    case 'numeric':
                        if (!is_numeric($value)) {
                            $this->addError($field, __('validation.numeric', ['field' => $field]));
                        }
                        break;
                    case 'integer':
                        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                            $this->addError($field, __('validation.integer', ['field' => $field]));
                        }
                        break;
                    case 'min':
                        if (is_numeric($value) && $param !== null && (float) $value < (float) $param) {
                            $this->addError($field, __('validation.min_num', ['field' => $field, 'min' => $param]));
                        } elseif (is_string($value) && $param !== null && mb_strlen($value) < (int) $param) {
                            $this->addError($field, __('validation.min_str', ['field' => $field, 'min' => $param]));
                        }
                        break;
                    case 'max':
                        if (is_numeric($value) && $param !== null && (float) $value > (float) $param) {
                            $this->addError($field, __('validation.max_num', ['field' => $field, 'max' => $param]));
                        } elseif (is_string($value) && $param !== null && mb_strlen($value) > (int) $param) {
                            $this->addError($field, __('validation.max_str', ['field' => $field, 'max' => $param]));
                        }
                        break;
                    case 'in':
                        $allowed = $param !== null ? explode(',', $param) : [];
                        if (!in_array((string) $value, $allowed, true)) {
                            $this->addError($field, __('validation.in', ['field' => $field]));
                        }
                        break;
                    case 'date':
                        $d = date_create_from_format('Y-m-d', (string) $value);
                        if (!$d || $d->format('Y-m-d') !== (string) $value) {
                            $this->addError($field, __('validation.date', ['field' => $field]));
                        }
                        break;
                }
            }
        }

        return $this->errors === [];
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        foreach ($this->errors as $messages) {
            if (isset($messages[0])) {
                return $messages[0];
            }
        }
        return null;
    }

    private function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }
}
