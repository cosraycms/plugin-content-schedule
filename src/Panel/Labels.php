<?php

declare(strict_types=1);

namespace Cosray\ContentSchedule\Panel;

use Cosray\ContentSchedule\Action;
use Cosray\ContentSchedule\Plan;

/**
 * The labels that depend on a state, an action or a type's kind. Literal
 * message ids, so the translation tooling finds them.
 */
final class Labels
{
	/**
	 * What a time in the editor's section stands for, and its help line. A
	 * type with a route goes online and offline, one without becomes active
	 * and inactive; online, publishing brings the draft live.
	 *
	 * @return array{string, string}
	 */
	public static function field(Action $action, bool $online, bool $routable): array
	{
		return match (true) {
			$action === Action::Unpublish && $routable => [
				__('content-schedule:unpublish'),
				__('content-schedule:unpublish-help'),
			],
			$action === Action::Unpublish => [
				__('content-schedule:deactivate'),
				__('content-schedule:deactivate-help'),
			],
			$online && $routable => [
				__('content-schedule:publish-draft'),
				__('content-schedule:publish-draft-help'),
			],
			$online => [
				__('content-schedule:activate-draft'),
				__('content-schedule:activate-draft-help'),
			],
			$routable => [
				__('content-schedule:publish'),
				__('content-schedule:publish-help'),
			],
			default => [
				__('content-schedule:activate'),
				__('content-schedule:activate-help'),
			],
		};
	}

	public static function state(string $state): string
	{
		return match ($state) {
			Plan::FAILED => __('content-schedule:state-failed'),
			Plan::DONE => __('content-schedule:state-done'),
			default => __('content-schedule:state-pending'),
		};
	}

	/** What the overview says when a state holds no plans. */
	public static function none(string $state): string
	{
		return match ($state) {
			Plan::FAILED => __('content-schedule:empty-failed'),
			Plan::DONE => __('content-schedule:empty-done'),
			default => __('content-schedule:empty-pending'),
		};
	}

	public static function action(?Action $action): string
	{
		return match ($action) {
			Action::Publish => __('content-schedule:action-publish'),
			Action::Unpublish => __('content-schedule:action-unpublish'),
			null => __('content-schedule:action-all'),
		};
	}

	/** The content rail's section and entry, which the panel translates. */
	public static function nav(): string
	{
		return __('content-schedule:nav') . __('content-schedule:nav-section');
	}
}
