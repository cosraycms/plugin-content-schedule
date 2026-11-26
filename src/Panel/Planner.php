<?php

declare(strict_types=1);

namespace Cosray\ContentSchedule\Panel;

use Celema\Core\Exception\HttpForbidden;
use Celema\Core\Exception\HttpNotFound;
use Cosray\Cms;
use Cosray\ContentSchedule\Action;
use Cosray\ContentSchedule\Plan;
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
		$types = $this->container->instance(Types::class);
		$plans = new Plans($context, $cms, $types);

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
		$online = (bool) $node->meta->get('published');
		$routable = (bool) $types->get($class, 'routable', false);
		[$kind, $message] = $cancel === null
			? $this->save($plans, $uid, is_array($form['due'] ?? null) ? $form['due'] : [], $online, $routable)
			: $this->remove($plans, $uid, $cancel, $online, $routable);

		return [
			'section' => $sections->section($node),
			'kind' => $kind,
			'message' => $message,
		];
	}

	/**
	 * Nothing is saved unless every time given is a time in the future and
	 * the schedule they make is one a node in its state can follow.
	 *
	 * @param array<array-key, mixed> $due the times by action, as the inputs sent them
	 * @return array{string, ?string} the kind and the message of the toast
	 */
	private function save(Plans $plans, string $uid, array $due, bool $online, bool $routable): array
	{
		$format = Format::of($this->request, $this->config);
		$pending = $plans->of($uid);
		$times = self::times($pending);
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
				if ($plan !== null) {
					$changes[] = [$action, null];
					unset($times[$action->value]);
				}

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
			$times[$action->value] = $at;
		}

		if ($changes === []) {
			return ['success', null];
		}

		$refusal = self::refusal($times, $online, $routable);

		if ($refusal !== null) {
			return ['error', $refusal];
		}

		foreach ($changes as [$action, $at]) {
			if ($at === null) {
				$plans->cancel($uid, $action);

				continue;
			}

			$plans->plan($uid, $action, $at, $this->actor());
		}

		return ['success', __('content-schedule:saved')];
	}

	/** @return array{string, ?string} the kind and the message of the toast */
	private function remove(Plans $plans, string $uid, Action $action, bool $online, bool $routable): array
	{
		$times = self::times($plans->of($uid));
		unset($times[$action->value]);
		$refusal = self::refusal($times, $online, $routable);

		if ($refusal !== null) {
			return ['error', $refusal];
		}

		return ['success', $plans->cancel($uid, $action) ? __('content-schedule:removed') : null];
	}

	/**
	 * Two times in one minute would run in no certain order. A node that is
	 * offline has nothing to take offline until a plan brings it online,
	 * which has to come first.
	 *
	 * @param array<string, DateTimeImmutable> $times the due times by action
	 */
	private static function refusal(array $times, bool $online, bool $routable): ?string
	{
		$publish = $times[Action::Publish->value] ?? null;
		$unpublish = $times[Action::Unpublish->value] ?? null;

		if (
			$publish !== null
			&& $unpublish !== null
			&& intdiv($publish->getTimestamp(), 60) === intdiv($unpublish->getTimestamp(), 60)
		) {
			return Labels::same($routable);
		}

		if ($online || $unpublish === null || $publish !== null && $publish < $unpublish) {
			return null;
		}

		return Labels::alone($routable);
	}

	/**
	 * @param array<string, Plan> $pending
	 * @return array<string, DateTimeImmutable>
	 */
	private static function times(array $pending): array
	{
		return array_map(static fn(Plan $plan): DateTimeImmutable => $plan->due, $pending);
	}
}
