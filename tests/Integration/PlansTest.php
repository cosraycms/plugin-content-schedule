<?php

declare(strict_types=1);

namespace Cosray\ContentSchedule\Tests\Integration;

use Cosray\Actor;
use Cosray\ContentSchedule\Action;
use Cosray\ContentSchedule\Plan;
use Cosray\ContentSchedule\Plans;
use Cosray\ContentSchedule\RunDue;
use Cosray\ContentSchedule\Tests\Fixtures\Note;
use Cosray\ContentSchedule\Tests\TestCase;
use Cosray\Exception\RuntimeException;
use Cosray\Queue;

/**
 * @internal
 *
 * @coversNothing
 */
final class PlansTest extends TestCase
{
	public function testAPlanIsKeptWithAJobThatWakesTheRunner(): void
	{
		$this->node('planned', 'Planned');
		$planner = new Actor($this->user(roles: ['editor'])->id);
		$due = $this->in(48);

		$this->plans()->plan('planned', Action::Publish, $due, $planner);

		$plan = $this->plans()->of('planned')[Action::Publish->value];
		$this->assertSame($due->getTimestamp(), $plan->due->getTimestamp());
		$this->assertSame('article', $plan->type);
		$this->assertSame($planner->id, $plan->planner);
		$this->assertSame(Plan::PENDING, $plan->state);
		$this->assertCount(1, $this->service(Queue::class)->pending(RunDue::class));
	}

	public function testPlanningAgainMovesThePlanAndNamesTheNewPlanner(): void
	{
		$this->node('moved', 'Moved');
		$first = new Actor($this->user(roles: ['editor'])->id);
		$second = new Actor($this->user(roles: ['editor'])->id);

		$this->plans()->plan('moved', Action::Publish, $this->in(48), $first);
		$this->plans()->plan('moved', Action::Publish, $this->in(24), $second);
		$this->plans()->plan('moved', Action::Unpublish, $this->in(72), $first);

		$plans = $this->plans()->of('moved');
		$this->assertCount(2, $plans);
		$this->assertSame($second->id, $plans[Action::Publish->value]->planner);
		$this->assertEqualsWithDelta(
			$this->in(24)->getTimestamp(),
			$plans[Action::Publish->value]->due->getTimestamp(),
			5,
		);
	}

	public function testACancelledPlanIsGone(): void
	{
		$this->node('cancelled', 'Cancelled');
		$this->plans()->plan('cancelled', Action::Publish, $this->in(1), Actor::system());

		$this->assertTrue($this->plans()->cancel('cancelled', Action::Publish));
		$this->assertFalse($this->plans()->cancel('cancelled', Action::Publish));
		$this->assertSame([], $this->plans()->of('cancelled'));
	}

	public function testAPlanNeedsATimeInTheFuture(): void
	{
		$this->node('late', 'Late');

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('A plan is due in the future');

		$this->plans()->plan('late', Action::Publish, $this->in(-1), Actor::system());
	}

	public function testAPlanNeedsAScheduledType(): void
	{
		$this->node('note', 'Note', class: Note::class);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Nodes of ' . Note::class . ' are not scheduled; add #[Schedule] to the class');

		$this->plans()->plan('note', Action::Publish, $this->in(1), Actor::system());
	}

	public function testAPlanNeedsItsNode(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage("There is no node with the uid 'missing'");

		$this->plans()->plan('missing', Action::Publish, $this->in(1), Actor::system());
	}

	public function testAPlanThatIsNotDueWaits(): void
	{
		$this->node('waiting', 'Waiting');
		$this->plans()->plan('waiting', Action::Publish, $this->in(1), Actor::system());

		$this->assertSame(['done' => 0, 'failed' => 0], $this->plans()->run());
		$this->assertNotTrue($this->stored('waiting')?->meta->published);
	}

