<?php

declare(strict_types=1);

use Cosray\App;
use Cosray\ContentSchedule\Plugin;
use Cosray\ContentSchedule\Tests\Fixtures\Article;
use Cosray\ContentSchedule\Tests\Fixtures\Note;
use Cosray\ContentSchedule\Tests\Fixtures\Setting;
use Cosray\Locales;
use Psr\Log\NullLogger;

/**
 * The app the plugin's tests and its `run` script boot: Cosray with the
 * plugin, two scheduled types and one that is not. DATABASE_URL names the
 * test database.
 */
return static function (): App {
	$root = dirname(__DIR__);
	$dsn = getenv('DATABASE_URL')
	?: 'pgsql:host=localhost;dbname=content_schedule_test;user=content_schedule_test;password=content_schedule_test';
	$app = App::create($root, [
		'app.name' => 'content-schedule',
		'db.dsn' => $dsn,
		'path.public' => "{$root}/tests/public",
		'path.views' => '/tests/views',
		'mail.transports' => ['default' => 'null://null'],
		'mail.from' => 'Site <site@example.com>',
		// A test turns the plugin's own navigation entry off.
		'content-schedule.nav' => getenv('CONTENT_SCHEDULE_NAV') !== 'off',
	]);

	$locales = new Locales();
	$locales->add('en', title: 'English', pgDict: 'english');
	$app->load($locales);
	$app->logger(new NullLogger());
	$app->node(Article::class);
	$app->node(Setting::class);
	$app->node(Note::class);
	$app->plugin(Plugin::class);
	// May open the panel, but not change nodes.
	$app->role('reader', 'Reader');
	$app->allow('role:reader', 'panel', 'read-nodes');

	return $app->boot();
};
