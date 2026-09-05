# Installing RideLog

Written for cPanel, because that is what most parks have. If you are on
something else, everything here still applies except the names of the buttons.

You will need about fifteen minutes and no shell access.

---

## Before you start

Check your PHP version. In cPanel, **Software → Select PHP Version**. Anything
from **8.0** upward works; 8.1 or newer is better. While you are on that screen,
tick these extensions if they are not already on:

- `pdo_mysql` — talking to the database
- `mbstring` — accented characters and anything typed in another language
- `fileinfo` — checking that an uploaded file is what it claims to be
- `gd` — resizing photos and stripping their metadata
- `openssl` — secure tokens, and SMTP if you use it

The installer checks all of this and tells you what is missing, so you can also
just carry on and let it tell you.

---

## 1. Upload the files

**File Manager → public_html**, then Upload the zip and extract it there.

- To have the site at `https://yourdomain.com/`, extract into `public_html`.
- To have it at `https://yourdomain.com/maintenance/`, make that folder first
  and extract into it.

Either works. The application never assumes it is at the root of a domain.

**Permissions.** cPanel's defaults are almost always right: folders `755`,
files `644`. Two folders must be writable by PHP:

- `storage/` (and everything under it)
- `config/`

If the installer complains that it cannot write, right-click each of those in
File Manager, choose **Change Permissions**, and set them to `755`. Only if that
still fails should you try `775`. Do not use `777` — on shared hosting that
means every other account on the server can write there too.

---

## 2. Make a database

**Databases → MySQL Database Wizard.** Three screens:

1. **Database name.** Something like `ridelog`. cPanel puts your account name in
   front, so the real name ends up as `youracct_ridelog` — write that down, it
   is what the installer wants.
2. **Username and password.** Again cPanel prefixes the username. Use the
   *Password Generator*, and copy the password somewhere before you click on.
3. **Privileges.** Tick **ALL PRIVILEGES**.

You now have three things the installer needs: the database name, the username
and the password. The host is `localhost` on essentially every cPanel account.

---

## 3. Run the installer

Open `https://yourdomain.com/install/` (add your subfolder if you used one).

**Welcome.** What is about to happen, and what you need to hand.

**Requirements.** A green tick per item. Anything red has to be fixed before you
can go on; each one says exactly what to change and where. Amber is a warning
you can ignore for now.

**Database.** The four values from step 2. Leave the port at `3306` and the
table prefix at `rl_` unless you know why you are changing them. The installer
connects, then creates and drops a test table to prove it really can write —
better to find out now than halfway through.

> **"Could not reach the database server"** is nearly always the host name. On
> cPanel it is `localhost`, not the domain and not an IP address.
>
> **"Access denied"** means the username or password is wrong, or you did not
> tick ALL PRIVILEGES on the wizard's last screen.

**Your account.** Your name, a username, an email address and a password. This
is the administrator account — the one that can add everybody else. Use a real
email address; it is how you get back in if you forget the password.

**About your park.** The site name (which appears in the header and on printed
reports), your organisation's name, your time zone, and the web address the site
will live at. Get the time zone right — every date in the system is displayed in
it. You also choose what to **start with**:

- **The Castle Fun Center fleet** (the default) — twenty go-karts, the Freefall,
  Dragon Coaster and Swings, the zip line, twelve bowling lanes, six axe-throwing
  lanes, and the laser tag arena, roller rink, climbing wall, mini golf course,
  arcade and shop compressor. Each comes with its daily checklist and its service
  schedule, and with no made-up history. Every name, tag and category can be
  changed afterwards from the Machines screen; the indoor extras were guesses,
  so check those first.
- **Fictional sample data** — fourteen made-up karts and rides with a year of
  history behind them. Good for a look around, and easy to clear out afterwards.
- **Nothing** — just the categories, locations and the two standard checklists.

**Install.** Ten seconds or so. Then a page with your cron command on it and a
short list of things to do.

---

## 4. Three things to do straight after

**Delete the `install/` folder.** The installer locks itself after a successful
run, but a folder that cannot be reached at all is better than one that refuses
politely. File Manager → select `install` → Delete.

**Set up the nightly job.** **Advanced → Cron Jobs**. Set it to run **once a
day** — 6am is good, before anyone opens up — and paste in the command from the
installer's last page. It looks like:

```
curl -s "https://yourdomain.com/cron.php?token=LONGRANDOMSTRING"
```

You can find it again later under **Settings → Security**. The job works out
what maintenance has fallen due, warns about parts running low, and clears out
old records. Nothing breaks without it; you just stop getting told things.

**Turn on HTTPS.** **Security → SSL/TLS Status** and run AutoSSL if your host
has not already. Then open `.htaccess` in the root of the site and uncomment the
five lines under "Force HTTPS", and the `Strict-Transport-Security` line just
above them. Passwords going over plain HTTP is not a thing to leave for later.

