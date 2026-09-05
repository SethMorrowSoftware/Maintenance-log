# RideLog

A maintenance log and dashboard for go-karts, rides and the rest of the
equipment at an amusement park. Built for the Castle Fun Center, and for
anybody else with a shed full of machines and a shared cPanel account.

The people who use it every day are mechanics, not IT people, so it is built
around that: logging a job takes four fields and thirty seconds, the daily
safety check is a screen of big Pass / Fail buttons on a phone, and a sticker on
the kart opens that kart's page when you point a camera at it.

**Plain PHP, plain MySQL, plain JavaScript.** No Composer, no npm, no build
step, no CDN. Upload the folder, open it in a browser, answer six questions,
and it runs.

---

## What it does

**Log maintenance.** Who did what, to which machine, when, how long it took and
what it cost. Parts used come off the shelf count automatically. Four fields are
required and the date and time are already filled in; everything else is folded
away until you want it.

**Keep track of the machines.** Every kart, ride, boat and compressor, with its
hour meter, its service history, its lifetime cost and its current status. Each
one has a **History** tab: every job, check, reported problem, status change and
meter reading in one searchable list — the page to open before pulling
something apart. The same recent history sits beside the log form and the
report-a-problem form while you type.

**Preventive maintenance that chases you.** Schedule a job by the calendar, by
the meter, or whichever comes first. The dashboard shows what is due and what is
overdue, and the nightly job tells people about it.

**Daily safety checks.** Build a checklist once; run it off a phone standing
next to the machine. A failed safety-critical item takes the machine out of
service and raises an urgent work order by itself. The completed check prints as
a signed record.

**Work orders.** Somebody reports a problem, a manager assigns it, a mechanic
fixes it and logs the job — and the work order closes off the log.

**Parts inventory.** What is on the shelf, what it cost, where it lives and when
to reorder. Using a part on a job takes it off the count and writes a movement
nobody has to remember to enter.

**Reports.** Maintenance history, spend per machine, month by month, downtime,
inspection compliance, the machine list, parts used and who did the work. Every
one of them exports to CSV and prints.

**Money is the administrator's business.** Prices, costs and spend are shown to
administrators only — on pages, in reports, in exports and in the API.
Technicians and managers record hours and parts; the cost is worked out behind
the scenes from the shelf price and the default labour rate, so the
administrator's figures are still right.

**QR labels.** A sheet of stickers, one per machine. Point a phone at one and it
opens that machine's page — or straight into a new log, if that is what you set
the labels to do.

**Four roles, and the administrator decides what they mean.** Viewer,
technician, manager, administrator. Out of the box a technician can log their
own work and run inspections, and gets a simpler home page built around three
buttons; a manager can do everything with the machines; an administrator can
also manage people and settings, and is the only one who sees money. The
**Roles** page is a grid of every permission against every role: tick what each
may do, and reset to the defaults when you change your mind. Administrators
always keep everything.

**Everything is optional.** Work orders, scheduled service, inspections, parts,
meters, downtime, money, photos, labels, reports, notifications, the audit log
and drafts each have a switch under Settings → Features. Off means gone from
the menus and forms; nothing is deleted, and switching back on brings the
records straight back.

**Fields you can add.** Settings → Fields puts whatever else the site needs on
every machine — seat size, gas or electric, a supplier's part number — as text,
a number, a date, yes or no, or a pick from a list. They show on the machine's
form and page, in the export, in search, and optionally as list columns.

**Built for entering a fleet.** "Add another like this" on any machine, and
"Save and add another like it" on the form, start the next one already filled
in with the next name in the sequence. A machine missing from the picker on the
log form can be added from right there, and you land back on the form with it
chosen and your typing intact.

**Nothing typed is lost.** The log and report forms keep a draft on the device
as you type and offer it back if the phone locks, the tab closes or the session
expires.

