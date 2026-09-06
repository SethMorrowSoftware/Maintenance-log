<?php

declare(strict_types=1);

namespace App;

/**
 * Extra fields on machines, defined by an administrator under Settings → Fields.
 *
 * A go-kart site wants "Seat size" and "Restrictor plate"; a site that looks
 * after appliances wants "Gas or electric" and "Filter part number". Rather
 * than guess, the administrator adds the fields, and every machine form,
 * page, list and export picks them up. Values live in one JSON column on the
 * machine, so adding a field never touches the schema.
 *
 * A definition is: key (stable, derived from the first label), label, type,
 * options (for "pick from a list"), list (show as a column on the machine
 * list) and hint.
 */
final class CustomFields
{
    public const SETTING_KEY = 'asset_custom_fields';

    /** @var array<string, string> type => label shown to the administrator */
    public const TYPES = [
        'text'   => 'Text',
        'number' => 'Number',
        'date'   => 'Date',
        'yesno'  => 'Yes or no',
        'choice' => 'Pick from a list',
    ];

    public const MAX_FIELDS = 30;
    public const MAX_TEXT   = 500;

    /** @var list<array<string, mixed>>|null */
    private static ?array $cache = null;

    private function __construct()
    {
    }

    // -------------------------------------------------------------------------
    // Definitions
    // -------------------------------------------------------------------------

    /**
     * Every field, in the order the administrator put them.
     *
     * @return list<array{key: string, label: string, type: string, options: list<string>, list: bool, hint: string}>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $out = [];

        try {
            $raw = (string) Settings::get(self::SETTING_KEY, '');
        } catch (\Throwable $e) {
            $raw = '';
        }

        $decoded = $raw === '' ? [] : json_decode($raw, true);

        if (is_array($decoded)) {
            foreach ($decoded as $row) {
                if (is_array($row) && trim((string) ($row['label'] ?? '')) !== '') {
                    $out[] = self::tidy($row, (string) ($row['key'] ?? ''));
                }
            }
        }

        self::$cache = $out;

        return $out;
    }

    public static function any(): bool
    {
        return self::all() !== [];
    }

    /**
     * The fields that also appear as a column on the machine list.
     *
     * @return list<array<string, mixed>>
     */
    public static function inList(): array
    {
        return array_values(array_filter(self::all(), static function (array $field): bool {
            return $field['list'];
        }));
    }

    /**
     * Turn the rows from the Settings → Fields form into definitions, without
     * saving. Rows with a blank name are skipped; problems come back keyed by
     * row so the form can say which line.
     *
     * @param  array<mixed> $rows
     * @return array{fields: list<array<string, mixed>>, errors: array<int, string>}
     */
    public static function build(array $rows): array
    {
        $fields = [];
        $errors = [];
        $keys   = [];
        $labels = [];

        foreach ($rows as $index => $row) {
            if (!is_array($row) || trim((string) ($row['label'] ?? '')) === '') {
                continue;
            }

            // Two fields with the same name would be two identical boxes.
            $labelKey = mb_strtolower(trim((string) $row['label']), 'UTF-8');

            if (isset($labels[$labelKey])) {
                $errors[(int) $index] = 'There is already a field called "' . trim((string) $row['label']) . '".';
                continue;
            }

            $labels[$labelKey] = true;

            if (count($fields) >= self::MAX_FIELDS) {
                $errors[(int) $index] = 'That is more than ' . self::MAX_FIELDS . ' fields, which is the most the form can hold.';
                break;
            }

            // The key is fixed the first time a field is saved, so renaming
            // "Seat" to "Seat size" later does not orphan what was typed in.
            $key = self::slug((string) ($row['key'] ?? ''));

            if ($key === '') {
                $key = self::slug((string) $row['label']);
            }

            if ($key === '') {
                $key = 'field';
            }

            $base = $key;
            $n    = 2;

            while (isset($keys[$key])) {
                $key = $base . '_' . $n++;
            }

            $keys[$key] = true;
            $field      = self::tidy($row, $key);

            if ($field['type'] === 'choice' && $field['options'] === []) {
                $errors[(int) $index] = '"' . $field['label'] . '" needs at least one choice to pick from.';
            }

            $fields[] = $field;
        }

        return ['fields' => $fields, 'errors' => $errors];
    }

