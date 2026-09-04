# Using RideLog

This is for the people who use it: mechanics, ride operators, whoever is running
the shop. Nothing here needs any computer knowledge.

If you only ever read one section, read the first one.

---

## Logging a job

You have just done something to a machine. Record it before you forget.

1. Click **Log maintenance** — it is the blue button at the top of the
   dashboard, and it is on every machine's page too.
2. Pick the machine.
3. Pick the kind of work: a repair, a service, a daily check, a modification.
4. Give it a short title. Write it the way you would say it: *"Replaced front
   brake pads"*, *"Chain kept jumping — swapped the sprocket"*.
5. The date and time are already set to now. If you are writing up something
   from this morning, change it.
6. **Save.**

That is the whole thing. Four fields.

Everything else is optional and folded away behind headings you can open if you
want them:

- **What you did** — the detail. Worth writing when the next person will need to
  know.
- **Parts used** — pick from the shelf and it comes off the count by itself. You
  can also type in something you bought specially.
- **Time, cost and condition** — how long it took, the meter reading, how long
  the machine was out of service, what it cost.
- **Follow-up** — tick it and a work order opens automatically, so the thing you
  noticed does not get forgotten.

> **Meter readings only go up.** If you type a number lower than the one already
> recorded, it will stop and ask you to check it. That is nearly always a typo,
> and a wrong meter reading throws off every service that is scheduled by hours.

### Editing something you got wrong

You can edit your own logs. A manager can edit anybody's. If somebody else
recorded it and it needs changing, ask a manager — this is deliberate, because a
maintenance record is a record.

---

## The daily check

The screen for this is built to be used one-handed, on a phone, standing next to
the machine at eight in the morning.

1. **Run an inspection** on the dashboard, or scan the sticker on the machine.
2. Pick the machine, if you did not scan it.
3. Work down the list. Each line is a big **Pass** / **Fail** button.
4. Anything you mark **Fail** opens a note box. Say what is wrong — *"pads down
   to the wear line on the near side"* is worth ten times more than "worn".
5. The bar at the bottom keeps count of how far through you are.
6. Type your name and press **Finish**.

**You can stop and come back.** Press **Save for later** and everything you have
answered is kept. If your phone loses signal halfway round, nothing is lost —
open it again and carry on from where you were.

**A red line marked "Safety" is the important kind.** If you fail one of those,
tick *"Take this asset out of service"* at the bottom. The machine is marked as
not available to guests, and an urgent work order is raised straight away so
somebody owns the problem.

Finished checks are kept and can be printed. If an inspector or an insurer ever
asks who checked what and when, it is all there under **Inspections**.

---

## Scanning the sticker

Every machine can have a QR sticker on it. Point your phone's camera at it and
it opens that machine's page — meter, history, open work orders, everything.

You need to be signed in. The first time each day it will ask; after that it
just works.

If somebody has set the stickers up to do so, scanning can drop you straight
into a new maintenance log or a new inspection instead. Ask whoever printed
them.

To print stickers: **Assets → Print labels**, or the little QR button on any
machine's page.

---

## Reporting a problem you are not fixing yourself

Use a **work order**. It is the difference between "somebody mentioned the
bumper car was pulling left" and a job with a name on it.

**Report an issue** on the dashboard or on the machine's page. Say what is
wrong, how urgent it is, and save. A manager assigns it; whoever it is assigned
to sees it on their dashboard under *My work*.

When it is fixed, open the work order and click **Log the work**. The
maintenance log fills itself in from the work order, and completing it closes
the work order off.

---

## Parts

**Parts Inventory** is what is on the shelf.

The number on each row is how many there are. If you took some, type how many
and press **Took**; if you put some back, press **Back**. That is it — every
change is recorded with your name against it.

You do not need to do this for parts used on a job. Add them to the maintenance
log instead and the count looks after itself.

Parts running low show up on the dashboard and turn amber in the list. Somebody
sets the reorder point per part; when the count drops to it, the people who
order things get told.

---

## Scheduled maintenance

**PM Schedules** is the work that comes round on its own: 50-hour services,
weekly greasing, the pre-season strip-down.

A schedule can be by the calendar (*every 30 days*), by the meter (*every 50
hours*), or both — whichever comes round first.

You do not usually have to look at this page. What is due appears on the
dashboard, with a **Log it** button beside each one that opens a maintenance log
with everything already filled in. Do the job, press save, and the schedule rolls
itself forward.

Anything overdue is red. Anything due within the warning period is amber.

---

## Finding things

**The search box at the top of every page** searches everything at once —
machines, jobs, work orders, parts, inspections. Type a kart number, a part
name, part of a job title. Press `/` on a keyboard to jump straight into it.

Every list also has filters along the top, and every list can be sorted by
clicking a column heading.

---

## Reports

**Reports** answers the questions somebody asks you once a month:

- **Maintenance history** — everything that happened, in a period
- **What it cost** — spend per machine, split into parts, labour and the rest
- **Month by month** — the trend
- **Downtime** — how long each machine was out of service, and how often
- **Inspection record** — who checked what, how often, and what failed
- **Asset list** — every machine with its meter, status and lifetime cost
- **Parts used** — what came off the shelf and what it cost
- **Who did the work** — jobs, hours and inspections per person

Pick a period at the top. Every report exports to a spreadsheet with **Export**,
and prints properly with **Print**.

---

## Your account

Click your name, top right.

- **My profile** — your name, email, phone, and a photo if you want one
- **Change password** — do it if you were handed one by somebody else
- **Notifications** — what the system has told you

The sun/moon button switches between light and dark. It remembers, and it
follows you between the workshop tablet and the office computer.

---

## Who can do what

| | Viewer | Technician | Manager | Administrator |
|---|:---:|:---:|:---:|:---:|
| See everything | ✓ | ✓ | ✓ | ✓ |
| Log maintenance | | ✓ | ✓ | ✓ |
| Edit their own logs | | ✓ | ✓ | ✓ |
| Edit anybody's logs | | | ✓ | ✓ |
| Run inspections | | ✓ | ✓ | ✓ |
| Raise work orders | | ✓ | ✓ | ✓ |
| Assign and close work orders | | | ✓ | ✓ |
| Take parts off the shelf | | ✓ | ✓ | ✓ |
| Add and edit machines | | | ✓ | ✓ |
| Add and edit parts | | | ✓ | ✓ |
| Build checklists and schedules | | | ✓ | ✓ |
| Export reports | | | ✓ | ✓ |
| See the audit log | | | ✓ | ✓ |
| Add and remove people | | | | ✓ |
| Change settings | | | | ✓ |

If you cannot do something you think you should be able to, that is the reason.
Ask an administrator.

---

## Things worth knowing

**Nothing is really deleted.** Deleting a machine keeps its whole maintenance
history — it just stops appearing in lists. Removing somebody keeps their name
on every job they logged. This is on purpose: a maintenance record with holes in
it is worse than none.

**Everything is recorded.** Who changed what, and when, is in the audit log.
Not to catch anybody out — so that when something looks wrong six months later,
it is possible to work out what happened.

**It works on a phone.** Every screen does. The daily check is built for it
specifically.

**It works without a signal, up to a point.** It is a website, so it needs a
connection to save. But the inspection screen saves as you go, so a dropout
costs you nothing.
