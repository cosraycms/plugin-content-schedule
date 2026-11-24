-- Planned actions on nodes: at `due` the node goes online, with its working
-- copy if it has one, or offline. A plan stays as a record once it ran; a
-- cancelled one is deleted. `type` is the handle of the node's type, kept
-- here so the overview filters without reading Cosray's tables.
CREATE TABLE /*:content-schedule.prefix:*/plans (
	plan bigint GENERATED ALWAYS AS IDENTITY,
	node text NOT NULL,
	type text NOT NULL,
	action text NOT NULL,
	due timestamp with time zone NOT NULL,
	planner bigint NOT NULL,
	planned timestamp with time zone NOT NULL DEFAULT now(),
	state text NOT NULL DEFAULT 'pending',
	ran timestamp with time zone,
	note text,
	CONSTRAINT /*:content-schedule.obj:*/pk_plans PRIMARY KEY (plan),
	CONSTRAINT /*:content-schedule.obj:*/fk_plans_nodes FOREIGN KEY (node)
		REFERENCES /*:cms.prefix:*/nodes (uid) ON DELETE CASCADE,
	CONSTRAINT /*:content-schedule.obj:*/fk_plans_users FOREIGN KEY (planner)
		REFERENCES /*:cms.prefix:*/users (usr),
	CONSTRAINT /*:content-schedule.obj:*/ck_plans_action CHECK (action IN ('publish', 'unpublish')),
	CONSTRAINT /*:content-schedule.obj:*/ck_plans_state CHECK (state IN ('pending', 'done', 'failed'))
);
-- A node has at most one pending plan per action.
CREATE UNIQUE INDEX /*:content-schedule.obj:*/ux_plans_pending
	ON /*:content-schedule.prefix:*/plans (node, action) WHERE state = 'pending';
CREATE INDEX /*:content-schedule.obj:*/ix_plans_due
	ON /*:content-schedule.prefix:*/plans (due) WHERE state = 'pending';
CREATE INDEX /*:content-schedule.obj:*/ix_plans_ran
	ON /*:content-schedule.prefix:*/plans (ran) WHERE state <> 'pending';
