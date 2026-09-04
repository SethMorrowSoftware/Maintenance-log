<?php

declare(strict_types=1);

namespace App;

use Throwable;

/**
 * Form and API input validation.
 *
 *     $v = Validator::make($_POST, [
 *         'name'        => 'required|string|max:150',
 *         'asset_tag'   => 'required|string|max:60|unique:assets,asset_tag,' . $id,
 *         'category_id' => 'nullable|int|exists:asset_categories,id',
 *         'status'      => 'required|in:in_service,out_of_service,maintenance,retired',
 *         'cost'        => 'nullable|decimal|min:0',
 *     ]);
 *
 *     if ($v->fails()) {
 *         Flash::reject($v->errors(), $_POST);
 *         redirect(url('asset-edit.php', ['id' => $id]));
 *     }
 *
 *     $data = $v->validated();
 *
 * Rules containing a pipe (a regex, typically) must be supplied as an array
 * instead of a pipe-delimited string.
 */
final class Validator
{
    /** @var array<string, mixed> */
    private array $data;

    /** @var array<string, string|list<string>> */
    private array $rules;

    /** @var array<string, string> custom "field.rule" => message overrides */
    private array $messages;

    /** @var array<string, string> field => human label */
    private array $labels;

    /** @var array<string, string> field => first error */
    private array $errors = [];

    /** @var array<string, mixed> cleaned values for fields that passed */
    private array $validated = [];

    private bool $ran = false;

    /**
     * @param array<string, mixed>              $data
     * @param array<string, string|list<string>> $rules
     * @param array<string, string>             $messages
     * @param array<string, string>             $labels
     */
    private function __construct(array $data, array $rules, array $messages, array $labels)
    {
        $this->data     = $data;
        $this->rules    = $rules;
        $this->messages = $messages;
        $this->labels   = $labels;
    }

    /**
     * @param array<string, mixed>               $data
     * @param array<string, string|list<string>> $rules
     * @param array<string, string>              $messages
     * @param array<string, string>              $labels
     */
    public static function make(array $data, array $rules, array $messages = [], array $labels = []): self
    {
        return new self($data, $rules, $messages, $labels);
    }

    // -------------------------------------------------------------------------
    // Results
    // -------------------------------------------------------------------------

    public function passes(): bool
    {
        $this->run();

        return $this->errors === [];
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        $this->run();

        return $this->errors;
    }

    public function first(?string $field = null): string
    {
        $this->run();

        if ($field !== null) {
            return (string) ($this->errors[$field] ?? '');
        }

        foreach ($this->errors as $message) {
            return $message;
        }

        return '';
    }

    public function hasError(string $field): bool
    {
        $this->run();

        return isset($this->errors[$field]);
    }

    /**
     * The cleaned values of every field that had a rule and passed it.
     *
     * @return array<string, mixed>
     */
    public function validated(): array
    {
        $this->run();

        return $this->validated;
    }

    /**
     * One cleaned value.
     *
     * @param  mixed $default
     * @return mixed
     */
    public function value(string $field, $default = null)
    {
        $this->run();

        return array_key_exists($field, $this->validated) ? $this->validated[$field] : $default;
    }

    /** Add an error discovered outside the rule engine. */
    public function addError(string $field, string $message): self
    {
        $this->run();

        if (!isset($this->errors[$field])) {
            $this->errors[$field] = $message;
            unset($this->validated[$field]);
        }

        return $this;
    }

    // -------------------------------------------------------------------------
    // Engine
    // -------------------------------------------------------------------------

