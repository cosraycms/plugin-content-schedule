<?php

declare(strict_types=1);

namespace Cosray\ContentSchedule;

use Cosray\ContentSchedule\Panel\Planner;
use Cosray\ContentSchedule\Panel\ScheduleSection;
use Cosray\Plugin\PanelRoutes;
use Cosray\Plugin\Registrar;
use Override;

/**
 * Scheduled publishing: editors plan in a node's editor when it goes
 * online, goes offline or its draft goes live, for the types that carry
 * `#[Schedule]`.
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
		$cms->translations("{$root}/lang");
		$cms->templates("{$root}/views");
		$cms->assets("{$root}/assets");
		$cms->css('schedule.css');
		$cms->nodeListener(Superseded::class);
		$cms->inspectorSection(ScheduleSection::class);

		$cms->panel(static function (PanelRoutes $panel): void {
			$panel
				->post('/nodes/{uid:[A-Za-z0-9-_.]{1,64}}', [Planner::class, 'plan'], 'plan')
				->render(
					'content-schedule:planned',
				);
		});
	}
}