**It tells you when it is unwell.** Settings → System checks PHP, the database,
the storage folders, the nightly job and the error log in plain language, and
has a one-click download of every record as a ZIP of spreadsheets.

**It talks to Slack.** With a bot token and one `chat:write` scope, problems
reported, failed checks, machines going out of service, jobs needing follow-up,
parts running low and a morning "what is due" list post to a channel. Each is
its own switch with its own channel, a priority floor, and an @mention for
urgent and safety messages.

**It starts with the real fleet.** The installer offers the Castle Fun Center
fleet — twenty go-karts, the Freefall, Dragon Coaster and Swings, the zip line,
bowling lanes, axe-throwing lanes and the indoor attractions — each with its
daily checklist and service schedule. A site that started empty can add the
same fleet later from Settings → System, without touching what it already
has. Everything is renamable, and the word "machines" itself is a setting: call
them rides, assets or appliances and every label follows.

---

## What it needs

| | |
|---|---|
| PHP | 8.0 or newer (8.1+ recommended) |
| Extensions | `pdo_mysql`, `mbstring`, `json`, `fileinfo`, `openssl`, and `gd` for photo resizing |
| Database | MySQL 5.7+ or MariaDB 10.3+ |
| Web server | Apache with `.htaccess` (any cPanel account), or nginx with the rules in `docs/INSTALL.md` |
| Browser | Anything current. It works without JavaScript, just with more page loads. |

No shell access needed. No Composer. No cron either, strictly — the nightly job
makes due-date warnings and tidying automatic, but nothing breaks without it.

---

## Installing it

Upload the folder, then open it in a browser. The installer checks the server,
takes your database details, creates the tables, makes your administrator
account and writes the configuration file for you.

`docs/INSTALL.md` walks through it screen by screen, with the cPanel bits
spelled out.

---

## Finding your way around

| | |
|---|---|
| `docs/INSTALL.md` | Installing, upgrading, and the cPanel specifics |
| `docs/USER-GUIDE.md` | For the people who use it: logging a job, running a check, ordering parts |
| `docs/SPEC.md` | The technical contract every file in here obeys |

---

## How it is put together

```
index.php, logs.php, log-edit.php …   one file per page, no routing to learn
app/
  bootstrap.php      autoloader, session, config, helpers
  Database.php       PDO wrapper; every query uses {table} placeholders
  Auth.php Acl.php   sessions, roles, permissions
  Dates.php          every timezone conversion in the application
  Models/            one class per domain
  Api/               the small JSON API the pages use themselves
  Views/             layouts, partials, one folder per domain
assets/css assets/js dependency-free, no build step
install/             the web installer, the schema and the seed data
storage/             uploads, logs, cache — writable, and denied to the web
config/              written by the installer, denied to the web
```

Three rules that hold everywhere and explain most of the code:

**Every table name in every query is written `{like_this}`** and expanded with
the installed prefix. It is impossible to forget the prefix, because there is no
way to write a query without one.

**Every datetime is stored in UTC** and converted for display in exactly one
place, `App\Dates`. Dates that mean a calendar day — a purchase date, a service
due date — are stored as dates and never shifted.

**Every dynamic value is escaped where it is printed**, with `e()`. There is no
templating engine deciding for you, so it is done at the point of output, every
time.

---

## Security

Passwords are bcrypt through PHP's own `password_hash`. Sessions are
regenerated on login, bound to the browser, and expire on inactivity. Five bad
password attempts locks an account for fifteen minutes, and the correct password
will not open it early. Every form carries a CSRF token; every non-GET API call
must carry one too. Uploads are checked by extension, by real content type, and
by whether an image actually decodes — then re-encoded through GD, which strips
anything hidden inside. Every query is a prepared statement, and anything that
reaches an `ORDER BY` comes from a hard-coded list. `app/`, `config/` and
`storage/` are denied to the web.

If you find something wrong with any of that, the honest thing is to say so:
open an issue.

---

## Licence

MIT. Do what you like with it.
