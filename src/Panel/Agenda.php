<?php

declare(strict_types=1);

namespace Cosray\ContentSchedule\Panel;

use Cosray\Cms;
use Cosray\ContentSchedule\Action;
use Cosray\ContentSchedule\Plan;
use Cosray\ContentSchedule\Plans;
use Cosray\Context;
use Cosray\Controller\Panel\Panel;
use Cosray\Node\Types;
use Cosray\Panel\NodeUrls;
use Cosray\Security\NodeAccess;
use DateTimeImmutable;

/**
 * The overview of the schedule: what is planned, by day, and what failed
 * or ran lately, among the nodes the user may change.
 */
final class Agenda extends Panel
{
	private const int PAGE_SIZE = 50;

	/** How far back finished plans are listed and counted. */
	private const int DAYS = 30;

	private const array STATES = [Plan::PENDING, Plan::FAILED, Plan::DONE];

	/** @return array<string, mixed> */
	public function index(Context $context, Cms $cms): array
	{
		$plans = $this->plans($context, $cms);
		$types = $this->types($plans);
		$state = $this->choice('state', self::STATES) ?? Plan::PENDING;
		$action = Action::tryFrom($this->choice('action', array_column(Action::cases(), 'value')) ?? '');
		$type = $this->choice('type', array_keys($types));
		$offset = max(0, (int) $this->request->param('offset', 0));
		$counts = $plans->counts(array_keys($types), self::DAYS);
		$found = $plans->list(
			$state,
			$type === null ? array_keys($types) : [$type],
			$action,
			self::PAGE_SIZE,
			$offset,
			self::DAYS,
		);
		$filter = ['state' => $state === Plan::PENDING ? null : $state, 'action' => $action?->value, 'type' => $type];
		$link = fn(array $change): string => $this->url('', array_filter(
			[...$filter, ...$change],
			static fn(mixed $value): bool => $value !== null && $value !== 0,
		));
		$total = $found['total'];

		return $this->context([
			'days' => $this->days($cms, $found['plans'], $types),
			'state' => $state,
			'none' => Labels::none($state),
			'pending' => $counts['pending'],
			'overdue' => $counts['overdue'],
			// Named apart from what the kit's partials read: `actions` are the head's buttons.
			'stateLinks' => array_map(static fn(string $value): array => [
				'label' => Labels::state($value),
				'count' => $counts[$value],
				'url' => $link(['state' => $value === Plan::PENDING ? null : $value, 'offset' => null]),
				'active' => $value === $state,
			], self::STATES),
			'actionLinks' => array_map(static fn(?Action $value): array => [
				'label' => Labels::action($value),
				'url' => $link(['action' => $value?->value, 'offset' => null]),
				'active' => $value === $action,
			], [null, ...Action::cases()]),
			'typeLinks' => count($types) > 1
				? array_map(static fn(?string $value): array => [
					'label' => $value === null ? __('content-schedule:type-all') : $types[$value]['label'],
					'url' => $link(['type' => $value, 'offset' => null]),
					'active' => $value === $type,
				], [null, ...array_keys($types)])
				: [],
			'total' => $total,
			'rangeStart' => $total === 0 ? 0 : $offset + 1,
			'rangeEnd' => $offset + count($found['plans']),
			'previousUrl' => $offset > 0 ? $link(['offset' => max(0, $offset - self::PAGE_SIZE)]) : null,
			'nextUrl' => ($offset + self::PAGE_SIZE) < $total ? $link(['offset' => $offset + self::PAGE_SIZE]) : null,
		]);
	}

	/**
	 * The plans as the overview lists them, grouped by the day they are due
	 * or ran on, with the title and the editor's link of their node.
	 *
	 * @param list<Plan> $plans
	 * @param array<string, array{label: string, routable: bool}> $types
	 * @return list<array{label: string, plans: list<array<string, mixed>>}>
	 */
	private function days(Cms $cms, array $plans, array $types): array
	{
		$format = Format::of($this->request, $this->config);
		$links = new NodeUrls($this->panelPath());
		$titles = [];

		if ($plans !== []) {
			$uids = array_values(array_unique(array_column($plans, 'node')));

			foreach ($cms->nodes->published(null)->hidden(null)->only(...$uids) as $node) {
				$titles[$node->meta->uid] = $node->label();
			}
		}

		$days = [];
		$now = new DateTimeImmutable();

		foreach ($plans as $plan) {
			$at = $plan->ran ?? $plan->due;
			$pending = $plan->state === Plan::PENDING;
			$day = $format->date($at);
			$days[$day] ??= ['label' => $format->day($at), 'plans' => []];
			$days[$day]['plans'][] = [
				'time' => $format->time($at),
				'due' => $pending ? null : $format->dateTime($plan->due),
				'action' => $plan->action->value,
				'actionLabel' => $pending
					? Labels::pending($plan->action, $types[$plan->type]['routable'] ?? true)
					: Labels::action($plan->action),
				'state' => $plan->state,
				'overdue' => $pending && $plan->due < $now,
				'title' => $titles[$plan->node] ?? null,
				'url' => isset($titles[$plan->node]) ? $links->edit($plan->node) : null,
				'type' => $types[$plan->type]['label'] ?? $plan->type,
				'planner' => $plan->plannerName,
				'note' => match (true) {
					$plan->note === null => null,
					$plan->note === Plan::UNCHANGED => __('content-schedule:note-unchanged'),
					default => $plan->note,
				},
			];
		}

		return array_values($days);
	}

	/**
	 * The scheduled types whose nodes the user may change, by handle: their
	 * label and whether they have a route, which words their plans.
	 *
	 * @return array<string, array{label: string, routable: bool}>
	 */
	private function types(Plans $plans): array
	{
		$access = $this->access();
		$registered = $access->registered();
		$types = $this->container->instance(Types::class);
		$found = [];

		foreach ($plans->scheduled($registered) as $handle) {
			$class = $registered[$handle];

			if ($this->permits($access->permission($class, NodeAccess::CHANGE))) {
				$found[$handle] = [
					// A label may be a message id of the site, as the panel's other type labels are.
					'label' => __((string) $types->get($class, 'label', $handle)),
					'routable' => (bool) $types->get($class, 'routable', false),
				];
			}
		}

		return $found;
	}

	/** @param list<string> $values */
	private function choice(string $name, array $values): ?string
	{
		$value = $this->request->param($name, '');

		return is_string($value) && in_array($value, $values, true) ? $value : null;
	}

	private function plans(Context $context, Cms $cms): Plans
	{
		return new Plans($context, $cms, $this->container->instance(Types::class));
	}

	private function access(): NodeAccess
	{
		return $this->container->instance(NodeAccess::class);
	}
}
