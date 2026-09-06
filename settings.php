<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\Acl;
use App\Auth;
use App\Csrf;
use App\Dates;
use App\Mailer;
use App\Request;
use App\Settings;
use App\Str;
use App\Uploader;
use App\View;

Auth::requireLogin();
Acl::requirePermission('settings.manage');

$groups = Settings::groups();
$tab    = Request::string('tab', 'general');

if (!isset($groups[$tab])) {
    $tab = 'general';
}

// -----------------------------------------------------------------------------
// Save
// -----------------------------------------------------------------------------

if (is_post()) {
    Csrf::verify();

    $action = Request::string('action');

    // ----------------------------------------------------------- test email
    if ($action === 'test_email') {
        $to = Request::string('test_email');

        if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            flash('error', 'Type an email address to send the test to.');
        } else {
            $result = Mailer::sendTest($to);

            if ($result['ok']) {
                flash('success', 'Test message sent to ' . $to . '. '
                    . 'If it does not arrive within a few minutes, check the spam folder '
                    . 'and then the settings below.');
            } else {
                flash('error', 'The test could not be sent: ' . (string) ($result['message'] ?? 'unknown error'));
            }

            audit('settings.test_email', 'setting', null,
                'Sent a test email to ' . $to . ($result['ok'] ? ' (delivered to the mail server)' : ' (failed)'));
        }

        redirect(url('settings.php', ['tab' => 'email']));
    }

    // ----------------------------------------------------------- test Slack
    if ($action === 'test_slack') {
        $result = \App\Slack::test();

        if ($result['ok']) {
            flash('success', 'Posted to Slack'
                . ($result['error'] !== '' ? ' (workspace: ' . $result['error'] . ')' : '')
                . '. If you can see it in the channel, turn "Post to Slack" on and save.');
        } else {
            flash('error', 'Slack test failed: ' . $result['error']);
        }

        audit('settings.test_slack', 'setting', null, 'Sent a Slack test message' . ($result['ok'] ? '' : ' (failed)'));

        redirect(url('settings.php', ['tab' => 'slack']));
    }

    // ----------------------------------------------------------- extra fields
    if ($action === 'save_fields') {
        $rows   = $_POST['fields'] ?? [];
        $errors = \App\CustomFields::save(is_array($rows) ? $rows : []);

        if ($errors !== []) {
            flash_errors(['fields' => implode(' ', $errors)], $_POST);
        } else {
            audit('settings.update', 'setting', null, 'Changed the extra fields on ' . asset_word(true));
            flash('success', 'Fields saved. They are on the ' . asset_word() . ' form now.');
        }

        redirect(url('settings.php', ['tab' => 'fields']));
    }

    // ----------------------------------------------------------- starting fleet
    if ($action === 'load_fleet') {
        $result = \App\Fleet::load();

        if ($result['ok'] && $result['machines'] + $result['checklists'] + $result['schedules'] === 0) {
            flash('info', 'The starting fleet was already here. Nothing was added.');
        } elseif ($result['ok']) {
            flash('success', 'The starting fleet is in: ' . $result['machines'] . ' ' . asset_word(true) . ', '
                . $result['checklists'] . ' checklists and ' . $result['schedules']
                . ' service schedules added. Nothing you already had was changed.');
        } else {
            flash('error', 'The fleet was only partly loaded: ' . implode(' ', array_slice($result['errors'], 0, 2)));
        }

        redirect(url('settings.php', ['tab' => 'system']));
    }

    // ----------------------------------------------------------- new cron token
    if ($action === 'new_cron_token') {
        Settings::set('cron_token', Str::random(48));
        audit('settings.update', 'setting', null, 'Generated a new cron token');
        flash('success', 'New cron token. Update the cron command in cPanel to match.');

        redirect(url('settings.php', ['tab' => 'security']));
    }

    // ----------------------------------------------------------- upload a logo
    // logo_path holds an attachment id, so the logo goes through exactly the
    // same checks as any other upload: real image, re-encoded, EXIF stripped.
    if ($action === 'logo') {
        $files = Request::files('logo');

        if ($files === []) {
            flash('error', 'Choose an image first.');
        } else {
            $result = Uploader::handle($files[0], 'setting', 0, Auth::id(), 'Site logo');

            if (!$result['ok']) {
                flash('error', $result['error']);
            } elseif ((int) $result['attachment']['is_image'] !== 1) {
                Uploader::delete((int) $result['id']);
                flash('error', 'The logo has to be an image.');
            } else {
                $previous = (int) Settings::get('logo_path', 0);

                Settings::set('logo_path', (string) $result['id']);

                if ($previous > 0) {
                    Uploader::delete($previous);
                }

                audit('settings.update', 'setting', null, 'Changed the logo');
                flash('success', 'Logo updated.');
            }
        }

        redirect(url('settings.php', ['tab' => 'branding']));
    }

    if ($action === 'remove_logo') {
        $previous = (int) Settings::get('logo_path', 0);

        Settings::set('logo_path', '');

        if ($previous > 0) {
            Uploader::delete($previous);
        }

        audit('settings.update', 'setting', null, 'Removed the logo');
        flash('success', 'Logo removed. The default mark is back.');

        redirect(url('settings.php', ['tab' => 'branding']));
    }

    // ----------------------------------------------------------- the tab itself
    $group    = Request::string('group', 'general');
    $group    = isset($groups[$group]) ? $group : 'general';
    $rows     = Settings::group($group);
    $changes  = [];
    $changed  = [];

    foreach ($rows as $row) {
        $key  = (string) $row['setting_key'];
        $type = (string) $row['setting_type'];

        // Internal keys are not on the form and must not be writable from it.
        // A heading is just a title in the form, with nothing to save.
        if ($type === 'hidden' || $type === 'heading') {
            continue;
        }

        if ($type === 'bool') {
            $value = Request::bool('s_' . $key) ? '1' : '0';
        } elseif (!array_key_exists('s_' . $key, $_POST)) {
            continue;
        } elseif (!is_scalar($_POST['s_' . $key])) {
            // A field posted as an array is not something our form sends.
            continue;
        } else {
            $value = (string) $_POST['s_' . $key];
        }

        $value = trim($value);

        // A blank password box means "leave the stored one alone", not "clear
        // it" — otherwise saving any other setting wipes the SMTP password.
        if ($type === 'password' && $value === '') {
            continue;
        }

        if ($type === 'int') {
            $value = (string) (int) preg_replace('/[^0-9-]/', '', $value);
        }

        if ($type === 'email' && $value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            flash('error', (string) $row['label'] . ' does not look like an email address.');
            redirect(url('settings.php', ['tab' => $group]));
        }

        if ($type === 'color' && $value !== '' && preg_match('/^#[0-9a-fA-F]{6}$/', $value) !== 1) {
            flash('error', (string) $row['label'] . ' must be a colour like #4f46e5.');
            redirect(url('settings.php', ['tab' => $group]));
        }

        if ($key === 'timezone' && $value !== '' && !in_array($value, timezone_identifiers_list(), true)) {
            flash('error', 'That is not a timezone this server knows about.');
            redirect(url('settings.php', ['tab' => $group]));
        }

        if ((string) $row['setting_value'] !== $value) {
            $changed[] = (string) ($row['label'] !== '' ? $row['label'] : $key);
        }

        $changes[$key] = $value;
    }

    if ($changes !== []) {
        Settings::setMany($changes);
    }

    if ($changed !== []) {
        audit('settings.update', 'setting', null,
            'Changed ' . implode(', ', array_slice($changed, 0, 8))
            . (count($changed) > 8 ? ' and ' . (count($changed) - 8) . ' more' : ''));
        flash('success', 'Saved.');
    } else {
        flash('info', 'Nothing changed.');
    }

    redirect(url('settings.php', ['tab' => $group]));
}