    /**
     * Save the rows from the Settings → Fields form.
     *
     * @param  array<mixed> $rows
     * @return array<int, string> problems keyed by row; empty means saved
     */
    public static function save(array $rows): array
    {
        $built = self::build($rows);

        if ($built['errors'] !== []) {
            return $built['errors'];
        }

        Settings::set(
            self::SETTING_KEY,
            $built['fields'] === [] ? '' : (string) json_encode($built['fields'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        self::$cache = null;

        return [];
    }

    /**
     * One definition, with every part present and within bounds.
     *
     * @param  array<string, mixed> $row
     * @return array{key: string, label: string, type: string, options: list<string>, list: bool, hint: string}
     */
    private static function tidy(array $row, string $key): array
    {
        $type = (string) ($row['type'] ?? 'text');

        if (!isset(self::TYPES[$type])) {
            $type = 'text';
        }

        $options = [];
        $rawOpts = $row['options'] ?? [];

        // From the form it is one comma-separated line; from storage, a list.
        $list = is_array($rawOpts) ? $rawOpts : (preg_split('/[\n,]/', (string) $rawOpts) ?: []);

        foreach ($list as $option) {
            $option = trim((string) $option);

            if ($option !== '' && !in_array($option, $options, true)) {
                $options[] = mb_substr($option, 0, 120);
            }
        }

        return [
            'key'     => $key !== '' ? $key : self::slug((string) $row['label']),
            'label'   => mb_substr(trim((string) ($row['label'] ?? '')), 0, 80),
            'type'    => $type,
            'options' => $type === 'choice' ? $options : [],
            'list'    => !empty($row['list']),
            'hint'    => mb_substr(trim((string) ($row['hint'] ?? '')), 0, 200),
        ];
    }

    /** "Seat size (inches)" → "seat_size_inches" */
    private static function slug(string $text): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', $text), '_'));

        return mb_substr($slug, 0, 40);
    }

    // -------------------------------------------------------------------------
    // Values on a machine
    // -------------------------------------------------------------------------

    /**
     * The stored values, key => value as text.
     *
     * @param  mixed $json the custom_data column
     * @return array<string, string>
     */
    public static function decode($json): array
    {
        if (!is_string($json) || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        if (!is_array($decoded)) {
            return [];
        }

        $out = [];

        foreach ($decoded as $key => $value) {
            if (is_scalar($value) && (string) $value !== '') {
                $out[(string) $key] = (string) $value;
            }
        }

        return $out;
    }

    /**
     * Values ready for the column. Nothing filled in is NULL, not "{}".
     *
     * @param array<string, string> $values
     */
    public static function encode(array $values): ?string
    {
        $values = array_filter($values, static function ($value): bool {
            return $value !== '' && $value !== null;
        });

        if ($values === []) {
            return null;
        }

        return (string) json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** The form field name for a key. Flat, so old-input and errors just work. */
    public static function inputName(string $key): string
    {
        return 'cf_' . $key;
    }

    /**
     * Read the extra fields off a submitted machine form, checking each
     * against its definition. Values for fields that no longer exist are
     * kept as they were, so removing a field from Settings loses nothing.
     *
     * @param  array<string, mixed>  $input    usually $_POST
     * @param  array<string, string> $existing what the machine already had
     * @param  array<string, string> $errors   filled with field name => message
     * @return array<string, string>
     */
    public static function fromInput(array $input, array $existing, array &$errors): array
    {
        $values = $existing;

        foreach (self::all() as $field) {
            $name = self::inputName($field['key']);
            $raw  = trim((string) ($input[$name] ?? ''));

            if ($raw === '') {
                unset($values[$field['key']]);
                continue;
            }

            $problem = self::check($field, $raw);

            if ($problem !== null) {
                $errors[$name] = $problem;
                continue;
            }

            $values[$field['key']] = self::normalise($field, $raw);
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $field
     */
    private static function check(array $field, string $raw): ?string
    {
        $label = (string) $field['label'];

        switch ((string) $field['type']) {
            case 'number':
                return is_numeric($raw) ? null : 'Type a number for ' . $label . '.';

            case 'date':
                $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw);

                return $date !== false && $date->format('Y-m-d') === $raw
                    ? null
                    : 'Pick a date for ' . $label . '.';

            case 'yesno':
                return in_array($raw, ['1', '0'], true) ? null : 'Choose yes or no for ' . $label . '.';

            case 'choice':
                return in_array($raw, $field['options'], true)
                    ? null
                    : 'Pick one of the choices for ' . $label . '.';

            default:
                return mb_strlen($raw) <= self::MAX_TEXT
                    ? null
                    : $label . ' is too long: ' . self::MAX_TEXT . ' characters at most.';
        }
    }

    /**
     * @param array<string, mixed> $field
     */
    private static function normalise(array $field, string $raw): string
    {
        if ((string) $field['type'] === 'number') {
            // "012.50" → "12.5", so the list sorts and the export adds up.
            return (string) (float) $raw;
        }

        return $raw;
    }

    /**
     * A stored value as people should read it.
     *
     * @param array<string, mixed> $field
     */
    public static function display(array $field, string $value): string
    {
        if ($value === '') {
            return '';
        }

        switch ((string) $field['type']) {
            case 'yesno':
                return $value === '1' ? 'Yes' : 'No';

            case 'date':
                return Dates::dateOnly($value);

            default:
                return $value;
        }
    }

    /**
     * The value of one field on a machine row, ready to read.
     *
     * @param array<string, mixed> $field
     * @param array<string, mixed> $asset a row with a custom_data column
     */
    public static function valueOn(array $field, array $asset): string
    {
        $values = self::decode($asset['custom_data'] ?? null);

        return self::display($field, $values[(string) $field['key']] ?? '');
    }
}
