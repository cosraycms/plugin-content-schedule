<?php

declare(strict_types=1);

namespace Cosray\ContentSchedule\Panel;

use Celema\Core\Exception\HttpForbidden;
use Celema\Core\Exception\HttpNotFound;
use Cosray\Cms;
use Cosray\ContentSchedule\Action;
use Cosray\ContentSchedule\Plans;
use Cosray\Context;
use Cosray\Controller\Panel\Panel;
use Cosray\Node\Types;
use Cosray\Node\Wrapper;
use Cosray\Security\NodeAccess;
use DateTimeImmutable;

/**
 * Where the editor's schedule section posts to. A user plans for the nodes
 * they may change.
 */
final class Planner extends Panel
{
	/**
	 * Saves the schedule the editor's section sends: a time plans its
	 * action or moves the plan, an empty one cancels it. `do` names a
	 * single action to cancel instead.
	 *
	 * @return array<string, mixed>
	 */
	public function plan(Context $context, Cms $cms, ScheduleSection $sections, string $uid): array
	{
		$node = $cms->node->working($uid) ?? throw new HttpNotFound($this->request);
		$class = Wrapper::unwrap($node)::class;
		$plans = new Plans($context, $cms, $this->container->instance(Types::class));

		if (!$plans->schedules($class)) {
			throw new HttpNotFound($this->request);
		}

		$access = $this->container->instance(NodeAccess::class);

		if (!$this->permits($access->permission($class, NodeAccess::CHANGE))) {
			throw new HttpForbidden($this->request);
		}

		$form = $this->formData();
		$cancel = Action::tryFrom(
			str_starts_with((string) ($form['do'] ?? ''), 'cancel:')
				? substr((string) $form['do'], 7)
				: '',
		);
		[$kind, $message] = $cancel === null
			? $this->save($plans, $uid, is_array($form['due'] ?? null) ? $form['due'] : [])
			: ['success', $plans->cancel($uid, $cancel) ? __('content-schedule:removed') : null];

		return [
			'section' => $sections->section($node),
			'kind' => $kind,
			'message' => $message,
		];
	}

	/**
	 * Nothing is saved unless every time given is a time in the future.
	 *
	 * @param array<array-key, mixed> $due the times by action, as the inputs sent them
	 * @return array{string, ?string} the kind and the message of the toast
	 */
	private function save(Plans $plans, string $uid, array $due): array
	{
		$format = Format::of($this->request, $this->config);
		$pending = $plans->of($uid);
		$now = new DateTimeImmutable();
		$changes = [];

		foreach (Action::cases() as $action) {
			$value = $due[$action->value] ?? null;

			// A field the section did not show changes nothing.
			if (!is_string($value)) {
				continue;
			}

			$plan = $pending[$action->value] ?? null;

			if (trim($value) === '') {
				$changes[] = [$action, null];

				continue;
			}

			$at = $format->parse($value);

			if ($at === null) {
				return ['error', __('content-schedule:invalid-time')];
			}

			// A pending plan sent back as it is stays as it is, the minute its input shows included.
			if ($plan !== null && $format->input($plan->due) === $format->input($at)) {
				continue;
			}

			if ($at <= $now) {
				return ['error', __('content-schedule:past-time')];
			}

			$changes[] = [$action, $at];
		}

		$changed = false;

		foreach ($changes as [$action, $at]) {
			if ($at === null) {
				$changed = $plans->cancel($uid, $action) || $changed;

				continue;
			}

			$plans->plan($uid, $action, $at, $this->actor());
			$changed = true;
		}

		return ['success', $changed ? __('content-schedule:saved') : null];
	}
}
