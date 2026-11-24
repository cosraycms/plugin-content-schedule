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
 * The schedule of a node in the editor's inspector: a time for each action
 * that fits the node's state, with the pending plans filled in.
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
		$online = (bool) $node->meta->get('published');
		$routable = (bool) $this->types->get($class, 'routable', false);
		$drafts = (bool) $this->types->get($class, 'drafts', true);
		$fields = [];

		// Online, a node without drafts has nothing a plan could bring live.
		if (!$online || $drafts || isset($pending[Action::Publish->value])) {
			$fields[] = $this->field(Action::Publish, $online, $routable, $pending, $format);
		}

		// Going offline needs a node that is online, or planned to be.
		if ($online || $pending !== []) {
			$fields[] = $this->field(Action::Unpublish, $online, $routable, $pending, $format);
		}

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
	private function field(Action $action, bool $online, bool $routable, array $pending, Format $format): array
	{
		$plan = $pending[$action->value] ?? null;
		[$label, $help] = Labels::field($action, $online, $routable);

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