Then, when you have a minute, three settings screens make it yours:

- **Settings → Features** — switch off whatever this site will not use. Off
  means gone from the menus and forms; nothing is deleted.
- **Settings → Fields** — add anything the machine form should ask that it does
  not already: seat size, gas or electric, a supplier's part number.
- **Roles** (under Administration) — tick what each role may do, if the
  defaults are not right. Technicians adding machines, say, or managers seeing
  costs.

---

## Slack alerts (optional)

RideLog can post to a Slack channel when a problem is reported, a daily check
fails, a machine goes out of service, a job needs follow-up, a part runs low,
and once a morning with what is due. Each of those can be switched on or off
and sent to its own channel, so the maintenance channel is as busy or as quiet
as you like.

You need a Slack app with a bot token. Five minutes:

1. Go to **api.slack.com/apps** → **Create New App** → **From scratch**. Name it
   (something like *Castle Maintenance*) and pick your workspace.
2. **OAuth & Permissions** → **Scopes** → **Bot Token Scopes** → add `chat:write`.
3. **Install to Workspace** and allow it.
4. Copy the **Bot User OAuth Token** — it starts with `xoxb-`.
5. In Slack, open the channel you want alerts in and type `/invite @` followed by
   the app's name. Do the same for any other channel you name in the settings.
6. In RideLog, **Settings → Slack**: paste the token, set the main channel (with
   the `#`), save, and press **Send a test message**. If it arrives, turn
   **Post to Slack** on and save.

If the test fails, the message on screen says what is wrong: the token was not
accepted, the bot has not been invited to the channel, the app is missing the
`chat:write` scope, or the server cannot reach Slack at all (rare on cPanel;
ask your host about outgoing connections).

The token is stored like a password: it is never shown again, never included in
exports, and anyone with it can post as the app. The "who to alert" box takes
`@here`, `@channel` or a member ID and is added only to urgent and safety
messages.

---

## Clearing out the demonstration data

If you installed it to have a look and now want to start properly:

**Databases → phpMyAdmin**, pick your database, click **SQL**, and run:

```sql
DELETE FROM rl_assets;
DELETE FROM rl_parts;
DELETE FROM rl_work_orders;
```

Maintenance logs, inspections, schedules and part movements are all attached to
those and go with them. Your settings, checklists, categories, locations and
user accounts stay.

(Change `rl_` if you chose a different prefix.)

---

## If you are not on Apache

The application relies on `.htaccess` to keep three folders private. On nginx
that does nothing, so add this to your server block:

```nginx
location ~ ^/(app|config|storage)/ { deny all; return 404; }
location ~ ^/install/ { deny all; return 404; }   # after installing
location ~ \.(sql|log|lock|ini|sh|bak|old)$ { deny all; return 404; }
location ~ /\. { deny all; return 404; }
```

The security headers the `.htaccess` sets are also sent by PHP itself, so those
need no nginx equivalent.

---

## Upgrading

1. Take a backup first: **Files → Backup → Download a MySQL Database Backup**.
2. Upload the new files over the old ones. Do not delete `config/` or
   `storage/` — your settings and your uploaded photos are in there.
3. Open `https://yourdomain.com/install/upgrade.php` and sign in as an
   administrator. It applies whatever database changes the new version needs and
   tells you what it did.
4. Delete `install/` again.

Upgrades are additive: nothing in your data is removed or rewritten. The runner
keeps a note of which database changes it has applied, so running it twice is
harmless, and a fresh install already counts every change as applied.

---

## When something goes wrong

**A blank white page.** PHP hit an error and was told not to show it. Look in
`storage/logs/` — there is a file per day. If it is empty, check the error log
in cPanel under **Metrics → Errors**.

**"Configuration file not found".** `config/config.php` is missing, so the
application thinks it has not been installed. If you deleted it by accident,
re-run the installer; your data is untouched and it will reconnect to the same
database.

**A photo will not upload.** Two limits apply and the smaller wins: the one in
**Settings → Uploads**, and your host's `upload_max_filesize`. The Uploads
settings page tells you what the host limit currently is. Raise it in **Select
PHP Version → Options**.

**Email is not arriving.** Settings → Email, and use **Send it** to test. The
commonest cause by far is a "from" address on a domain that is not yours — many
hosts reject those outright. Use something like
`maintenance@yourdomain.com`. If PHP's `mail()` is unreliable on your host,
switch the transport to SMTP and use the mailbox details from **Email
Accounts → Connect Devices**.

**Everybody is locked out.** Five wrong passwords locks an account for fifteen
minutes; waiting is the easy fix. If an administrator account is genuinely lost,
phpMyAdmin → `rl_users` → edit the row → set `locked_until` to `NULL`. To reset
the password, put a bcrypt hash in `password_hash` — or just set
`must_change_password` to `1` and use the "forgotten password" link.
