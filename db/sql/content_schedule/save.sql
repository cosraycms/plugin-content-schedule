-- Plans an action, or moves the pending plan of that action to the new
-- time and planner. `delay` is the time until it is due by the database's
-- clock, which the queue runs by.
INSERT INTO /*:content-schedule.prefix:*/plans (
	node,
	type,
	action,
	due,
	planner
) VALUES (
	:node,
	:type,
	:action,
	CAST(:due AS timestamptz),
	:planner
)
ON CONFLICT (node, action) WHERE state = 'pending' DO UPDATE SET
	due = EXCLUDED.due,
	planner = EXCLUDED.planner,
	planned = now()

RETURNING
	plan,
	GREATEST(0, ceil(extract(epoch FROM due - now())))::integer AS delay;