    private function run(): void
    {
        if ($this->ran) {
            return;
        }

        $this->ran = true;

        foreach ($this->rules as $field => $ruleSet) {
            $rules = is_array($ruleSet) ? $ruleSet : explode('|', $ruleSet);
            $value = $this->raw($field);

            $isNullable = in_array('nullable', $rules, true);
            $isRequired = false;

            foreach ($rules as $rule) {
                if ($rule === 'required' || strpos($rule, 'required_') === 0) {
                    $isRequired = true;
                    break;
                }
            }

            // "required" first, so an empty value reports the right message.
            if ($isRequired && $this->isEmpty($value)) {
                $this->fail($field, 'required', $this->message($field, 'required', 'is required.'));
                continue;
            }

            // Nothing supplied and nothing demanded: skip the rest.
            if ($this->isEmpty($value)) {
                if ($isNullable || !$isRequired) {
                    $this->validated[$field] = $this->emptyValueFor($rules);
                }
                continue;
            }

            $clean  = $value;
            $failed = false;

            foreach ($rules as $rule) {
                if ($rule === '' || $rule === 'required' || $rule === 'nullable') {
                    continue;
                }

                [$name, $params] = $this->parseRule($rule);

                $result = $this->apply($name, $field, $clean, $params);

                if ($result === null) {
                    continue; // unknown rule: ignore rather than silently pass
                }

                if ($result['ok'] === false) {
                    $this->fail($field, $name, $result['message']);
                    $failed = true;
                    break;
                }

                if (array_key_exists('value', $result)) {
                    $clean = $result['value'];
                }
            }

            if (!$failed) {
                $this->validated[$field] = $clean;
            }
        }
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    private function parseRule(string $rule): array
    {
        $colon = strpos($rule, ':');

        if ($colon === false) {
            return [$rule, []];
        }

        $name      = substr($rule, 0, $colon);
        $argString = substr($rule, $colon + 1);

        // A regex argument is taken whole; everything else splits on commas.
        if ($name === 'regex') {
            return [$name, [$argString]];
        }

        return [$name, array_map('trim', explode(',', $argString))];
    }

    /**
     * @param  mixed        $value
     * @param  list<string> $params
     * @return array{ok: bool, message?: string, value?: mixed}|null
     */
    private function apply(string $rule, string $field, $value, array $params): ?array
    {
        switch ($rule) {
            case 'string':
                if (is_array($value)) {
                    return $this->no($field, $rule, 'must be text.');
                }

                return ['ok' => true, 'value' => Str::tidy((string) $value)];

            case 'text':
                // Like string, but preserves line breaks.
                if (is_array($value)) {
                    return $this->no($field, $rule, 'must be text.');
                }

                return ['ok' => true, 'value' => Str::tidyBlock((string) $value)];

            case 'int':
            case 'integer':
                if (!is_numeric($value) || (string) (int) $value !== trim((string) $value)) {
                    // Accept "5" and 5, reject "5.5" and "five".
                    if (!is_numeric($value) || floor((float) $value) !== (float) $value) {
                        return $this->no($field, $rule, 'must be a whole number.');
                    }
                }

                return ['ok' => true, 'value' => (int) $value];

            case 'numeric':
            case 'decimal':
                $clean = is_string($value) ? preg_replace('/[^0-9.\-]/', '', $value) : $value;

                if ($clean === null || $clean === '' || !is_numeric($clean)) {
                    return $this->no($field, $rule, 'must be a number.');
                }

                return ['ok' => true, 'value' => (float) $clean];

            case 'bool':
            case 'boolean':
                $truthy = ['1', 'true', 'yes', 'on', 1, true];
                $falsy  = ['0', 'false', 'no', 'off', 0, false, ''];

                if (in_array($value, $truthy, true) || (is_string($value) && in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true))) {
                    return ['ok' => true, 'value' => 1];
                }

                if (in_array($value, $falsy, true) || (is_string($value) && in_array(strtolower($value), ['0', 'false', 'no', 'off'], true))) {
                    return ['ok' => true, 'value' => 0];
                }

                return $this->no($field, $rule, 'must be yes or no.');

            case 'email':
                $email = trim((string) $value);

                if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                    return $this->no($field, $rule, 'must be a valid email address.');
                }

                return ['ok' => true, 'value' => $email];

            case 'url':
                $url = trim((string) $value);

                if (filter_var($url, FILTER_VALIDATE_URL) === false || !preg_match('#^https?://#i', $url)) {
                    return $this->no($field, $rule, 'must be a valid web address starting with http:// or https://.');
                }

                return ['ok' => true, 'value' => $url];

            case 'date':
                $date = Dates::toDate((string) $value);

                if ($date === null) {
                    return $this->no($field, $rule, 'must be a valid date.');
                }

                return ['ok' => true, 'value' => $date];

            case 'datetime':
                $utc = Dates::toUtc((string) $value);

                if ($utc === null) {
                    return $this->no($field, $rule, 'must be a valid date and time.');
                }

                return ['ok' => true, 'value' => $utc];

            case 'min':
                $limit = (float) ($params[0] ?? 0);

                if (is_array($value)) {
                    return count($value) >= $limit
                        ? ['ok' => true]
                        : $this->no($field, $rule, 'must have at least ' . (int) $limit . ' items.');
                }

                if (is_int($value) || is_float($value)) {
                    return $value >= $limit
                        ? ['ok' => true]
                        : $this->no($field, $rule, 'must be ' . $this->formatNumber($limit) . ' or more.');
                }

                return mb_strlen((string) $value, 'UTF-8') >= $limit
                    ? ['ok' => true]
                    : $this->no($field, $rule, 'must be at least ' . (int) $limit . ' characters.');

            case 'max':
                $limit = (float) ($params[0] ?? 0);

                if (is_array($value)) {
                    return count($value) <= $limit
                        ? ['ok' => true]
                        : $this->no($field, $rule, 'must have no more than ' . (int) $limit . ' items.');
                }

                if (is_int($value) || is_float($value)) {
                    return $value <= $limit
                        ? ['ok' => true]
                        : $this->no($field, $rule, 'must be ' . $this->formatNumber($limit) . ' or less.');
                }

                return mb_strlen((string) $value, 'UTF-8') <= $limit
                    ? ['ok' => true]
                    : $this->no($field, $rule, 'must be ' . (int) $limit . ' characters or fewer.');

            case 'between':
                $low  = (float) ($params[0] ?? 0);
                $high = (float) ($params[1] ?? 0);

                $measure = is_numeric($value) && !is_string($value)
                    ? (float) $value
                    : (is_array($value) ? count($value) : mb_strlen((string) $value, 'UTF-8'));

                return ($measure >= $low && $measure <= $high)
                    ? ['ok' => true]
                    : $this->no($field, $rule, 'must be between ' . $this->formatNumber($low) . ' and ' . $this->formatNumber($high) . '.');

            case 'in':
                return in_array((string) $value, $params, true)
                    ? ['ok' => true, 'value' => (string) $value]
                    : $this->no($field, $rule, 'is not one of the allowed choices.');

            case 'not_in':
                return !in_array((string) $value, $params, true)
                    ? ['ok' => true]
                    : $this->no($field, $rule, 'is not allowed.');

            case 'regex':
                $pattern = (string) ($params[0] ?? '');

                if ($pattern === '' || @preg_match($pattern, '') === false) {
                    return ['ok' => true];
                }

                return preg_match($pattern, (string) $value) === 1
                    ? ['ok' => true]
                    : $this->no($field, $rule, 'is not in the expected format.');

            case 'alpha_dash':
                return preg_match('/^[A-Za-z0-9_\-]+$/', (string) $value) === 1
                    ? ['ok' => true]
                    : $this->no($field, $rule, 'may only contain letters, numbers, dashes and underscores.');

            case 'username':
                return preg_match('/^[A-Za-z0-9._\-]{3,64}$/', (string) $value) === 1
                    ? ['ok' => true]
                    : $this->no($field, $rule, 'must be 3 to 64 characters, using letters, numbers, dots, dashes or underscores.');

            case 'confirmed':
                $other = $this->raw($field . '_confirmation');

                return (string) $value === (string) $other
                    ? ['ok' => true]
                    : $this->no($field, $rule, 'does not match the confirmation.');

            case 'same':
                $other = $this->raw((string) ($params[0] ?? ''));

                return (string) $value === (string) $other
                    ? ['ok' => true]
                    : $this->no($field, $rule, 'must match ' . $this->label((string) ($params[0] ?? '')) . '.');

            case 'different':
                $other = $this->raw((string) ($params[0] ?? ''));

                return (string) $value !== (string) $other
                    ? ['ok' => true]
                    : $this->no($field, $rule, 'must be different from ' . $this->label((string) ($params[0] ?? '')) . '.');

            case 'array':
                return is_array($value)
                    ? ['ok' => true]
                    : $this->no($field, $rule, 'must be a list.');

            case 'password':
                $error = Auth::validatePassword((string) $value, $this->data);

                return $error === ''
                    ? ['ok' => true]
                    : ['ok' => false, 'message' => $error];

            case 'unique':
                return $this->checkUnique($field, $value, $params);

            case 'exists':
                return $this->checkExists($field, $value, $params);

            case 'after':
                return $this->compareDates($field, $rule, $value, $params, '>');

            case 'after_or_equal':
                return $this->compareDates($field, $rule, $value, $params, '>=');

            case 'before':
                return $this->compareDates($field, $rule, $value, $params, '<');

            case 'before_or_equal':
                return $this->compareDates($field, $rule, $value, $params, '<=');

            case 'not_future':
                $utc = Dates::toUtc((string) $value) ?? Dates::toDate((string) $value);

                if ($utc === null) {
                    return ['ok' => true];
                }

                // Allow a few minutes of clock skew between the browser and server.
                return $utc <= gmdate(Dates::DB_FORMAT, time() + 900)
                    ? ['ok' => true]
                    : $this->no($field, $rule, 'cannot be in the future.');

            case 'hex_color':
                return preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $value) === 1
                    ? ['ok' => true]
                    : $this->no($field, $rule, 'must be a colour like #4f46e5.');

