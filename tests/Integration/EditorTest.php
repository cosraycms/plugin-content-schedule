<?php

declare(strict_types=1);

namespace Cosray\ContentSchedule\Tests\Integration;

use Cosray\Actor;
use Cosray\ContentSchedule\Action;
use Cosray\ContentSchedule\Tests\Fixtures\Note;
use Cosray\ContentSchedule\Tests\Fixtures\Setting;
use Cosray\ContentSchedule\Tests\TestCase;
use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use DOMXPath;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * The schedule in the node editor: which times its section offers, and
 * what posting them does.
 *
 * @internal
 *
 * @coversNothing
 */
final class EditorTest extends TestCase
{
	private const string SECTION = '//section[@id="cms-inspector-section-content-schedule"]';

	public function testAnOfflineNodeCanBePlannedToGoOnline(): void
	{
		$this->actingAs($this->user(roles: ['editor']));
		$this->node('fresh', 'Fresh');

		$html = $this->html($this->get('/panel/node/fresh'));

		$this->assertSame(['Goes online'], $this->labels($html));
		$this->assertSame(1, $this->found(
			'//form[@id="cms-inspector-form-content-schedule" and @action="/panel/content-schedule/nodes/fresh"]',
			$html,
		));
		$this->assertSame(1, $this->found(
			self::SECTION
				. '//input[@type="datetime-local" and @name="due[publish]" and @value=""'
				. ' and @form="cms-inspector-form-content-schedule"]',
			$html,
		));
	}

	public function testAnOnlineNodeCanBePlannedToUpdateAndToGoOffline(): void
	{
		$this->actingAs($this->user(roles: ['editor']));
		$this->node('live', 'Live', published: true);

		$this->assertSame(
			['Draft goes live', 'Goes offline'],
			$this->labels($this->html($this->get('/panel/node/live'))),
		);
	}

	public function testATypeWithoutARouteOrDraftsIsWordedByWhatItDoes(): void
	{
		$this->actingAs($this->user(roles: ['editor']));
		$this->node('inactive', 'Inactive', class: Setting::class);
		$this->node('active', 'Active', published: true, class: Setting::class);

		$this->assertSame(['Becomes active'], $this->labels($this->html($this->get('/panel/node/inactive'))));
		// Saved directly, it has no draft a plan could bring live.
		$this->assertSame(['Becomes inactive'], $this->labels($this->html($this->get('/panel/node/active'))));
	}

	public function testATypeWithoutTheAttributeHasNoSchedule(): void
	{
		$this->actingAs($this->user(roles: ['editor']));
		$this->node('plain', 'Plain', class: Note::class);

		$this->assertSame(0, $this->found(self::SECTION, $this->html($this->get('/panel/node/plain'))));
		$this->assertSame(404, $this->plan('plain', ['due' => ['publish' => $this->input('+1 day')]])->getStatusCode());
	}