// -----------------------------------------------------------------------------
// Page
// -----------------------------------------------------------------------------

// Choices for the settings that are a pick-list rather than free text.
$choices = [
    'timezone'       => Dates::timezones(),
    'date_format'    => [
        'M j, Y'  => Dates::sampleFormat('M j, Y')  . '  (Sep 4, 2026)',
        'j M Y'   => Dates::sampleFormat('j M Y')   . '  (4 Sep 2026)',
        'd/m/Y'   => Dates::sampleFormat('d/m/Y')   . '  (04/09/2026)',
        'm/d/Y'   => Dates::sampleFormat('m/d/Y')   . '  (09/04/2026)',
        'Y-m-d'   => Dates::sampleFormat('Y-m-d')   . '  (2026-09-04)',
        'D, M j'  => Dates::sampleFormat('D, M j')  . '  (Fri, Sep 4)',
    ],
    'time_format'    => [
        'g:i A' => Dates::sampleFormat('g:i A') . '  (2:30 PM)',
        'H:i'   => Dates::sampleFormat('H:i')   . '  (14:30)',
    ],
    'week_start'     => ['0' => 'Sunday', '1' => 'Monday'],
    'theme_default'  => ['system' => 'Match the device', 'light' => 'Always light', 'dark' => 'Always dark'],
    'mail_transport' => Mailer::transportChoices(),
    'smtp_secure'    => Mailer::encryptionChoices(),
    'slack_min_criticality' => [
        'any'      => 'Every ' . asset_word(),
        'medium'   => 'Medium importance and up',
        'high'     => 'High importance and up',
        'critical' => 'Critical ' . asset_word(true) . ' only',
    ],
    'slack_on_problem' => [
        'off'    => 'Do not post problems',
        'urgent' => 'Urgent only',
        'high'   => 'High and urgent',
        'all'    => 'Every problem',
    ],
    'slack_on_inspection' => [
        'off'      => 'Do not post',
        'critical' => 'Only when a safety-critical item fails',
        'any'      => 'Any failed item',
    ],
    'slack_on_job' => [
        'off'      => 'Do not post',
        'followup' => 'Only jobs needing follow-up',
        'all'      => 'Every job',
    ],
];

View::render('settings/index', [
    'title'      => 'Settings',
    'subtitle'   => 'How this copy of ' . Settings::siteName() . ' behaves',
    'activeNav'  => 'settings.php',
    'tab'        => $tab,
    'groups'     => $groups,
    'rows'       => Settings::group($tab),
    'choices'    => $choices,
    'uploadLimit' => Settings::hostUploadLimitBytes(),
    // The System tab is a health report, not a form.
    'health'     => $tab === 'system' ? \App\Health::report() : null,
    // The Fields tab is its own editor. After a rejected save it shows what
    // was typed, not what is stored, so nothing has to be retyped.
    'customFields' => $tab === 'fields'
        ? (is_array(old('fields', null)) ? \App\CustomFields::build((array) old('fields', []))['fields'] : \App\CustomFields::all())
        : [],
]);