	public function testADuePlanBringsTheNodeOnlineWithItsDraftAsItsPlanner(): void
	{
		$editor = $this->user(roles: ['editor']);
		$this->actingAs($editor);
		$this->node('launch', 'First');
		$this->edit('launch', 'Final');
		$this->plans()->plan('launch', Action::Publish, $this->in(1), new Actor($editor->id));
		$this->due('launch', Action::Publish);

		// The job the plan queued runs whatever is due.
		$this->service(RunDue::class)->handle([]);

		$node = $this->stored('launch');
		$this->assertTrue($node?->meta->published);
		$this->assertSame('Final', $node->title());
		$this->assertSame([[
			'action' => 'publish',
			'state' => 'done',
			'note' => null,
			'planner' => $editor->id,
		]], $this->rows(
			'launch',
		));

		$written = array_slice($this->events('launch'), -2);
		$this->assertSame(['applied', 'published'], array_column($written, 0));
		$this->assertSame([$editor->id, $editor->id], array_column($written, 1));
		$this->assertSame(Plans::SOURCE, $written[0][2]['source']);
		$this->assertSame(Plans::SOURCE, $written[1][2]['source']);
		$this->assertArrayHasKey('planned', $written[1][2]);
	}

	public function testADuePlanBringsTheDraftOfAnOnlineNodeLive(): void
	{
		$this->actingAs($this->user(roles: ['editor']));
		$this->node('prices', 'Prices 2026', published: true);
		$this->edit('prices', 'Prices 2027');
		$this->plans()->plan('prices', Action::Publish, $this->in(1), Actor::system());
		$this->assertSame('Prices 2026', $this->stored('prices')?->title());
		$this->due('prices', Action::Publish);

		$this->assertSame(['done' => 1, 'failed' => 0], $this->plans()->run());

		$this->assertSame('Prices 2027', $this->stored('prices')?->title());
		$this->assertTrue($this->stored('prices')?->meta->published);
	}

	public function testADuePlanTakesTheNodeOffline(): void
	{
		$this->node('offer', 'Offer', published: true);
		$this->plans()->plan('offer', Action::Unpublish, $this->in(1), Actor::system());
		$this->due('offer', Action::Unpublish);

		$this->assertSame(['done' => 1, 'failed' => 0], $this->plans()->run());

		$this->assertFalse($this->stored('offer')?->meta->published);
		$this->assertSame('unpublished', array_last($this->events('offer'))[0]);
	}

	public function testAPlanWithNothingToChangeIsDoneAndSaysSo(): void
	{
		$this->node('steady', 'Steady', published: true);
		$this->plans()->plan('steady', Action::Publish, $this->in(1), Actor::system());
		$this->due('steady', Action::Publish);

		$this->assertSame(['done' => 1, 'failed' => 0], $this->plans()->run());

		$this->assertSame(Plan::UNCHANGED, $this->rows('steady')[0]['note']);
	}

	public function testAPlanThatCannotRunFailsWithTheReasonAndIsNotTriedAgain(): void
	{
		$this->node('gone', 'Gone');
		$this->node('fine', 'Fine');
		$this->plans()->plan('gone', Action::Publish, $this->in(1), Actor::system());
		$this->plans()->plan('fine', Action::Publish, $this->in(1), Actor::system());
		$this->due('gone', Action::Publish);
		$this->due('fine', Action::Publish);
		// Deleted behind the plans' back, without the event that would cancel the plan.
		$this
			->service(\Celema\Quma\Database::class)
			->execute(
				"UPDATE cms.nodes SET deleted = now() WHERE uid = 'gone'",
			)
			->run();

		$this->assertSame(['done' => 1, 'failed' => 1], $this->plans()->run());
		$this->assertSame(['done' => 0, 'failed' => 0], $this->plans()->run());

		$this->assertSame('failed', $this->rows('gone')[0]['state']);
		$this->assertSame("There is no node with the uid 'gone'", $this->rows('gone')[0]['note']);
		$this->assertTrue($this->stored('fine')?->meta->published);
	}

	public function testGoingOnlineByHandDropsThePlanToPublishAndKeepsTheOneToUnpublish(): void
	{
		$this->node('early', 'Early');
		$this->plans()->plan('early', Action::Publish, $this->in(24), Actor::system());
		$this->plans()->plan('early', Action::Unpublish, $this->in(48), Actor::system());

		$this->service(\Cosray\Node\Writer::class)->publish('early', Actor::system());

		$this->assertSame([Action::Unpublish->value], array_keys($this->plans()->of('early')));
	}

