<?php

/**
 * RideLog configuration — SAMPLE
 *
 * The web installer writes config/config.php for you. You should not need to
 * create it by hand. This file documents the exact shape it produces, so you
 * can repair a broken install or move the site between servers.
 *
 * To use it manually: copy this file to config/config.php, fill in the
 * database section, and create an empty file at config/installed.lock after
 * you have loaded install/schema.sql and install/seed.sql.
 *
 * This file must never be reachable from the web. The .htaccess in this
 * directory blocks it, and the guard on the first line stops PHP from
 * executing it even if the block fails.
 */

if (!defined('RIDELOG')) {
    die('No direct access');
}

return [

    'app' => [
        // Shown in the browser title bar before the site name loads from the
        // database. The editable name lives in Settings.
        'name' => 'RideLog',

        // The full public address of the application, with no trailing slash.
        // Get this right: password reset links, QR codes and emails are all
        // built from it.
        //   https://example.com                 site at the domain root
        //   https://example.com/maintenance     site in a subfolder
        'url' => 'https://example.com',

        // A 64-character random string, generated at install time. It salts
        // the session fingerprint. Changing it signs everybody out.
        'key' => '',

        // Leave false on a live site. When true, PHP errors and full exception
        // messages are shown in the browser instead of only being logged.
        'debug' => false,

        // Fallback display timezone, used before settings are available.
        // The editable value lives in Settings > Localization.
        'timezone' => 'America/New_York',

        // Turn on ONLY if the site sits behind a load balancer, Cloudflare or
        // another reverse proxy you control. It makes the app believe the
        // X-Forwarded-For and X-Forwarded-Proto headers, which anyone can
        // forge if there is no proxy in front.
        'trust_proxy' => false,
    ],

    'db' => [
        // On nearly every cPanel account this is 'localhost'.
        'host' => 'localhost',
        'port' => 3306,

        // cPanel prefixes database and user names with your account name,
        // e.g. account_ridelog and account_rluser.
        'name' => '',
        'user' => '',
        'pass' => '',

        'charset' => 'utf8mb4',

        // Prefix applied to every table name. Lets several applications share
        // one database. Changing it after installation orphans your data.
        'prefix' => 'rl_',

        // Optional. Set only if your host requires a socket path instead of a
        // host name; leave empty otherwise.
        'socket' => '',
    ],

    'security' => [
        // The session cookie name. Letters, numbers and underscores only.
        'session_name' => 'ridelog_session',

        // Ends a session when the client IP changes. Off by default because
        // technicians on phones move between wifi and mobile data constantly,
        // which would sign them out mid-inspection.
        'bind_session_ip' => false,
    ],
];
