<?php

declare(strict_types=1);

namespace Cosray\ContentSchedule;

use Cosray\Plugin\Registrar;
use Override;

/**
 * Scheduled publishing: plans for when a node goes online, goes offline or
 * its draft goes live, for the types that carry `#[Schedule]`.
 */
final class Plugin implements \Cosray\Plugin\Plugin
{
	#[Override]
	public function id(): string
	{
		return 'content-schedule';
	}

	#[Override]
	public function register(Registrar $cms): void
	{
		$root = dirname(__DIR__);
		$cms->nodeSchema(Schedule::class, new ScheduleHandler());
		$cms->migrations("{$root}/db/migrations");
		$cms->sql("{$root}/db/sql");
		$cms->nodeListener(Superseded::class);
	}
}
