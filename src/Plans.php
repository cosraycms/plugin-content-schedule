<?php

declare(strict_types=1);

namespace Cosray\ContentSchedule;

use Celema\Core\Exception\HttpError;
use Celema\Quma\Database;
use Cosray\Actor;
use Cosray\Cms;
use Cosray\Context;
use Cosray\Exception\RuntimeException;
use Cosray\Node\Types;
use Cosray\Node\Wrapper;
use Cosray\Node\Write;
use Cosray\Node\Writer;
use Cosray\Queue;
use DateTimeImmutable;
use DateTimeInterface;
use Throwable;

/**
 * The planned actions of nodes: plans them, cancels them and runs the ones
 * that are due.
 *
 *     $plans->plan($uid, Action::Publish, new DateTimeImmutable('2027-01-01 08:00'), $actor);
 *     $plans->cancel($uid, Action::Publish);
 *
 * It checks no permissions: each caller checks them for its user.
 *
 * @api
 */
final class Plans
{
	/** The source a plan names as the cause of the writes it makes. */
	public const string SOURCE = 'content-schedule';

	/** The queue the wake-up jobs are pushed to. */
	public const string QUEUE = 'content-schedule';

	private const int MAX_NOTE_LENGTH = 500;

	private readonly Database $db;
	private readonly Queue $queue;
	private ?Writer $writer = null;

	public function __construct(
		private readonly Context $context,
		private readonly Cms $cms,
		private readonly Types $types,
	) {
		$this->db = $context->db;
		$this->queue = new Queue($context->db);
	}

	/**
	 * Whether nodes of the class can be planned: it carries `#[Schedule]`.
	 *
	 * @param class-string $class
	 */
	public function schedules(string $class): bool
	{
		return (bool) $this->types->get($class, 'schedule', false);
	}

	/**
	 * The handles of the scheduled types among the registered ones.
	 *
	 * @param array<string, class-string> $registered node classes by handle
	 * @return list<string>
	 */
	public function scheduled(array $registered): array
	{
		return array_keys(array_filter($registered, $this->schedules(...)));
	}

	/**
	 * Plans the action for the time. A node has one pending plan per
	 * action: planning it again moves the time and makes the actor its
	 * planner. The plan and the job that wakes the runner are written
	 * together.
	 */
	public function plan(string $uid, Action $action, DateTimeInterface $due, Actor $actor): void
	{
		$node = $this->cms->node->byUid($uid, published: null)
			?? throw new RuntimeException("There is no node with the uid '{$uid}'");
		$class = Wrapper::unwrap($node)::class;

		if (!$this->schedules($class)) {
			throw new RuntimeException("Nodes of {$class} are not scheduled; add #[Schedule] to the class");
		}

		if ($due->getTimestamp() <= time()) {
			throw new RuntimeException('A plan is due in the future');
		}

		$this->db->transaction(function () use ($uid, $class, $action, $due, $actor): void {
			$row = $this->db->content_schedule->save([
				'node' => $uid,
				'type' => (string) $this->types->get($class, 'handle'),
				'action' => $action->value,
				'due' => $due->format(DATE_ATOM),
				'planner' => $actor->id,
			])->one();

			$this->queue->push(RunDue::class, delay: (int) $row['delay'], queue: self::QUEUE);
		});
	}

	/** Drops the node's pending plan of the action; returns whether there was one. */
	public function cancel(string $uid, Action $action): bool
	{
		return $this->db->content_schedule->remove(['node' => $uid, 'action' => $action->value])->first() !== null;
	}

	/**
	 * The node's pending plans by the value of their action.
	 *
	 * @return array<string, Plan>
	 */
	public function of(string $uid): array
	{
		$plans = [];

		foreach ($this->db->content_schedule->pending(['node' => $uid])->all() as $row) {
			$plan = Plan::of($row);
			$plans[$plan->action->value] = $plan;
		}

		return $plans;
	}

	/**
	 * A page of the plans in a state among the given types: pending ones by
	 * the time they are due, finished ones of the last `$days` days, the
	 * latest first.
	 *
	 * @param list<string> $types type handles
	 * @return array{plans: list<Plan>, total: int}
	 */
	public function list(
		string $state,
		array $types,
		?Action $action = null,
		int $limit = 50,
		int $offset = 0,
		int $days = 30,
	): array {
		$params = [
			'state' => $state,
			'types' => json_encode($types, JSON_THROW_ON_ERROR),
			'limit' => $limit,
			'offset' => $offset,
		];

		if ($action !== null) {
			$params['action'] = $action->value;
		}

		if ($state !== Plan::PENDING) {
			$params['days'] = $days;
		}

		$rows = $this->db->content_schedule->list($params)->all();

		return [
			'plans' => array_map(Plan::of(...), $rows),
			'total' => (int) ($rows[0]['total'] ?? 0),
		];
	}

