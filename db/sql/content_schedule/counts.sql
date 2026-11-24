-- How many plans each state holds among the given types, finished ones of
-- the last `days` days only, and how many pending ones are overdue: due
-- more than `grace` minutes ago, which means nothing runs the queue.
SELECT
	COUNT(*) FILTER (WHERE state = 'pending') AS pending,
	COUNT(*) FILTER (WHERE state = 'failed' AND ran >= now() - make_interval(days => :days)) AS failed,
	COUNT(*) FILTER (WHERE state = 'done' AND ran >= now() - make_interval(days => :days)) AS done,
	COUNT(*) FILTER (
		WHERE state = 'pending' AND due < now() - make_interval(mins => :grace)
	) AS overdue
FROM
	/*:content-schedule.prefix:*/plans
WHERE
	type IN (SELECT jsonb_array_elements_text(CAST(:types AS jsonb)));
