<?php

declare(strict_types=1);

/**
 * Everything, in one download.
 *
 * One spreadsheet file per table, zipped, for backing up or for moving the
 * records somewhere else one day. Passwords, tokens and other secrets are
 * left out. Photos are not included: they live in storage/uploads, which a
 * normal cPanel backup already covers.
 *
 * If the server has no ZIP support the same data goes out as a single JSON
 * file instead, so the export never silently does nothing.
 */

require __DIR__ . '/app/bootstrap.php';

use App\Acl;
use App\Auth;
use App\Csv;
use App\Dates;
use App\Response;

Auth::requireLogin();
Acl::requirePermission('settings.manage');

@set_time_limit(300);

// Table => columns to leave out. Sessions, resets and login attempts are not
// records anybody needs back.
$tables = [
    'users'                 => ['password_hash', 'remember_token', 'reset_token', 'api_token'],
    'locations'             => [],
    'asset_categories'      => [],
    'assets'                => [],
    'meter_readings'        => [],
    'parts'                 => [],
    'part_transactions'     => [],
    'checklists'            => [],
    'checklist_items'       => [],
    'maintenance_schedules' => [],
    'work_orders'           => [],
    'work_order_comments'   => [],
    'inspections'           => [],
    'inspection_items'      => [],
    'maintenance_logs'      => [],
    'maintenance_log_parts' => [],
    'attachments'           => [],
    'saved_reports'         => [],
    'settings'              => [],
    'audit_log'             => [],
];

$db = db();

/**
 * Rows of one table, one at a time, with secret columns removed.
 *
 * @param  list<string> $drop
 * @return Generator<int, array<string, mixed>>
 */
$rows = static function (string $table, array $drop) use ($db): Generator {
    $statement = $db->run('SELECT * FROM {' . $table . '} ORDER BY id ASC');

    while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
        foreach ($drop as $column) {
            unset($row[$column]);
        }

        // Settings hold a few secrets of their own.
        if ($table === 'settings' && preg_match('/token|secret|smtp_pass|api_key|app_key/i', (string) ($row['setting_key'] ?? '')) === 1) {
            $row['setting_value'] = '';
        }

        yield $row;
    }
};

$stamp    = Dates::localNow()->format('Y-m-d');
$exported = 0;

audit('export', 'system', null, 'Downloaded a full data export');

// -----------------------------------------------------------------------------
// ZIP of CSVs
// -----------------------------------------------------------------------------

if (class_exists('ZipArchive')) {
    $zipPath = tempnam(sys_get_temp_dir(), 'ridelog-export-');
    $zip     = new ZipArchive();

    if ($zipPath === false || $zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
        Response::abortPage(500, 'The export could not be written to the temporary folder.');
    }

    $temporary = [];

    foreach ($tables as $table => $drop) {
        $file = tempnam(sys_get_temp_dir(), 'ridelog-csv-');

        if ($file === false) {
            continue;
        }

        $temporary[] = $file;
        $handle      = fopen($file, 'wb');

        if ($handle === false) {
            continue;
        }

        $headers = null;

        foreach ($rows($table, $drop) as $row) {
            if ($headers === null) {
                $headers = array_keys($row);
                fwrite($handle, Csv::BOM);
                fwrite($handle, Csv::line($headers));
            }

            fwrite($handle, Csv::line(array_values($row)));
        }

        if ($headers === null) {
            // An empty table still gets a file with its column names.
            $columns = $db->all('SHOW COLUMNS FROM {' . $table . '}');
            $names   = [];

            foreach ($columns as $column) {
                $name = (string) ($column['Field'] ?? '');

                if ($name !== '' && !in_array($name, $drop, true)) {
                    $names[] = $name;
                }
            }

            fwrite($handle, Csv::BOM);
            fwrite($handle, Csv::line($names));
        }

        fclose($handle);
        $zip->addFile($file, $table . '.csv');
        $exported++;
    }

    $zip->addFromString(
        'README.txt',
        "RideLog full export — " . Dates::datetime(Dates::nowUtc()) . "\n\n"
        . "One CSV file per table. Open them in Excel, Numbers or Google Sheets.\n"
        . "Dates and times are in UTC. Money columns are in the site's currency.\n"
        . "Passwords and secret tokens are not included.\n"
        . "Photos and other uploaded files are not included: they are in storage/uploads on the server.\n"
    );

    $zip->close();

    $filename = 'ridelog-full-export-' . $stamp . '.zip';

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . (string) filesize($zipPath));
    header('X-Content-Type-Options: nosniff');
    Response::noStore();

    readfile($zipPath);

    @unlink($zipPath);

    foreach ($temporary as $file) {
        @unlink($file);
    }

    exit;
}

// -----------------------------------------------------------------------------
// No ZIP support: one JSON file
// -----------------------------------------------------------------------------

$filename = 'ridelog-full-export-' . $stamp . '.json';

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');
Response::noStore();

echo '{"exported_at":' . json_encode(Dates::nowUtc()) . ',"note":"Dates are UTC. Passwords, tokens and photos are not included.","tables":{';

$first = true;

foreach ($tables as $table => $drop) {
    echo ($first ? '' : ',') . json_encode($table) . ':[';
    $first    = false;
    $firstRow = true;

    foreach ($rows($table, $drop) as $row) {
        echo ($firstRow ? '' : ',') . json_encode($row, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        $firstRow = false;
    }

    echo ']';
    flush();
}

echo '}}';
exit;
