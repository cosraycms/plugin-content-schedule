UPDATE /*:content-schedule.prefix:*/plans
SET
	state = :state,
	ran = now(),
	note = :note
WHERE
	plan = :plan;
