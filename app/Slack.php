<?php

declare(strict_types=1);

namespace App;

use App\Models\Asset;
use Throwable;

/**
 * Slack alerts.
 *
 * A Slack app with a bot token (xoxb-…) and the chat:write scope posts to a
 * channel the bot has been invited to. Which events post, how urgent they
 * have to be, and which channel each one goes to are all settings, so the
 * maintenance channel can stay quiet or busy to taste.
 *
 * Nothing here ever throws or blocks a save: a failed post is written to the
 * error log and forgotten. The one place a failure is shown to a person is
 * the "send a test" button on the settings page.
 */
final class Slack
{
    private const API = 'https://slack.com/api/';

    /** Where the API lives. config.php may point it at a stand-in for testing. */
    private static function api(): string
    {
        $override = trim((string) Config::get('app.slack_api', ''));

        return $override !== '' ? rtrim($override, '/') . '/' : self::API;
    }

    private const PRIORITY_RANK    = ['low' => 0, 'normal' => 1, 'high' => 2, 'urgent' => 3];
    private const CRITICALITY_RANK = ['low' => 0, 'medium' => 1, 'high' => 2, 'critical' => 3];

    private function __construct()
    {
    }

    // -------------------------------------------------------------------------
    // Settings
    // -------------------------------------------------------------------------

    public static function enabled(): bool
    {
        return Settings::bool('slack_enabled', false) && self::token() !== '';
    }

    private static function token(): string
    {
        return trim((string) Settings::get('slack_bot_token', ''));
    }

    /** The channel for one kind of alert, falling back to the main one. */
    public static function channelFor(string $event): string
    {
        $own = trim((string) Settings::get('slack_' . $event . '_channel', ''));

        return $own !== '' ? $own : trim((string) Settings::get('slack_channel', ''));
    }

    /** "@here" and friends in the form Slack understands, or nothing. */
    private static function mention(): string
    {
        $raw = trim((string) Settings::get('slack_mention', ''));

        if ($raw === '') {
            return '';
        }

        $bare = ltrim($raw, '@');

        if (in_array(strtolower($bare), ['here', 'channel', 'everyone'], true)) {
            return '<!' . strtolower($bare) . '>';
        }

        if (preg_match('/^[UW][A-Z0-9]{6,}$/', $bare) === 1) {
            return '<@' . $bare . '>';
        }

        if (preg_match('/^S[A-Z0-9]{6,}$/', $bare) === 1) {
            return '<!subteam^' . $bare . '>';
        }

        return $raw;
    }

    /** Is this machine important enough to post about? */
    private static function importantEnough(?array $asset): bool
    {
        $floor = (string) Settings::get('slack_min_criticality', 'any');

        if ($floor === 'any' || $floor === '' || $asset === null) {
            return true;
        }

        $have = self::CRITICALITY_RANK[(string) ($asset['criticality'] ?? 'medium')] ?? 1;
        $need = self::CRITICALITY_RANK[$floor] ?? 0;

        return $have >= $need;
    }

    // -------------------------------------------------------------------------
    // Talking to Slack
    // -------------------------------------------------------------------------

    /**
     * Post one message. Returns ok plus a plain-English error when it is not.
     *
     * @return array{ok: bool, error: string}
     */
    public static function post(string $channel, string $text, string $event = ''): array
    {
        $channel = trim($channel);

        if ($channel === '') {
            return ['ok' => false, 'error' => 'No channel is set. Fill in the main channel under Settings → Slack.'];
        }

        $result = self::call('chat.postMessage', [
            'channel'      => $channel,
            'text'         => $text,
            'unfurl_links' => false,
            'unfurl_media' => false,
        ]);

        if (!$result['ok']) {
            log_error('Slack post failed' . ($event !== '' ? ' (' . $event . ')' : '') . ': ' . $result['error']);
        }

        return $result;
    }

    /** Post an alert of one kind, if that kind is switched on. Never throws. */
    private static function send(string $event, string $text): void
    {
        try {
            if (!self::enabled()) {
                return;
            }

            self::post(self::channelFor($event), $text, $event);
        } catch (Throwable $e) {
            log_error('Slack alert failed (' . $event . '): ' . $e->getMessage());
        }
    }

