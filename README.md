# Content schedule for Cosray

A Cosray plugin for scheduled publishing. In a node's editor, an editor plans when the node goes online and when it goes offline. Going online brings the changes saved by then live, so the same time plans an update of a node that is online. An overview lists what is planned across the site.

Nothing about a plan is checked when visitors read the site. At the planned time a job does what the editor would do by hand, so the node's history, webhooks, the API's sync and search all see an ordinary publication.

## Installation

```bash
composer require cosray/content-schedule
```

Register the plugin in the app's bootstrap and apply its migration:

```php
$app->plugin(\Cosray\ContentSchedule\Plugin::class);
```

```bash
php run db:migrations --apply
```

The plugin keeps one table, `plans`, in a database namespace of its own: the schema `content_schedule` on PostgreSQL, which Cosray creates, or the name prefix `content_schedule_` where the site uses name prefixes. A site chooses another one with `'content-schedule.prefix'` in `db.placeholders`. A plugin is PHP code with the site's full access; install it only if you trust its code.

To remove the plugin, unregister it, drop its schema with `DROP SCHEMA content_schedule CASCADE;` and delete its migration record with `DELETE FROM migrations WHERE migration LIKE 'content-schedule:%';`. Without a schema, drop the table `content_schedule_plans` instead. Wake-up jobs it left on the queue fail and can be pruned with `queue:prune`.

## Scheduling a type

Add `#[Schedule]` to the node types editors may plan:

```php
use Cosray\ContentSchedule\Schedule;

#[Label('Article'), Route('/articles/{title}'), Schedule]
final class Article implements Title {}
```

The attribute takes no arguments. A `#[Record]` has no publication, so it cannot carry it.

## In the editor

A scheduled type's editor shows a Schedule section in the inspector's status tab, below the publication switch. It offers two times, whatever the node's state: Online from and Offline from. A type without a route words them as Active from and Inactive from, as the rest of the panel words its publication.

Online from brings the node online with the changes saved by then; for a node that is online already, it brings only those changes live. Offline from takes the node offline. Together the two times make a window or, for a node that is online, a pause.

Saving the schedule plans each time given, moves a plan whose time changed and cancels one whose time was emptied; Remove cancels a single plan. All of this takes effect at once, apart from saving the node, and does not count as an edit of it. Times are entered and shown in the panel's time zone, and a time in the past is refused. A node that is offline has nothing to take offline, so its time to go offline needs an earlier time to go online. The two times may not fall in the same minute, which would leave their order open. Planning needs the permission to change the node's type.

## What a plan does

- **Online from:** the node's working copy, if it has one, is applied and the node is online, as applying and publishing in the editor does. The working copy is taken as it is at that time, so editors can keep writing until then. Edits a user has not saved are not part of it.
- **Offline from:** the node is taken offline. Its content and working copy stay as they are.

A node has at most one pending plan per action. The events a plan writes name the user who planned it, with `{"source": "content-schedule", "planned": …}` as their cause. That user's permission is checked when the plan is made, not when it runs, so the plan of someone who has left still runs.

A plan that finds nothing to do, like bringing online a node that already is and has no draft, is done and says so. A plan that cannot run, because its node is gone or its draft does not validate, fails with the reason and is not tried again; plan it anew once the cause is fixed.

Acting by hand supersedes a plan:

- Bringing the node online drops its plan to go online. So does applying the draft of an online node.
- Taking it offline drops its plan to go offline.
- Deleting it drops both.
- Discarding the draft keeps the plan, so a change can be planned before it is written.

## The overview

The Schedule page lists the upcoming plans by day, and under Failed and Done what ran in the last 30 days, with filters for the action and the type. It shows the plans of the types a user may change. The plugin adds the page to the content rail in a section of its own, Planning, and a card to the dashboard that counts the plans of the next seven days. A site that places the link itself turns the section off:

```php
'content-schedule.nav' => false,
```

```php
$content = $app->section('Content');
$content->link('content-schedule:nav', '/panel/content-schedule')->icon('bi:calendar-event');
```

## Running plans on time

Each plan pushes a job to Cosray's [job queue](https://codefloe.com/cosray/cms/src/branch/main/docs/queue.md), due at the plan's time, on the queue `content-schedule`. How punctual a plan is depends on what runs the queue:

- **`queue:listen`** as a daemon runs it within moments.
- **`queue:work`** from cron runs it within a minute.
- **Without either,** requests run due jobs after their response. A plan then runs with the first request after its time, and that request still gets the old state. On a site with few visitors, or behind a cache, it can run late.

Use cron or the daemon where the time matters, as for an offer that must end. The overview warns when plans are overdue, which means that nothing runs the queue.

## From code

`Cosray\ContentSchedule\Plans` is the service behind the panel. It takes the `Context`, the `Cms` and the node `Types`, as Cosray's own write services do, and checks no permissions:

```php
use Cosray\Actor;
use Cosray\ContentSchedule\Action;
use Cosray\ContentSchedule\Plans;

$plans = new Plans($context, $cms, $types);

$plans->plan($uid, Action::Publish, new DateTimeImmutable('2027-01-01 08:00'), new Actor($userId));
$plans->cancel($uid, Action::Publish);
$plans->of($uid);   // the node's pending plans by action
$plans->run();      // runs what is due, as the job does
```

## Development

The plugin is tested on Cosray's test kit against a database of its own:

```bash
createuser content_schedule_test --pwprompt  # password: content_schedule_test
createdb --owner content_schedule_test content_schedule_test
php run db:migrations --namespace=install --apply
php run db:migrations --apply
composer check
```

`tests/app.php` is the app the tests and `run` boot; `DATABASE_URL` overrides its database. Don't name the database role `content_schedule`: PostgreSQL's default search path looks in a schema named like the role first, which is the plugin's. `php run i18n:status` keeps the catalogs in `lang/` in step with the code; add keys by hand.

## License

MIT, see [LICENSE.md](LICENSE.md).
