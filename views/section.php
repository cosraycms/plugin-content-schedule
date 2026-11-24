<?php

declare(strict_types=1);

use function Cosray\escape;

// The schedule of a node in the editor's inspector: a time for each action
// that fits the node's state. A time saved plans the action, an emptied one
// cancels it, and a pending plan says who planned it and can be removed.
// Receives: fields (action, label, help, value, planned), min, form.

$fields = (array) u($fields);
$form = (string) u($form);
$min = (string) u($min);
?>
<?php foreach ($fields as $field): ?>
	<?php

	$field = (array) $field;
	$action = (string) $field['action'];
	$id = "content-schedule-{$action}";
	$planned = $field['planned'] ?? null;
	?>
	<div class="cms-kit-inspector-field" data-schedule="<?= escape($action) ?>">
		<label for="<?= escape($id) ?>"><?= escape((string) $field['label']) ?></label>
		<input
			id="<?= escape($id) ?>"
			class="cms-input"
			type="datetime-local"
			name="due[<?= escape($action) ?>]"
			value="<?= escape((string) $field['value']) ?>"
			min="<?= escape($min) ?>"
			aria-describedby="<?= escape($id) ?>-help"
			form="<?= escape($form) ?>" />
		<?php if (is_string($planned)): ?>
			<div class="cms-kit-inspector-row">
				<span class="cms-kit-inspector-help" id="<?= escape($id) ?>-help"><?= escape($planned) ?></span>
				<button
					type="submit"
					class="cms-button cms-button-secondary cms-button-small"
					name="do"
					value="cancel:<?= escape($action) ?>"
					form="<?= escape($form) ?>">
					<?= escape(__('content-schedule:remove')) ?>
				</button>
			</div>
		<?php else: ?>
			<p class="cms-kit-inspector-help" id="<?= escape($id) ?>-help"><?= escape((string) $field['help']) ?></p>
		<?php endif ?>
	</div>
<?php endforeach ?>
<div class="content-schedule-save">
	<button
		type="submit"
		class="cms-button cms-button-secondary cms-button-small"
		name="do"
		value="save"
		form="<?= escape($form) ?>">
		<?= escape(__('content-schedule:save')) ?>
	</button>
</div>