    /**
     * Send yourself a message to prove the token and channel work.
     *
     * @return array{ok: bool, error: string}
     */
    public static function test(): array
    {
        if (self::token() === '') {
            return ['ok' => false, 'error' => 'Paste the bot token first, save, then test.'];
        }

        $auth = self::call('auth.test', []);

        if (!$auth['ok']) {
            return $auth;
        }

        $channel = trim((string) Settings::get('slack_channel', ''));
        $me      = user();
        $who     = $me === null ? 'somebody' : trim((string) $me['first_name'] . ' ' . (string) $me['last_name']);

        $result = self::post(
            $channel,
            ':white_check_mark: *' . Settings::siteName() . ' is connected to Slack.* '
            . 'Test sent by ' . $who . ' · <' . absolute_url('settings.php', ['tab' => 'slack']) . '|Slack settings>',
            'test'
        );

        if ($result['ok']) {
            $result['error'] = (string) ($auth['team'] ?? '');
        }

        return $result;
    }

    /**
     * One Slack Web API call.
     *
     * @param  array<string, mixed> $payload
     * @return array{ok: bool, error: string, team?: string}
     */
    private static function call(string $method, array $payload): array
    {
        $token = self::token();

        if ($token === '') {
            return ['ok' => false, 'error' => 'No bot token is set.'];
        }

        $body    = (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = [
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Bearer ' . $token,
        ];

        $raw = null;

        if (function_exists('curl_init')) {
            $curl = curl_init(self::api() . $method);

            if ($curl !== false) {
                curl_setopt_array($curl, [
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => $body,
                    CURLOPT_HTTPHEADER     => $headers,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_TIMEOUT        => 8,
                    CURLOPT_SSL_VERIFYPEER => true,
                ]);

                $raw = curl_exec($curl);

                if ($raw === false) {
                    $error = curl_error($curl);
                    curl_close($curl);

                    return ['ok' => false, 'error' => 'Could not reach Slack: ' . ($error !== '' ? $error : 'connection failed') . '.'];
                }

                curl_close($curl);
            }
        }

        if ($raw === null) {
            $context = stream_context_create([
                'http' => [
                    'method'        => 'POST',
                    'header'        => implode("\r\n", $headers),
                    'content'       => $body,
                    'timeout'       => 8,
                    'ignore_errors' => true,
                ],
                'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
            ]);

            $raw = @file_get_contents(self::api() . $method, false, $context);

            if ($raw === false) {
                return ['ok' => false, 'error' => 'Could not reach Slack. The server may not allow outgoing connections; ask your host.'];
            }
        }

        $decoded = json_decode((string) $raw, true);

        if (!is_array($decoded)) {
            return ['ok' => false, 'error' => 'Slack sent back something that was not JSON.'];
        }

        if (!empty($decoded['ok'])) {
            return ['ok' => true, 'error' => '', 'team' => (string) ($decoded['team'] ?? '')];
        }

        return ['ok' => false, 'error' => self::explain((string) ($decoded['error'] ?? 'unknown_error'))];
    }

    /** Slack's error codes, in words a person can act on. */
    private static function explain(string $code): string
    {
        $map = [
            'invalid_auth'      => 'Slack did not accept the token. Copy the Bot User OAuth Token (it starts with xoxb-) again.',
            'not_authed'        => 'No token reached Slack. Paste the Bot User OAuth Token and save.',
            'account_inactive'  => 'That token belongs to a deactivated Slack app or workspace.',
            'token_revoked'     => 'That token has been revoked in Slack. Reinstall the app and paste the new token.',
            'missing_scope'     => 'The Slack app needs the chat:write permission. Add the scope, reinstall the app, and paste the new token.',
            'not_in_channel'    => 'The bot is not in that channel. In Slack, open the channel and type /invite @ followed by the app name.',
            'channel_not_found' => 'Slack cannot find that channel. Use the name with the #, or the channel ID, and invite the bot to it.',
            'is_archived'       => 'That channel is archived.',
            'msg_too_long'      => 'The message was too long for Slack.',
            'ratelimited'       => 'Slack asked us to slow down. Try again in a minute.',
            'invalid_arguments' => 'Slack rejected the message. Check the channel name.',
        ];

        return $map[$code] ?? ('Slack said: ' . $code . '.');
    }

    // -------------------------------------------------------------------------
    // Building text
    // -------------------------------------------------------------------------

    private static function who(): string
    {
        $me = user();

        if ($me === null) {
            return '';
        }

        return trim((string) ($me['first_name'] ?? '') . ' ' . (string) ($me['last_name'] ?? ''));
    }

    private static function machine(?array $asset): string
    {
        if ($asset === null) {
            return 'No particular ' . asset_word();
        }

        $tag = (string) ($asset['asset_tag'] ?? '');

        return (string) $asset['name'] . ($tag !== '' ? ' (' . $tag . ')' : '');
    }

    private static function link(string $path, array $query, string $label): string
    {
        return '<' . absolute_url($path, $query) . '|' . $label . '>';
    }

    private static function oneLine(?string $text, int $limit = 200): string
    {
        $text = trim((string) $text);

        if ($text === '') {
            return '';
        }

        return Str::limit((string) preg_replace('/\s+/', ' ', $text), $limit);
    }

    // -------------------------------------------------------------------------
    // Events
    // -------------------------------------------------------------------------

    /** A work order was raised. */
    public static function problemReported(int $workOrderId): void
    {
        try {
            if (!self::enabled()) {
                return;
            }

            $wo = \App\Models\WorkOrder::find($workOrderId);

            if ($wo === null) {
                return;
            }

            $priority = (string) $wo['priority'];
            $safety   = (int) $wo['is_safety_issue'] === 1;
            $floor    = (string) Settings::get('slack_on_problem', 'high');
            $asset    = !empty($wo['asset_id']) ? Asset::find((int) $wo['asset_id']) : null;

            // A job marked "needs follow-up" raises its own work order. When the
            // job itself was just posted, posting the follow-up too says the
            // same thing twice.
            if (Str::startsWith((string) $wo['title'], 'Follow-up: ')
                && (string) Settings::get('slack_on_job', 'followup') !== 'off') {
                return;
            }

            // Likewise a failed inspection raises a work order of its own; the
            // inspection message says what failed, so that one is enough.
            if ((string) ($wo['source'] ?? '') === 'inspection'
                && (string) Settings::get('slack_on_inspection', 'critical') !== 'off') {
                return;
            }

            $wanted = match ($floor) {
                'all'    => true,
                'high'   => (self::PRIORITY_RANK[$priority] ?? 1) >= 2,
                'urgent' => $priority === 'urgent',
                default  => false,
            };

            // A safety issue is never filtered out, whatever the urgency.
            if ($safety && Settings::bool('slack_on_safety', true)) {
                $wanted = true;
            } elseif (!self::importantEnough($asset)) {
                return;
            }

            if (!$wanted) {
                return;
            }

            $emoji = $safety ? ':no_entry:' : match ($priority) {
                'urgent' => ':rotating_light:',
                'high'   => ':warning:',
                'low'    => ':memo:',
                default  => ':wrench:',
            };

            $head = $safety
                ? 'Safety issue reported'
                : Status::label($priority, 'priority') . ' problem reported';

            $lines   = [];
            $lines[] = $emoji . ' *' . $head . '* — ' . self::machine($asset);
            $lines[] = '*' . (string) $wo['title'] . '*';

            $detail = self::oneLine((string) ($wo['description'] ?? ''));

            if ($detail !== '') {
                $lines[] = $detail;
            }

            $meta = [];

            $reporter = trim((string) ($wo['reporter_first'] ?? '') . ' ' . (string) ($wo['reporter_last'] ?? ''));

            if ($reporter !== '') {
                $meta[] = 'Reported by ' . $reporter;
            }

            if ((int) $wo['took_out_of_service'] === 1) {
                $meta[] = 'taken out of service';
            }

            $meta[]  = self::link('workorder-view.php', ['id' => $workOrderId], (string) $wo['wo_number']);
            $lines[] = implode(' · ', $meta);

            $mention = ($safety || $priority === 'urgent') ? self::mention() : '';

            if ($mention !== '') {
                $lines[0] = $mention . ' ' . $lines[0];
            }

            self::send('problem', implode("\n", $lines));
        } catch (Throwable $e) {
            log_error('Slack problem alert failed: ' . $e->getMessage());
        }
    }

    /** A work order was completed or cancelled. */
    public static function problemFixed(int $workOrderId): void
    {
        try {
            if (!self::enabled() || !Settings::bool('slack_on_fixed', true)) {
                return;
            }

            $wo = \App\Models\WorkOrder::find($workOrderId);

            if ($wo === null) {
                return;
            }

            $asset = !empty($wo['asset_id']) ? Asset::find((int) $wo['asset_id']) : null;

            if (!self::importantEnough($asset)) {
                return;
            }

            $cancelled = (string) $wo['status'] === 'cancelled';
            $lines     = [];
            $lines[]   = ($cancelled ? ':heavy_multiplication_x: *Problem closed without work*' : ':white_check_mark: *Problem fixed*')
                       . ' — ' . self::machine($asset);
            $lines[]   = (string) $wo['wo_number'] . ': ' . (string) $wo['title'];

            $resolution = self::oneLine((string) ($wo['resolution'] ?? ''));

            if ($resolution !== '') {
                $lines[] = $resolution;
            }

            $closer = trim((string) ($wo['closer_first'] ?? '') . ' ' . (string) ($wo['closer_last'] ?? ''));
            $meta   = [];

            if ($closer !== '') {
                $meta[] = 'By ' . $closer;
            }

            $meta[]  = self::link('workorder-view.php', ['id' => $workOrderId], 'Open');
            $lines[] = implode(' · ', $meta);

            self::send('problem', implode("\n", $lines));
        } catch (Throwable $e) {
            log_error('Slack fixed alert failed: ' . $e->getMessage());
        }
    }

    /**
     * An inspection had failures.
     *
     * @param array<string, mixed> $inspection a row from Inspection::find()
     */
    public static function inspectionFailed(array $inspection, bool $takenOutOfService): void
    {
        try {
            if (!self::enabled()) {
                return;
            }

            $critical = (int) ($inspection['critical_failed'] ?? 0) === 1;
            $mode     = (string) Settings::get('slack_on_inspection', 'critical');

            if ($mode === 'off' || ($mode === 'critical' && !$critical)) {
                return;
            }

            $asset = Asset::find((int) $inspection['asset_id']);

            if (!$critical && !self::importantEnough($asset)) {
                return;
            }

            $failed = (int) $inspection['failed_count'];
            $total  = $failed + (int) ($inspection['passed_count'] ?? 0);

            $items = db()->all(
                "SELECT item_text, notes FROM {inspection_items}
                 WHERE inspection_id = ? AND response IN ('fail', 'no')
                 ORDER BY is_critical DESC, sort_order ASC LIMIT 4",
                [(int) $inspection['id']]
            );

            $lines   = [];
            $lines[] = ($critical ? ':no_entry: *Failed safety check*' : ':x: *Failed inspection*')
                     . ' — ' . self::machine($asset) . ' · ' . (string) $inspection['checklist_name'];
            $lines[] = $failed . ' of ' . $total . ' failed' . ($takenOutOfService ? ', taken out of service' : '');

            foreach ($items as $item) {
                $lines[] = '• ' . (string) $item['item_text']
                    . ((string) ($item['notes'] ?? '') !== '' ? ' — ' . self::oneLine((string) $item['notes'], 120) : '');
            }

            if ($failed > count($items)) {
                $lines[] = '• and ' . ($failed - count($items)) . ' more';
            }

            $who     = self::who();
            $meta    = $who !== '' ? ['Checked by ' . $who] : [];
            $meta[]  = self::link('inspection-view.php', ['id' => (int) $inspection['id']], 'Open the inspection');
            $lines[] = implode(' · ', $meta);

            $mention = $critical ? self::mention() : '';

            if ($mention !== '') {
                $lines[0] = $mention . ' ' . $lines[0];
            }

            self::send('inspection', implode("\n", $lines));
        } catch (Throwable $e) {
            log_error('Slack inspection alert failed: ' . $e->getMessage());
        }
    }

    /**
     * A machine changed status.
     *
     * @param array<string, mixed> $asset the row before the change
     */
    public static function statusChanged(array $asset, string $to, string $reason): void
    {
        try {
            if (!self::enabled() || !Settings::bool('slack_on_status', true)) {
                return;
            }

            // A problem report or a failed check that took it out of service has
            // already been posted with the reason; saying it twice is noise.
            if (Str::startsWith($reason, 'Taken out of service by ') || Str::startsWith($reason, 'Failed inspection on ')) {
                return;
            }

            if (!self::importantEnough($asset)) {
                return;
            }

            $head = match ($to) {
                'out_of_service' => ':red_circle: *Out of service*',
                'maintenance'    => ':wrench: *In the shop*',
                'in_service'     => ':large_green_circle: *Back in service*',
                'retired'        => ':file_cabinet: *Retired*',
                default          => ':information_source: *' . Status::label($to, 'asset') . '*',
            };

            $lines   = [];
            $lines[] = $head . ' — ' . self::machine($asset)
                     . ' (was ' . strtolower(Status::label((string) $asset['status'], 'asset')) . ')';

            if ($reason !== '') {
                $lines[] = self::oneLine($reason);
            }

            $who     = self::who();
            $meta    = $who !== '' ? ['By ' . $who] : [];
            $meta[]  = self::link('asset-view.php', ['id' => (int) $asset['id']], 'Open');
            $lines[] = implode(' · ', $meta);

            self::send('status', implode("\n", $lines));
        } catch (Throwable $e) {
            log_error('Slack status alert failed: ' . $e->getMessage());
        }
    }

    /** A maintenance log was saved. */
    public static function jobLogged(int $logId): void
    {
        try {
            if (!self::enabled()) {
                return;
            }

            $mode = (string) Settings::get('slack_on_job', 'followup');

            if ($mode === 'off') {
                return;
            }

            $log = \App\Models\MaintenanceLog::find($logId);

            if ($log === null) {
                return;
            }

            $followUp = (int) ($log['requires_followup'] ?? 0) === 1;

            if ($mode === 'followup' && !$followUp) {
                return;
            }

            $asset = Asset::find((int) $log['asset_id']);

            if (!self::importantEnough($asset)) {
                return;
            }

            $lines   = [];
            $lines[] = ($followUp ? ':bookmark: *Work logged, needs follow-up*' : ':wrench: *Work logged*')
                     . ' — ' . self::machine($asset);
            $lines[] = '*' . (string) $log['title'] . '*';

            $done = self::oneLine((string) ($log['work_performed'] ?? ''));

            if ($done !== '') {
                $lines[] = $done;
            }

            if ($followUp && (string) ($log['followup_notes'] ?? '') !== '') {
                $lines[] = 'Follow-up: ' . self::oneLine((string) $log['followup_notes']);
            }

            $meta = [Status::label((string) $log['log_type'], 'log_type')];

            if ((float) ($log['labor_hours'] ?? 0) > 0) {
                $meta[] = Dates::humanHours((float) $log['labor_hours']);
            }

            $tech = trim((string) ($log['first_name'] ?? '') . ' ' . (string) ($log['last_name'] ?? ''));

            if ($tech !== '') {
                $meta[] = 'by ' . $tech;
            }

            if ((int) ($log['is_completed'] ?? 1) === 0) {
                $meta[] = 'not finished yet';
            }

            $meta[]  = self::link('log-view.php', ['id' => $logId], 'Open');
            $lines[] = implode(' · ', $meta);

            self::send('job', implode("\n", $lines));
        } catch (Throwable $e) {
            log_error('Slack job alert failed: ' . $e->getMessage());
        }
    }

    /**
     * A part dropped to its reorder level.
     *
     * @param array<string, mixed> $part
     */
    public static function lowStock(array $part): void
    {
        try {
            if (!self::enabled() || !Settings::bool('slack_on_stock', true)) {
                return;
            }

            $text = ':package: *Running low* — ' . (string) $part['name']
                . ((string) ($part['part_number'] ?? '') !== '' ? ' (' . (string) $part['part_number'] . ')' : '')
                . "\n" . decimal($part['quantity_on_hand']) . ' ' . (string) $part['unit_of_measure']
                . ' left, reorder at ' . decimal($part['reorder_level'])
                . ((string) ($part['supplier'] ?? '') !== '' ? ' · from ' . (string) $part['supplier'] : '')
                . ' · ' . self::link('part-view.php', ['id' => (int) $part['id']], 'Open the part');

            self::send('stock', $text);
        } catch (Throwable $e) {
            log_error('Slack stock alert failed: ' . $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // Once a day, from cron
    // -------------------------------------------------------------------------

    /** Overdue and upcoming service as one list. Returns a line for the cron output. */
    public static function dueDigest(): string
    {
        if (!self::enabled() || !Settings::bool('slack_on_due', true)) {
            return 'switched off';
        }

        $lead = Settings::int('notify_pm_due_days', 7, 0, 365);
        $rows = Scheduler::due($lead);
        $due  = [];

        foreach ($rows as $row) {
            if (in_array((string) $row['due_state'], ['overdue', 'due', 'due_soon'], true)) {
                $due[] = $row;
            }
        }

        if ($due === []) {
            return 'nothing due';
        }

        $lines = [];

        foreach (array_slice($due, 0, 15) as $row) {
            $state = (string) $row['due_state'];
            $days  = $row['days_until'];

            if ($state === 'overdue') {
                $when = $days === null ? 'Overdue (by meter)' : 'Overdue ' . abs((int) $days) . ' day' . (abs((int) $days) === 1 ? '' : 's');
                $dot  = ':red_circle:';
            } elseif ($days !== null && (int) $days <= 0) {
                $when = 'Due today';
                $dot  = ':large_orange_circle:';
            } else {
                $when = $days === null ? 'Due soon (by meter)' : 'Due in ' . (int) $days . ' day' . ((int) $days === 1 ? '' : 's');
                $dot  = ':large_yellow_circle:';
            }

            $lines[] = '• ' . $dot . ' *' . $when . '* — ' . (string) $row['name']
                     . ' (' . (string) $row['asset_name'] . ')';
        }

        $overdueAll = count(array_filter($due, static fn (array $r): bool => (string) $r['due_state'] === 'overdue'));

        $head = ':calendar: *Service due* — ' . $overdueAll . ' overdue, ' . (count($due) - $overdueAll)
              . ' coming up in the next ' . $lead . ' days';

        if (count($due) > 15) {
            $lines[] = '• and ' . (count($due) - 15) . ' more';
        }

        $lines[] = self::link('schedules.php', ['due' => 'overdue'], 'Open Scheduled Service');

        $result = self::post(self::channelFor('due'), $head . "\n" . implode("\n", $lines), 'due');

        return $result['ok'] ? count($due) . ' listed' : 'FAILED: ' . $result['error'];
    }

    /** The morning report. Returns a line for the cron output. */
    public static function dailySummary(): string
    {
        if (!self::enabled() || !Settings::bool('slack_daily_summary', false)) {
            return 'switched off';
        }

        $down = db()->all(
            "SELECT name, asset_tag, status FROM {assets}
             WHERE deleted_at IS NULL AND status IN ('out_of_service', 'maintenance')
             ORDER BY name ASC LIMIT 12"
        );
        $downCount = db()->count(
            "SELECT COUNT(*) FROM {assets} WHERE deleted_at IS NULL AND status IN ('out_of_service', 'maintenance')"
        );
        $open = db()->one(
            "SELECT COUNT(*) AS n, SUM(priority = 'urgent') AS urgent, SUM(is_safety_issue = 1) AS safety
             FROM {work_orders}
             WHERE deleted_at IS NULL AND status NOT IN ('completed', 'cancelled')"
        );
        $low = db()->all(
            'SELECT name FROM {parts}
             WHERE deleted_at IS NULL AND is_active = 1 AND reorder_level > 0 AND quantity_on_hand <= reorder_level
             ORDER BY name ASC LIMIT 8'
        );

        $overdue = 0;
        $soon    = 0;

        foreach (Scheduler::due(7) as $row) {
            if ((string) $row['due_state'] === 'overdue') {
                $overdue++;
            } elseif (in_array((string) $row['due_state'], ['due', 'due_soon'], true)) {
                $soon++;
            }
        }

        $lines   = [];
        $lines[] = ':sunrise: *Morning report — ' . Dates::localNow()->format('D, M j') . '*';

        if ($downCount === 0) {
            $lines[] = '• Everything is in service';
        } else {
            $names = array_map(static fn (array $a): string => (string) $a['name'], $down);
            $lines[] = '• *Not running: ' . $downCount . '* — ' . implode(', ', $names)
                     . ($downCount > count($names) ? ', …' : '');
        }

        $openN = (int) ($open['n'] ?? 0);
        $extra = [];

        if ((int) ($open['urgent'] ?? 0) > 0) {
            $extra[] = (int) $open['urgent'] . ' urgent';
        }

        if ((int) ($open['safety'] ?? 0) > 0) {
            $extra[] = (int) $open['safety'] . ' safety';
        }

        $lines[] = '• Open problems: ' . $openN . ($extra !== [] ? ' (' . implode(', ', $extra) . ')' : '');
        $lines[] = '• Service overdue: ' . $overdue . ' · due this week: ' . $soon;

        if ($low !== []) {
            $lines[] = '• Parts running low: ' . implode(', ', array_map(static fn (array $p): string => (string) $p['name'], $low));
        }

        $lines[] = self::link('index.php', [], 'Open the dashboard');

        $result = self::post(self::channelFor('summary'), implode("\n", $lines), 'summary');

        return $result['ok'] ? 'posted' : 'FAILED: ' . $result['error'];
    }
}
