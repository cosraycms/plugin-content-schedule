<?php

declare(strict_types=1);

namespace Cosray\ContentSchedule;

use Cosray\Cms;
use Cosray\Context;
use Cosray\Contract\Job;
use Cosray\Node\Types;
use Override;

/**
 * Wakes the runner at the time a plan is due. It runs every plan that is
 * due by then, so a wake-up whose plan was moved or cancelled finds
 * nothing, and one that ran late catches up.
 */
final class RunDue implements Job
{
	private readonly Plans $plans;

	public function __construct(Context $context, Cms $cms, Types $types)
	{
		$this->plans = new Plans($context, $cms, $types);
	}

	#[Override]
	public function handle(array $payload): void
	{
		$this->plans->run();
	}
}
