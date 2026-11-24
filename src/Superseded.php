<?php

declare(strict_types=1);

namespace Cosray\ContentSchedule;

use Cosray\Cms;
use Cosray\Context;
use Cosray\Contract\NodeListener;
use Cosray\Node\Types;
use Cosray\Node\Write;
use Override;

/** Drops the plans an editor's own action made pointless; see Plans::supersede(). */
final class Superseded implements NodeListener
{
	private readonly Plans $plans;

	public function __construct(Context $context, Cms $cms, Types $types)
	{
		$this->plans = new Plans($context, $cms, $types);
	}

	#[Override]
	public function written(Write $write): void
	{
		$this->plans->supersede($write);
	}
}
