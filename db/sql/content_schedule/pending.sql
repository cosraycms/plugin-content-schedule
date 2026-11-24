SELECT
	p.plan,
	p.node,
	p.type,
	p.action,
	p.due,
	p.planner,
	COALESCE(u.data->>'name', u.username, u.email) AS planner_name,
	p.planned,
	p.state,
	p.ran,
	p.note
FROM
	/*:content-schedule.prefix:*/plans p
	INNER JOIN /*:cms.prefix:*/users u ON u.usr = p.planner
WHERE
	p.node = :node
	AND p.state = 'pending'
ORDER BY
	p.due,
	p.plan;
