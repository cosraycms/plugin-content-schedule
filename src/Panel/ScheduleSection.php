<?php

declare(strict_types=1);

namespace Cosray\ContentSchedule\Panel;

use Cosray\Cms;
use Cosray\Config;
use Cosray\ContentSchedule\Action;
use Cosray\ContentSchedule\Plans;
use Cosray\Context;
use Cosray\Contract\InspectorSection;
use Cosray\Node\Types;
use Cosray\Node\Wrapper;
use Cosray\Panel\Inspector\Section;
use DateTimeImmutable;
use Override;

/**
 * The schedule of a node in the editor's inspector: a time to go online and
 * one to go offline, with the pending plans filled in.
 */
final class ScheduleSection implements InspectorSection
{
	private readonly Plans $plans;

	public function __construct(
		private readonly Context $context,
		Cms $cms,
		private readonly Types $types,
		private readonly Config $config,
	) {
		$this->plans = new Plans($context, $cms, $types);
	}

	#[Override]
	public function section(Wrapper $node): ?Section
	{
		$class = Wrapper::unwrap($node)::class;

		if (!$this->plans->schedules($class)) {
			return null;
		}

		$uid = $node->meta->uid;
		$format = Format::of($this->context->request, $this->config);
		$pending = $this->plans->of($uid);
		$routable = (bool) $this->types->get($class, 'routable', false);
		$fields = array_map(
			fn(Action $action): array => $this->field($action, $routable, $pending, $format),
			Action::cases(),
		);

		return new Section(
			'content-schedule',
			__('content-schedule:heading'),
			'content-schedule:section',
			['fields' => $fields, 'min' => $format->input(new DateTimeImmutable())],
			rtrim($this->config->panel->path, '/') . "/content-schedule/nodes/{$uid}",
		);
	}

	/**
	 * @param array<string, \Cosray\ContentSchedule\Plan> $pending
	 * @return array{action: string, label: string, help: string, value: string, planned: ?string}
	 */
	private function field(Action $action, bool $routable, array $pending, Format $format): array
	{
		$plan = $pending[$action->value] ?? null;
		[$label, $help] = Labels::field($action, $routable);

		return [
			'action' => $action->value,
			'label' => $label,
			'help' => $help,
			'value' => $plan === null ? '' : $format->input($plan->due),
			'planned' => $plan === null
				? null
				: __('content-schedule:planned-by', [
					'name' => $plan->plannerName,
					'date' => $format->dateTime($plan->planned),
				]),
		];
	}
}
