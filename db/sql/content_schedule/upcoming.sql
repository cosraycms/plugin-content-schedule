-- The pending plans of the coming days, for the dashboard: how many, and
-- when the next one is due.
SELECT
	COUNT(*) AS total,
	MIN(due) AS next
FROM
	/*:content-schedule.prefix:*/plans
WHERE
	state = 'pending'
	AND due < now() + make_interval(days => :days);