	public function testGoingOfflineByHandDropsThePlanToUnpublish(): void
	{
		$this->node('withdrawn', 'Withdrawn', published: true);
		$this->plans()->plan('withdrawn', Action::Unpublish, $this->in(24), Actor::system());

		$this->service(\Cosray\Node\Writer::class)->unpublish('withdrawn', Actor::system());

		$this->assertSame([], $this->plans()->of('withdrawn'));
	}

	public function testADraftAppliedByHandDropsThePlanOnlyWhenTheNodeIsOnline(): void
	{
		$this->actingAs($this->user(roles: ['editor']));
		$this->node('online', 'Online', published: true);
		$this->node('offline', 'Offline');

		foreach (['online', 'offline'] as $uid) {
			$this->edit($uid, 'Changed');
			$this->plans()->plan($uid, Action::Publish, $this->in(24), Actor::system());
			// Applying in the editor: a save that says so.
			$this->post(
				"/panel/node/{$uid}",
				['content' => ['title' => ['value' => ['zxx' => 'Changed']]], 'apply' => '1', '_complete' => '1'],
				['HX-Request' => 'true'],
			);
			$this->assertSame('Changed', $this->stored($uid)?->title(), $uid);
		}

		$this->assertSame([], $this->plans()->of('online'));
		$this->assertSame([Action::Publish->value], array_keys($this->plans()->of('offline')));
	}

	public function testDeletingTheNodeDropsItsPlans(): void
	{
		$this->actingAs($this->user(roles: ['editor']));
		$this->node('deleted', 'Deleted', published: true);
		$this->plans()->plan('deleted', Action::Publish, $this->in(24), Actor::system());
		$this->plans()->plan('deleted', Action::Unpublish, $this->in(48), Actor::system());

		$this->post('/panel/node/deleted/delete', headers: ['HX-Request' => 'true']);

		$this->assertNull($this->stored('deleted'));
		$this->assertSame([], $this->plans()->of('deleted'));
	}

	public function testAPlansOwnWriteKeepsTheNodesOtherPlan(): void
	{
		$this->node('window', 'Window');
		$this->plans()->plan('window', Action::Publish, $this->in(24), Actor::system());
		$this->plans()->plan('window', Action::Unpublish, $this->in(48), Actor::system());
		$this->due('window', Action::Publish);

		$this->plans()->run();

		$this->assertTrue($this->stored('window')?->meta->published);
		$this->assertSame([Action::Unpublish->value], array_keys($this->plans()->of('window')));

		$this->due('window', Action::Unpublish);
		$this->plans()->run();

		$this->assertFalse($this->stored('window')?->meta->published);
		$this->assertSame(['done', 'done'], array_column($this->rows('window'), 'state'));
	}

	public function testTheCountsTellPendingFinishedAndOverduePlansApart(): void
	{
		$this->node('count-a', 'A');
		$this->node('count-b', 'B');
		$this->node('count-c', 'C', published: true);
		$this->plans()->plan('count-a', Action::Publish, $this->in(1), Actor::system());
		$this->plans()->plan('count-b', Action::Publish, $this->in(1), Actor::system());
		$this->plans()->plan('count-c', Action::Publish, $this->in(1), Actor::system());
		$this->due('count-c', Action::Publish);
		$this->plans()->run();
		$this
			->service(\Celema\Quma\Database::class)
			->execute(
				"UPDATE content_schedule.plans SET due = now() - interval '1 hour' WHERE node = 'count-b'",
			)
			->run();

		$this->assertSame(
			['pending' => 2, 'failed' => 0, 'done' => 1, 'overdue' => 1],
			$this->plans()->counts(['article']),
		);
		$this->assertSame(
			['pending' => 0, 'failed' => 0, 'done' => 0, 'overdue' => 0],
			$this->plans()->counts(['setting']),
		);

		$pending = $this->plans()->list(Plan::PENDING, ['article']);
		$this->assertSame(2, $pending['total']);
		$this->assertSame(['count-b', 'count-a'], array_column($pending['plans'], 'node'));
		$this->assertSame(['count-c'], array_column($this->plans()->list(Plan::DONE, ['article'])['plans'], 'node'));
		$this->assertSame([], $this->plans()->list(Plan::PENDING, ['article'], Action::Unpublish)['plans']);
	}
}
