DELETE FROM /*:content-schedule.prefix:*/plans
WHERE
	node = :node
	AND action = :action
	AND state = 'pending'

RETURNING plan;
