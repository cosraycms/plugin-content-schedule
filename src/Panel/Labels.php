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
	 * and inactive.
	 *
	 * @return array{string, string}
	 */
	public static function field(Action $action, bool $routable): array
	{
		return match (true) {
			$action === Action::Publish && $routable => [
				__('content-schedule:online-from'),
				__('content-schedule:online-from-help'),
			],
			$action === Action::Publish => [
				__('content-schedule:active-from'),
				__('content-schedule:active-from-help'),
			],
			$routable => [
				__('content-schedule:offline-from'),
				__('content-schedule:offline-from-help'),
			],
			default => [
				__('content-schedule:inactive-from'),
				__('content-schedule:inactive-from-help'),
			],
		};
	}

	/** What a pending plan will do, worded by whether its type has a route. */
	public static function pending(Action $action, bool $routable): string
	{
		return match (true) {
			$action === Action::Publish && $routable => __('content-schedule:publish'),
			$action === Action::Publish => __('content-schedule:activate'),
			$routable => __('content-schedule:unpublish'),
			default => __('content-schedule:deactivate'),
		};
	}

	/** Why a node that is offline may not be planned to go offline without going online before. */
	public static function alone(bool $routable): string
	{
		return $routable ? __('content-schedule:offline-alone') : __('content-schedule:inactive-alone');
	}

	/** Why the two times of a node may not fall in the same minute. */
	public static function same(bool $routable): string
	{
		return $routable ? __('content-schedule:offline-same') : __('content-schedule:inactive-same');
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