	public function testATimePlansTheActionAndShowsWhoPlannedIt(): void
	{
		$editor = $this->user(roles: ['editor']);
		$this->actingAs($editor);
		$this->node('launch', 'Launch');
		$due = $this->input('+2 days');

		$response = $this->plan('launch', ['do' => 'save', 'due' => ['publish' => $due]]);

		$this->assertSame(200, $response->getStatusCode());
		$html = $this->html($response);
		$this->assertSame(1, $this->found(
			self::SECTION . '[@hx-swap-oob="true"]//input[@name="due[publish]" and @value="' . $due . '"]',
			$html,
		));
		// Planned to go online, it can be planned to go offline again.
		$this->assertSame(['Goes online', 'Goes offline'], $this->labels($html));
		$this->assertSame(1, $this->found(
			self::SECTION
				. '//button[@name="do" and @value="cancel:publish" and @form="cms-inspector-form-content-schedule"]',
			$html,
		));
		$this->assertStringContainsString('Planned by ', $html);
		$this->assertSame(1, $this->found('//*[@data-toast="success"][contains(., "Schedule saved")]', $html));

		$plan = $this->plans()->of('launch')[Action::Publish->value];
		$this->assertSame($editor->id, $plan->planner);
		$this->assertSame($due, $plan->due->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i'));
	}

	public function testSavingMovesChangedTimesAndLeavesTheOthers(): void
	{
		$first = $this->user(roles: ['editor']);
		$this->node('window', 'Window', published: true);
		$this->plans()->plan('window', Action::Publish, new DateTimeImmutable('+2 days'), new Actor($first->id));
		$kept = $this->plans()->of('window')[Action::Publish->value];
		$second = $this->user(roles: ['editor']);
		$this->actingAs($second);

		$html = $this->html($this->plan('window', [
			'do' => 'save',
			'due' => [
				'publish' => $kept->due->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i'),
				'unpublish' => $this->input('+5 days'),
			],
		]));

		$plans = $this->plans()->of('window');
		// Sent back as it was shown, the plan keeps its time to the second and its planner.
		$this->assertEquals($kept->due, $plans[Action::Publish->value]->due);
		$this->assertSame($first->id, $plans[Action::Publish->value]->planner);
		$this->assertSame($second->id, $plans[Action::Unpublish->value]->planner);
		$this->assertSame(1, $this->found('//*[@data-toast="success"]', $html));
	}

	public function testAnEmptiedTimeCancelsItsPlan(): void
	{
		$this->actingAs($this->user(roles: ['editor']));
		$this->node('emptied', 'Emptied');
		$this->plans()->plan('emptied', Action::Publish, new DateTimeImmutable('+2 days'), Actor::system());

		$html = $this->html($this->plan('emptied', ['do' => 'save', 'due' => ['publish' => '']]));

		$this->assertSame([], $this->plans()->of('emptied'));
		$this->assertSame(1, $this->found('//*[@data-toast="success"][contains(., "Schedule saved")]', $html));
		$this->assertSame(['Goes online'], $this->labels($html));
	}

	public function testRemovingCancelsOnePlan(): void
	{
		$this->actingAs($this->user(roles: ['editor']));
		$this->node('removed', 'Removed', published: true);
		$this->plans()->plan('removed', Action::Publish, new DateTimeImmutable('+2 days'), Actor::system());
		$this->plans()->plan('removed', Action::Unpublish, new DateTimeImmutable('+3 days'), Actor::system());

		// The other field arrives with the form and stays as it is.
		$html = $this->html($this->plan('removed', [
			'do' => 'cancel:unpublish',
			'due' => [
				'publish' => $this->input('+9 days'),
				'unpublish' => $this->input('+9 days'),
			],
		]));

		$plans = $this->plans()->of('removed');
		$this->assertSame([Action::Publish->value], array_keys($plans));
		$this->assertEqualsWithDelta(time() + (2 * 86400), $plans[Action::Publish->value]->due->getTimestamp(), 5);
		$this->assertSame(1, $this->found('//*[@data-toast="success"][contains(., "Plan removed")]', $html));
	}

	public function testATimeInThePastIsRefusedAndNothingIsSaved(): void
	{
		$this->actingAs($this->user(roles: ['editor']));
		$this->node('past', 'Past', published: true);

		$html = $this->html($this->plan('past', [
			'do' => 'save',
			'due' => [
				'publish' => $this->input('+2 days'),
				'unpublish' => $this->input('-1 hour'),
			],
		]));

		$this->assertSame([], $this->plans()->of('past'));
		$this->assertSame(1, $this->found(
			'//*[@data-toast="error"][contains(., "Choose a time in the future.")]',
			$html,
		));
	}

	public function testWhatIsNoTimeIsRefused(): void
	{
		$this->actingAs($this->user(roles: ['editor']));
		$this->node('nonsense', 'Nonsense');

		$html = $this->html($this->plan('nonsense', ['do' => 'save', 'due' => ['publish' => 'tomorrow']]));

		$this->assertSame([], $this->plans()->of('nonsense'));
		$this->assertSame(1, $this->found('//*[@data-toast="error"][contains(., "Enter a date and a time.")]', $html));
	}

	public function testAUserWhoMayNotChangeTheNodeMayNotPlan(): void
	{
		$this->actingAs($this->user(roles: ['reader']));
		$this->node('guarded', 'Guarded');

		$response = $this->plan('guarded', ['do' => 'save', 'due' => ['publish' => $this->input('+1 day')]]);

		$this->assertSame(403, $response->getStatusCode());
		$this->assertSame([], $this->plans()->of('guarded'));
	}

	public function testTheSectionFollowsANodeThatWentOnlineByHand(): void
	{
		$this->actingAs($this->user(roles: ['editor']));
		$this->node('byhand', 'By hand');
		$this->plans()->plan('byhand', Action::Publish, new DateTimeImmutable('+2 days'), Actor::system());

		$html = $this->html($this->post(
			'/panel/node/byhand/visibility',
			['published' => '1', 'listed' => '1'],
			['HX-Request' => 'true'],
		));

		// Online now, its plan to go online is gone, and the section offers what fits.
		$this->assertSame([], $this->plans()->of('byhand'));
		$this->assertSame(['Draft goes live', 'Goes offline'], $this->labels($html));
		$this->assertSame(1, $this->found(self::SECTION . '[@hx-swap-oob="true"]', $html));
	}

	/** @param array<string, mixed> $form */
	private function plan(string $uid, array $form): Response
	{
		return $this->post("/panel/content-schedule/nodes/{$uid}", $form, ['HX-Request' => 'true']);
	}

	/** A time as a `datetime-local` input sends it; the test app's panel shows UTC. */
	private function input(string $when): string
	{
		return new DateTimeImmutable($when, new DateTimeZone('UTC'))->format('Y-m-d\TH:i');
	}

	/** @return list<string> the labels of the times the section offers */
	private function labels(string $html): array
	{
		$labels = [];

		foreach ($this->xpath($html)->query(self::SECTION . '//label') ?: [] as $label) {
			$labels[] = trim((string) $label->textContent);
		}

		return $labels;
	}

	private function found(string $expression, string $html): int
	{
		$nodes = $this->xpath($html)->query($expression);

		return $nodes === false ? 0 : $nodes->length;
	}

	private function xpath(string $html): DOMXPath
	{
		$document = new DOMDocument();
		// The panel's markup has elements libxml's HTML 4 parser does not know.
		$before = libxml_use_internal_errors(true);
		$document->loadHTML('<?xml encoding="utf-8"?><div>' . $html . '</div>');
		libxml_clear_errors();
		libxml_use_internal_errors($before);

		return new DOMXPath($document);
	}
}
