-- The next due plan, held for the transaction that runs it. A plan another
-- runner holds is skipped, so two runners never run the same one.
SELECT
	plan,
	node,
	action,
	planner,
	planned
FROM
	/*:content-schedule.prefix:*/plans
WHERE
	state = 'pending'
	AND due <= now()
ORDER BY
	due,
	plan
LIMIT 1
FOR UPDATE SKIP LOCKED;
