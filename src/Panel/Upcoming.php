<?php

declare(strict_types=1);

namespace Cosray\ContentSchedule\Panel;

use Celema\Quma\Database;
use Cosray\Config;
use Cosray\Context;
use Cosray\Contract\DashboardCard;
use Cosray\Panel\Dashboard\Card;
use DateTimeImmutable;
use Override;

/** The dashboard's count of what is planned for the coming week, with the next time. */
final readonly class Upcoming implements DashboardCard
{
	private const int DAYS = 7;

	public function __construct(
		private Database $db,
		private Context $context,
		private Config $config,
	) {}

	#[Override]
	public function card(): Card
	{
		$row = $this->db->content_schedule->upcoming(['days' => self::DAYS])->one();
		$next = isset($row['next']) ? new DateTimeImmutable((string) $row['next']) : null;

		return new Card(
			label: __('content-schedule:card'),
			value: (int) $row['total'],
			note: $next === null
				? __('content-schedule:card-none')
				: __('content-schedule:card-next', [
					'date' => Format::of($this->context->request, $this->config)->dateTime($next),
				]),
			url: rtrim($this->config->panel->path, '/') . '/content-schedule',
		);
	}
}
