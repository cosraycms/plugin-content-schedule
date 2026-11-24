<?php

declare(strict_types=1);

namespace Cosray\ContentSchedule\Tests\Integration;

use Celema\Quma\Database;
use Cosray\Actor;
use Cosray\ContentSchedule\Action;
use Cosray\ContentSchedule\Tests\Fixtures\Setting;
use Cosray\ContentSchedule\Tests\TestCase;

/**
 * The overview of the schedule, its entry in the content rail and its
 * card on the dashboard.
 *
 * @internal
 *
 * @coversNothing
 */
final class OverviewTest extends TestCase
{
	public function testThePanelLinksToTheScheduleAndCountsItOnTheDashboard(): void
	{
		$this->actingAs($this->user(roles: ['editor']));
		$this->node('soon', 'Soon');
		$this->plans()->plan('soon', Action::Publish, $this->in(24), Actor::system());

		$dashboard = $this->html($this->get('/panel'));
		$rail = $this->html($this->get('/panel/content-schedule'));

		$this->assertMatchesRegularExpression(
			'~<a class="cms-dashboard-card" href="/panel/content-schedule"[^>]*>.*?Planned, next 7 days.*?Next: ~s',
			$dashboard,
		);
		$this->assertMatchesRegularExpression(
			'~<span>Planning</span>.*?href="/panel/content-schedule"\s+aria-current="page".*?<span>Schedule</span>~s',
			$rail,
		);
	}

	public function testASiteCanPlaceTheLinkItself(): void
	{
		putenv('CONTENT_SCHEDULE_NAV=off');

		try {
			$this->app = $this->createApp();
			$this->actingAs($this->user(roles: ['editor']));

			$response = $this->get('/panel/content-schedule');
		} finally {
			putenv('CONTENT_SCHEDULE_NAV');
		}

		$this->assertSame(200, $response->getStatusCode());
		$this->assertStringNotContainsString('<span>Planning</span>', $this->html($response));
	}

	public function testUpcomingPlansAreListedByDayWithTheirNodes(): void
	{
		$editor = $this->user(roles: ['editor']);
		$this->actingAs($editor);
		$this->node('first', 'Christmas market');
		$this->node('second', 'Price list', published: true);
		$this->plans()->plan('second', Action::Unpublish, $this->in(72), new Actor($editor->id));
		$this->plans()->plan('first', Action::Publish, $this->in(24), new Actor($editor->id));

		$html = $this->html($this->get('/panel/content-schedule'));

		$this->assertStringContainsString('2 planned actions', $html);
		$this->assertMatchesRegularExpression(
			'~<a href="/panel/node/first[^"]*">Christmas market</a>.*<a href="/panel/node/second[^"]*">Price list</a>~s',
			$html,
		);
		$this->assertSame(2, substr_count($html, 'class="content-schedule-day"'));
		$this->assertMatchesRegularExpression('~Upcoming\s*<span class="cms-kit-filter-count">2</span>~', $html);
		$this->assertStringNotContainsString('overdue', $html);
		$this->assertStringNotContainsString('Draft goes live', $html);
	}

	public function testAPendingPlanIsWordedByWhatItWillDoToItsNode(): void
	{
		$this->actingAs($this->user(roles: ['editor']));
		$this->node('update', 'Online already', published: true);
		$this->node('switch', 'A setting', class: Setting::class);
		$this->plans()->plan('update', Action::Publish, $this->in(24), Actor::system());
		$this->plans()->plan('switch', Action::Publish, $this->in(48), Actor::system());

		$html = $this->html($this->get('/panel/content-schedule'));

		$this->assertMatchesRegularExpression('~Draft goes live</span>(?:(?!</li>).)*Online already~s', $html);
		$this->assertMatchesRegularExpression('~Becomes active</span>(?:(?!</li>).)*A setting~s', $html);
	}

	public function testTheListNarrowsToAnActionAndAType(): void
	{
		$this->actingAs($this->user(roles: ['editor']));
		$this->node('article', 'An article');
		$this->node('setting', 'A setting', class: Setting::class);
		$this->plans()->plan('article', Action::Publish, $this->in(24), Actor::system());
		$this->plans()->plan('setting', Action::Publish, $this->in(24), Actor::system());
		$this->plans()->plan('setting', Action::Unpublish, $this->in(48), Actor::system());

		$offline = $this->html($this->get('/panel/content-schedule?action=unpublish'));
		$articles = $this->html($this->get('/panel/content-schedule?type=article'));

		$this->assertStringNotContainsString('An article', $offline);
		$this->assertSame(1, substr_count($offline, '>A setting</a>'));
		$this->assertStringContainsString('An article', $articles);
		$this->assertStringNotContainsString('A setting', $articles);
		// The filters keep each other.
		$this->assertStringContainsString('href="/panel/content-schedule?action=unpublish&amp;type=article"', $offline);
	}

	public function testFinishedPlansAreListedWithWhatBecameOfThem(): void
	{
		$this->actingAs($this->user(roles: ['editor']));
		$this->node('done', 'Went online');
		$this->node('steady', 'Already online', published: true);
		$this->node('gone', 'Vanished');

		foreach (['done', 'steady', 'gone'] as $uid) {
			$this->plans()->plan($uid, Action::Publish, $this->in(1), Actor::system());
			$this->due($uid, Action::Publish);
		}

		$this->service(Database::class)->execute("UPDATE cms.nodes SET deleted = now() WHERE uid = 'gone'")->run();
		$this->plans()->run();

		$upcoming = $this->html($this->get('/panel/content-schedule'));
		$done = $this->html($this->get('/panel/content-schedule?state=done'));
		$failed = $this->html($this->get('/panel/content-schedule?state=failed'));

		$this->assertStringContainsString('Nothing is planned', $upcoming);
		$this->assertStringContainsString('Went online', $done);
		$this->assertStringContainsString('Nothing to change: the entry was already in that state.', $done);
		$this->assertStringContainsString('Deleted entry', $failed);
		$this->assertStringContainsString('There is no node with the uid', $failed);
		$this->assertStringNotContainsString('Went online', $failed);
	}

	public function testOverduePlansSayThatNothingRunsTheQueue(): void
	{
		$this->actingAs($this->user(roles: ['editor']));
		$this->node('stuck', 'Stuck');
		$this->plans()->plan('stuck', Action::Publish, $this->in(1), Actor::system());
		$this
			->service(Database::class)
			->execute(
				"UPDATE content_schedule.plans SET due = now() - interval '2 hours' WHERE node = 'stuck'",
			)
			->run();

		$html = $this->html($this->get('/panel/content-schedule'));

		$this->assertStringContainsString('1 planned action is overdue', $html);
		$this->assertMatchesRegularExpression(
			'~<span class="cms-kit-pill" data-tone="warning">Goes online</span>~',
			$html,
		);
		$this->assertStringContainsString('Nothing seems to run the job queue.', $html);
	}

	public function testAUserSeesOnlyThePlansOfNodesTheyMayChange(): void
	{
		$this->node('hidden', 'Not for readers');
		$this->plans()->plan('hidden', Action::Publish, $this->in(24), Actor::system());
		$this->actingAs($this->user(roles: ['reader']));

		$html = $this->html($this->get('/panel/content-schedule'));

		$this->assertStringNotContainsString('Not for readers', $html);
		$this->assertStringContainsString('Nothing is planned', $html);
	}
}
