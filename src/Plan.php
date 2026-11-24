<?php

declare(strict_types=1);

namespace Cosray\ContentSchedule;

use DateTimeImmutable;

/**
 * An action planned for a node. Pending until it is due; then done, or
 * failed with a note saying why.
 */
final readonly class Plan
{
	public const string PENDING = 'pending';
	public const string DONE = 'done';
	public const string FAILED = 'failed';

	/** The note of a plan that found nothing to do, like publishing a node that was online without a draft. */
	public const string UNCHANGED = 'unchanged';

	/**
	 * @param string $node the node's uid
	 * @param string $type the handle of the node's type
	 * @param int $planner the user who planned it
	 * @param ?DateTimeImmutable $ran when it ran
	 * @param ?string $note why it failed, or UNCHANGED
	 */
	public function __construct(
		public int $id,
		public string $node,
		public string $type,
		public Action $action,
		public DateTimeImmutable $due,
		public int $planner,
		public string $plannerName,
		public DateTimeImmutable $planned,
		public string $state = self::PENDING,
		public ?DateTimeImmutable $ran = null,
		public ?string $note = null,
	) {}

	/** @param array<string, mixed> $row */
	public static function of(array $row): self
	{
		return new self(
			(int) $row['plan'],
			(string) $row['node'],
			(string) $row['type'],
			Action::from((string) $row['action']),
			new DateTimeImmutable((string) $row['due']),
			(int) $row['planner'],
			(string) ($row['planner_name'] ?? ''),
			new DateTimeImmutable((string) $row['planned']),
			(string) $row['state'],
			isset($row['ran']) ? new DateTimeImmutable((string) $row['ran']) : null,
			isset($row['note']) ? (string) $row['note'] : null,
		);
	}
}
