<?php

declare(strict_types=1);

use function Cosray\escape;

// The overview of the schedule: what is planned, by day, and what failed
// or ran lately. Receives: days, state, none, pending, overdue, stateLinks,
// actionLinks, typeLinks, total, rangeStart, rangeEnd, previousUrl, nextUrl.

$this->layout('layer/main');

$days = (array) u($days);
$state = (string) u($state);
$overdue = (int) u($overdue);
$typeLinks = (array) u($typeLinks);
$tones = ['pending' => 'info', 'done' => 'success', 'failed' => 'danger'];
?>

<div class="cms-page cms-kit-page content-schedule">
	<?php $this->include('kit/head', [
		'title' => __('content-schedule:nav'),
		'count' => __n('content-schedule:count', 'content-schedule:count-plural', (int) u($pending)),
	]) ?>

	<div class="cms-kit-body">
		<div class="cms-page-toolbar">
			<?php $this->include('kit/filter', ['label' => __('content-schedule:filter-state'), 'links' => $stateLinks]) ?>
			<?php $this->include('kit/filter', ['label' => __('content-schedule:filter-action'), 'links' => $actionLinks]) ?>
			<?php if ($typeLinks !== []): ?>
				<?php $this->include('kit/filter', ['label' => __('content-schedule:filter-type'), 'links' => $typeLinks]) ?>
			<?php endif ?>
		</div>

		<div class="cms-kit-scroll">
			<?php if ($overdue > 0): ?>
				<div class="cms-kit-cards">
					<article class="cms-kit-card" data-tone="warning" role="status">
						<header class="cms-kit-card-head">
							<h3><?= escape(__n('content-schedule:overdue', 'content-schedule:overdue-plural', $overdue)) ?></h3>
						</header>
						<p><?= escape(__('content-schedule:overdue-help')) ?></p>
					</article>
				</div>
			<?php endif ?>

			<?php if ($days === []): ?>
				<?php $this->include('kit/empty', [
					'title' => $none,
					'text' => $state === 'pending' ? __('content-schedule:empty-help') : null,
				]) ?>
			<?php endif ?>

			<?php foreach ($days as $day): ?>
				<?php $day = (array) $day; ?>
				<section class="content-schedule-day">
					<h2><?= escape((string) $day['label']) ?></h2>
					<ul class="cms-kit-list">
						<?php foreach ((array) $day['plans'] as $plan): ?>
							<?php

							$plan = (array) $plan;
							$action = (string) $plan['action'];
							?>
							<li data-state="<?= escape((string) $plan['state']) ?>">
								<time class="content-schedule-time"><?= escape((string) $plan['time']) ?></time>
								<span class="content-schedule-action">
									<?php $this->include('kit/pill', [
									'label' => $plan['actionLabel'],
									'tone' => match (true) {
										(bool) $plan['overdue'] => 'warning',
										$plan['state'] === 'pending' => $action === 'publish' ? 'success' : 'neutral',
										default => $tones[(string) $plan['state']],
									},
								]) ?>
								</span>
								<span class="content-schedule-title">
									<?php if (is_string($plan['url'])): ?>
										<a href="<?= escape($plan['url']) ?>"><?= escape((string) $plan['title']) ?></a>
									<?php else: ?>
										<?= escape(__('content-schedule:gone')) ?>
									<?php endif ?>
								</span>
								<span class="content-schedule-meta">
									<?= escape((string) $plan['type']) ?> · <?= escape((string) $plan['planner']) ?>
									<?php if (is_string($plan['due'])): ?>
										· <?= escape(__('content-schedule:was-due', ['date' => $plan['due']])) ?>
									<?php endif ?>
								</span>
								<?php if (is_string($plan['note'])): ?>
									<span class="content-schedule-note"><?= escape($plan['note']) ?></span>
								<?php endif ?>
							</li>
						<?php endforeach ?>
					</ul>
				</section>
			<?php endforeach ?>
		</div>

		<?php if ((int) u($total) > 0): ?>
			<?php $this->include('kit/pagination', [
				'start' => $rangeStart,
				'end' => $rangeEnd,
				'total' => $total,
				'previous' => $previousUrl,
				'next' => $nextUrl,
			]) ?>
		<?php endif ?>
	</div>
</div>