            case 'timezone':
                return in_array((string) $value, timezone_identifiers_list(), true)
                    ? ['ok' => true]
                    : $this->no($field, $rule, 'is not a recognised time zone.');

            default:
                return null;
        }
    }

    /**
     * unique:table,column[,ignoreId[,idColumn]]
     *
     * @param  mixed        $value
     * @param  list<string> $params
     * @return array{ok: bool, message?: string}
     */
    private function checkUnique(string $field, $value, array $params): array
    {
        $table    = (string) ($params[0] ?? '');
        $column   = (string) ($params[1] ?? $field);
        $ignoreId = isset($params[2]) && $params[2] !== '' ? (int) $params[2] : 0;
        $idColumn = (string) ($params[3] ?? 'id');

        if ($table === '') {
            return ['ok' => true];
        }

        try {
            $db  = db();
            $sql = 'SELECT COUNT(*) FROM ' . $db->quoteIdentifier($db->table($table))
                 . ' WHERE ' . $db->quoteIdentifier($column) . ' = ?';

            $bindings = [$value];

            if ($ignoreId > 0) {
                $sql .= ' AND ' . $db->quoteIdentifier($idColumn) . ' <> ?';
                $bindings[] = $ignoreId;
            }

            // Soft-deleted rows should not block a new record from reusing a tag.
            if (in_array('deleted_at', $db->columns($table), true)) {
                $sql .= ' AND deleted_at IS NULL';
            }

            $count = $db->count($sql, $bindings);
        } catch (Throwable $e) {
            // If the check itself fails, do not block the save — the database
            // has its own unique index as the real guarantee.
            return ['ok' => true];
        }

        return $count === 0
            ? ['ok' => true]
            : $this->no($field, 'unique', 'is already in use.');
    }

    /**
     * exists:table,column
     *
     * @param  mixed        $value
     * @param  list<string> $params
     * @return array{ok: bool, message?: string}
     */
    private function checkExists(string $field, $value, array $params): array
    {
        $table  = (string) ($params[0] ?? '');
        $column = (string) ($params[1] ?? 'id');

        if ($table === '') {
            return ['ok' => true];
        }

        try {
            $db    = db();
            $sql   = 'SELECT COUNT(*) FROM ' . $db->quoteIdentifier($db->table($table))
                   . ' WHERE ' . $db->quoteIdentifier($column) . ' = ?';

            if (in_array('deleted_at', $db->columns($table), true)) {
                $sql .= ' AND deleted_at IS NULL';
            }

            $count = $db->count($sql, [$value]);
        } catch (Throwable $e) {
            return ['ok' => true];
        }

        return $count > 0
            ? ['ok' => true]
            : $this->no($field, 'exists', 'refers to something that no longer exists.');
    }

    /**
     * @param  mixed        $value
     * @param  list<string> $params
     * @return array{ok: bool, message?: string}
     */
    private function compareDates(string $field, string $rule, $value, array $params, string $operator): array
    {
        $target = (string) ($params[0] ?? '');

        // The parameter is either another field name or a literal date.
        $otherRaw = $this->has($target) ? (string) $this->raw($target) : $target;

        $left  = Dates::toUtc((string) $value) ?? Dates::toDate((string) $value);
        $right = Dates::toUtc($otherRaw) ?? Dates::toDate($otherRaw);

        if ($left === null || $right === null) {
            return ['ok' => true];
        }

        // Compare at the same granularity when one side is date-only.
        if (strlen($left) === 10 || strlen($right) === 10) {
            $left  = substr($left, 0, 10);
            $right = substr($right, 0, 10);
        }

        switch ($operator) {
            case '>':
                $ok = $left > $right;
                $why = 'must be after';
                break;
            case '>=':
                $ok = $left >= $right;
                $why = 'must be on or after';
                break;
            case '<':
                $ok = $left < $right;
                $why = 'must be before';
                break;
            default:
                $ok = $left <= $right;
                $why = 'must be on or before';
                break;
        }

        $describe = $this->has($target) ? $this->label($target) : $target;

        return $ok ? ['ok' => true] : $this->no($field, $rule, $why . ' ' . $describe . '.');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @return mixed
     */
    private function raw(string $field)
    {
        if (array_key_exists($field, $this->data)) {
            return $this->data[$field];
        }

        // Support dotted paths into nested arrays: "parts.0.quantity".
        if (strpos($field, '.') !== false) {
            $value = $this->data;

            foreach (explode('.', $field) as $segment) {
                if (!is_array($value) || !array_key_exists($segment, $value)) {
                    return null;
                }

                $value = $value[$segment];
            }

            return $value;
        }

        return null;
    }

    private function has(string $field): bool
    {
        return array_key_exists($field, $this->data);
    }

    /**
     * @param mixed $value
     */
    private function isEmpty($value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_array($value)) {
            return $value === [];
        }

        return false;
    }

    /**
     * What an absent optional field becomes in validated().
     *
     * @param  list<string> $rules
     * @return mixed
     */
    private function emptyValueFor(array $rules)
    {
        foreach ($rules as $rule) {
            [$name] = $this->parseRule($rule);

            if (in_array($name, ['int', 'integer', 'numeric', 'decimal', 'date', 'datetime'], true)) {
                return null;
            }

            if (in_array($name, ['bool', 'boolean'], true)) {
                return 0;
            }

            if ($name === 'array') {
                return [];
            }
        }

        return '';
    }

    private function label(string $field): string
    {
        if (isset($this->labels[$field])) {
            return $this->labels[$field];
        }

        return Str::humanize($field);
    }

    private function message(string $field, string $rule, string $fallback): string
    {
        if (isset($this->messages[$field . '.' . $rule])) {
            return $this->messages[$field . '.' . $rule];
        }

        if (isset($this->messages[$field])) {
            return $this->messages[$field];
        }

        return $this->label($field) . ' ' . $fallback;
    }

    /**
     * @return array{ok: false, message: string}
     */
    private function no(string $field, string $rule, string $fallback): array
    {
        return ['ok' => false, 'message' => $this->message($field, $rule, $fallback)];
    }

    private function fail(string $field, string $rule, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = $message;
        }
    }

    private function formatNumber(float $value): string
    {
        return floor($value) === $value
            ? (string) (int) $value
            : rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
