<?php

declare(strict_types=1);

namespace Cosray\ContentSchedule\Tests;

use Celema\Quma\Database;
use Cosray\App;
use Cosray\Cms;
use Cosray\ContentSchedule\Action;
use Cosray\ContentSchedule\Plans;
use Cosray\ContentSchedule\Tests\Fixtures\Article;
use Cosray\Context;
use Cosray\Node\Types;
use Cosray\Node\Wrapper;
use Cosray\Node\Writer;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface as Response;

/** Boots the test app with the plugin; see tests/app.php. */
abstract class TestCase extends \Cosray\Testing\TestCase
{
	protected function createApp(): App
	{
		return (require __DIR__ . '/app.php')();
	}

	/** The plans as a job or a command gets them. */
	protected function plans(): Plans
	{
		return new Plans($this->service(Context::class), $this->service(Cms::class), $this->service(Types::class));
	}

	/**
	 * A node with a title, published or not.
	 *
	 * @param class-string $class
	 */
	protected function node(string $uid, string $title, bool $published = false, string $class = Article::class): void
	{
		$writer = $this->service(Writer::class);
		$prepared = $writer->prepare($class, ['title' => $title])->uid($uid);

		$writer->create($published ? $prepared->published() : $prepared);
	}

	/** Saves a new title as the signed-in editor does: into the working copy of a type with drafts. */
	protected function edit(string $uid, string $title): Response
	{
		return $this->post(
			"/panel/node/{$uid}",
			['content' => ['title' => ['value' => ['zxx' => $title]]], '_complete' => '1'],
			['HX-Request' => 'true'],
		);
	}

	/** The node's main version, whatever its publication. */
	protected function stored(string $uid): ?Wrapper
	{
		return $this->service(Cms::class)->node->byUid($uid, published: null);
	}

	/** The time in a number of hours. */
	protected function in(int $hours): DateTimeImmutable
	{
		return new DateTimeImmutable("+{$hours} hours");
	}

	/** Makes the node's pending plan of the action due, as if its time had come. */
	protected function due(string $uid, Action $action): void
	{
		$this
			->service(Database::class)
			->execute(
				"UPDATE content_schedule.plans SET due = now() - interval '1 minute'
			WHERE node = :node AND action = :action AND state = 'pending'",
				['node' => $uid, 'action' => $action->value],
			)
			->run();
	}

	/**
	 * The node's plans, finished ones too, in the order they were planned.
	 *
	 * @return list<array<string, mixed>>
	 */
	protected function rows(string $uid): array
	{
		return $this
			->service(Database::class)
			->execute(
				'SELECT action, state, note, planner FROM content_schedule.plans WHERE node = :node ORDER BY plan',
				['node' => $uid],
			)
			->all();
	}

	/**
	 * What was done to the node, with who did it and why.
	 *
	 * @return list<array{string, int, array<string, mixed>}>
	 */
	protected function events(string $uid): array
	{
		return array_map(
			static fn(array $row): array => [
				(string) $row['action'],
				(int) $row['actor'],
				(array) json_decode((string) $row['data'], true),
			],
			$this
				->service(Database::class)
				->execute(
					'SELECT e.action, e.actor, e.data FROM cms.events e JOIN cms.nodes n ON n.node = e.node
				WHERE n.uid = :uid ORDER BY e.event',
					['uid' => $uid],
				)
				->all(),
		);
	}

	protected function html(Response $response): string
	{
		return (string) $response->getBody();
	}
}
