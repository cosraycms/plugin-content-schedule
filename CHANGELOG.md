# Changelog

## [Unreleased]

Initial release.

- `#[Schedule]` attribute that lets editors plan when the nodes of a type go online and offline. A `#[Record]` has no publication and cannot carry it.
- Schedule section in the status tab of the node editor with two times, Online from and Offline from, or Active from and Inactive from for a type without a route. Going online applies the working copy as it is at that time, so the same time plans an update of a node that is online. Saving the schedule takes effect at once, apart from saving the node.
- Validation that refuses a time in the past, both times in the same minute and, for a node that is offline, a time to go offline without an earlier time to go online. A refused schedule keeps the times the editor entered.
- Permission checks: planning needs the permission to change the node's type, and the overview lists only the plans of the types the user may change.
- Plans that run through Cosray's job queue on the queue `content-schedule`, as the user who planned them and with `{"source": "content-schedule", "planned": …}` as the cause of the events they write. A plan with nothing to do is done and says so; one that cannot run fails with its reason and is not tried again.
- Plans that acting by hand supersedes: going online, or applying the draft of a node that is online, drops the plan to go online; going offline drops the plan to go offline; deleting the node drops both.
- Schedule page in a Planning section of the content rail that lists upcoming plans by day and the plans that failed or ran in the last 30 days, filtered by action and type, and warns when plans are overdue because nothing runs the queue. `'content-schedule.nav' => false` turns the section off for a site that places the link itself.
- Dashboard card that counts the plans of the next seven days.
- `Plans` service that plans, cancels, lists and runs plans from code without permission checks.
- Migration for the `plans` table in the database namespace `content_schedule`, which `'content-schedule.prefix'` in `db.placeholders` changes.
- Translations for English, German, Spanish, French, Italian, Dutch, Polish and Portuguese.