	/**
	 * How many plans each state holds among the given types, finished ones
	 * of the last `$days` days, and how many pending ones are overdue by
	 * more than `$grace` minutes: a sign that nothing runs the job queue.
	 *
	 * @param list<string> $types type handles
	 * @return array{pending: int, failed: int, done: int, overdue: int}
	 */
	public function counts(array $types, int $days = 30, int $grace = 10): array
	{
		$row = $this->db->content_schedule->counts([
			'types' => json_encode($types, JSON_THROW_ON_ERROR),
			'days' => $days,
			'grace' => $grace,
		])->one();

		return [
			'pending' => (int) $row['pending'],
			'failed' => (int) $row['failed'],
			'done' => (int) $row['done'],
			'overdue' => (int) $row['overdue'],
		];
	}

	/**
	 * Runs the plans that are due, each in a transaction of its own, and
	 * returns how many it ran. A plan that cannot run, like one of a locked
	 * node, fails with a note and is not tried again.
	 *
	 * @return array{done: int, failed: int}
	 */
	public function run(int $limit = 100): array
	{
		$done = 0;
		$failed = 0;

		while ($limit-- > 0) {
			$state = $this->db->transaction($this->next(...));

			if ($state === null) {
				break;
			}

			$state === Plan::DONE ? $done++ : $failed++;
		}

		return ['done' => $done, 'failed' => $failed];
	}

	/**
	 * Drops the pending plans a write by hand made pointless: one to
	 * publish once the node went online or, online, had its working copy
	 * applied; one to unpublish once it went offline; both once it is
	 * deleted. A discarded working copy keeps the plan, so a change can be
	 * planned before it is written.
	 */
	public function supersede(Write $write): void
	{
		if (!$write->has('published', 'applied', 'unpublished', 'deleted')) {
			return;
		}

		$pending = $this->of($write->uid);

		if ($pending === []) {
			return;
		}

		$publish = $write->has('published', 'deleted') || $write->has('applied') && $this->online($write->uid);

		if ($publish && isset($pending[Action::Publish->value])) {
			$this->cancel($write->uid, Action::Publish);
		}

		if ($write->has('unpublished', 'deleted') && isset($pending[Action::Unpublish->value])) {
			$this->cancel($write->uid, Action::Unpublish);
		}
	}

	/** Runs the next due plan and returns the state it ended in, null when none is due. */
	private function next(): ?string
	{
		$row = $this->db->content_schedule->claim()->first();

		if ($row === null) {
			return null;
		}

		$plan = (int) $row['plan'];
		// Finished before the write, so the events of the write supersede nothing.
		$this->finish($plan, Plan::DONE);

		try {
			$changed = $this->act(
				(string) $row['node'],
				Action::from((string) $row['action']),
				new Actor((int) $row['planner']),
				new DateTimeImmutable((string) $row['planned']),
			);
		} catch (Throwable $e) {
			$this->finish($plan, Plan::FAILED, self::reason($e));

			return Plan::FAILED;
		}

		if (!$changed) {
			$this->finish($plan, Plan::DONE, Plan::UNCHANGED);
		}

		return Plan::DONE;
	}

	private function act(string $uid, Action $action, Actor $planner, DateTimeImmutable $planned): bool
	{
		$this->writer ??= new Writer($this->context, $this->cms, $this->types);
		$cause = ['source' => self::SOURCE, 'planned' => $planned->format(DATE_ATOM)];

		return match ($action) {
			Action::Publish => $this->writer->publish($uid, $planner, $cause),
			Action::Unpublish => $this->writer->unpublish($uid, $planner, $cause),
		};
	}

	private function finish(int $plan, string $state, ?string $note = null): void
	{
		$this->db->content_schedule->finish(['plan' => $plan, 'state' => $state, 'note' => $note])->run();
	}

	private function online(string $uid): bool
	{
		return (bool) $this->cms->node->byUid($uid, published: null)?->meta->get('published');
	}

	/** What an editor reads as the reason: the message of a refusal, without its status. */
	private static function reason(Throwable $e): string
	{
		$payload = $e instanceof HttpError ? $e->payload() : null;
		$message = is_array($payload) && is_string($payload['message'] ?? null)
			? $payload['message']
			: $e->getMessage();

		return mb_substr($message, 0, self::MAX_NOTE_LENGTH);
	}
}
