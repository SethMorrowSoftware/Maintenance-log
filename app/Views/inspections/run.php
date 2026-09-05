<?php
/**
 * The checklist runner.
 *
 * This is the screen used one-handed, on a phone, standing next to a kart at
 * eight in the morning. Everything about it is built for that:
 *
 *  - Pass / Fail / N/A are big buttons, not a dropdown.
 *  - A failed item opens a notes box, because that is when there is something
 *    to say.
 *  - Progress sticks to the bottom of the screen so the count is always visible.
 *  - It saves without finishing, so losing signal loses nothing.
 */

use App\Dates;
use App\Settings;
use App\Status;
use App\View;

$inspectionId = (int) $inspection['id'];
$meterUnit    = (string) $inspection['meter_type'];
$hasMeter     = $meterUnit !== 'none';

// What this particular checklist asks for. A checklist whose template has since
// been deleted falls back to the site-wide defaults.
$needsSignature = $inspection['require_signature'] === null
    ? Settings::bool('inspection_signature_required', true)
    : (int) $inspection['require_signature'] === 1;

$asksForMeter = $inspection['require_meter'] === null
    ? true
    : (int) $inspection['require_meter'] === 1;

// If the checklist already has a "meter reading" line on it, do not ask for the
// same number again at the bottom of the page.
$meterOnList = false;

foreach ($sections as $sectionItems) {
    foreach ($sectionItems as $sectionItem) {
        if ((string) $sectionItem['response_type'] === 'meter') {
            $meterOnList = true;
            break 2;
        }
    }
}
?>

<form method="post" action="<?= e(url('inspection-run.php', ['id' => $inspectionId])) ?>" data-guard>
    <?= csrf_field() ?>

    <div class="alert alert-info no-print">
        <?= icon('info', '', 18) ?>
        <div class="alert-body">
            Work down the list. Anything you mark <strong>Fail</strong> opens a note box —
            say what is wrong. You can stop and come back: nothing is lost until you finish.
        </div>
    </div>

    <?php $itemNumber = 0; ?>
    <?php foreach ($sections as $sectionName => $items): ?>
        <div class="checklist-section">
            <?php if ((string) $sectionName !== ''): ?>
                <h2 class="checklist-section-title"><?= e((string) $sectionName) ?></h2>
            <?php endif; ?>

            <?php foreach ($items as $item): ?>
                <?php
                $itemNumber++;
                $itemId    = (int) $item['id'];
                $response  = (string) $item['response'];
                $type      = (string) $item['response_type'];
                $critical  = (int) $item['is_critical'] === 1;
                $answered  = $response !== '' || (string) $item['value_text'] !== '' || $item['value_number'] !== null;

                $stateClass = 'is-unanswered';

                if (in_array($response, ['pass', 'yes'], true)) {
                    $stateClass = 'is-pass';
                } elseif (in_array($response, ['fail', 'no'], true)) {
                    $stateClass = 'is-fail';
                } elseif ($response === 'na') {
                    $stateClass = 'is-na';
                } elseif ($answered) {
                    $stateClass = 'is-pass';
                }
                ?>
                <div class="checklist-item <?= e($stateClass) ?> is-required" data-checklist-item>
                    <div class="checklist-item-head">
                        <div>
                            <div class="checklist-item-text">
                                <span class="text-subtle"><?= (int) $itemNumber ?>.</span>
                                <?= e((string) $item['item_text']) ?>
                            </div>
                            <?php
                            // The original description lives on the template.
                            $template = $item['checklist_item_id'] === null
                                ? null
                                : db()->one('SELECT description, unit, min_value, max_value FROM {checklist_items} WHERE id = ?',
                                    [(int) $item['checklist_item_id']]);
                            ?>
                            <?php if ($template !== null && (string) $template['description'] !== ''): ?>
                                <div class="checklist-item-desc"><?= e((string) $template['description']) ?></div>
                            <?php endif; ?>
                        </div>
                        <?php if ($critical): ?>
                            <span class="checklist-critical">
                                <?= icon('alert-triangle', '', 13) ?> Safety
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php // ---------- Pass / fail style answers ---------- ?>
                    <?php $choices = App\Models\Inspection::allowedResponses($type); ?>
                    <?php if ($choices !== []): ?>
                        <div class="response-group" role="radiogroup"
                             aria-label="<?= attr((string) $item['item_text']) ?>">
                            <?php foreach ($choices as $choice): ?>
                                <label class="response-btn<?= $response === $choice ? ' is-selected' : '' ?>"
                                       data-response="<?= e($choice) ?>">
                                    <input type="radio" class="sr-only"
                                           name="items[<?= $itemId ?>][response]"
                                           value="<?= e($choice) ?>"
                                           <?= checked($response, $choice) ?>
                                           data-response-input>
                                    <?= e(Status::label($choice, 'response')) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php // ---------- Number and meter answers ---------- ?>
                    <?php if ($type === 'number' || $type === 'meter'): ?>
                        <div class="input-group" style="max-width:260px">
                            <input type="number" step="0.01" class="form-input"
                                   name="items[<?= $itemId ?>][value_number]"
                                   value="<?= attr((string) ($item['value_number'] ?? '')) ?>"
                                   inputmode="decimal"
                                   aria-label="<?= attr((string) $item['item_text']) ?>">
                            <?php if ($template !== null && (string) $template['unit'] !== ''): ?>
                                <span class="input-addon"><?= e((string) $template['unit']) ?></span>
                            <?php elseif ($type === 'meter' && $hasMeter): ?>
                                <span class="input-addon"><?= e($meterUnit) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($template !== null && ($template['min_value'] !== null || $template['max_value'] !== null)): ?>
                            <div class="form-hint">
                                Expected
                                <?= $template['min_value'] !== null ? e(decimal($template['min_value'])) : '' ?>
                                <?= $template['min_value'] !== null && $template['max_value'] !== null ? ' to ' : '' ?>
                                <?= $template['max_value'] !== null ? e(decimal($template['max_value'])) : '' ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php // ---------- Free text answers ---------- ?>
                    <?php if ($type === 'text'): ?>
                        <textarea class="form-textarea" rows="2"
                                  name="items[<?= $itemId ?>][value_text]"
                                  maxlength="500" data-autogrow
                                  aria-label="<?= attr((string) $item['item_text']) ?>"><?= e((string) $item['value_text']) ?></textarea>
                    <?php endif; ?>

                    <?php // ---------- Notes, shown when something failed ---------- ?>
                    <?php if ($choices !== []): ?>
                        <div class="checklist-notes" data-fail-notes
                             <?= in_array($response, ['fail', 'no'], true) || (string) $item['notes'] !== '' ? '' : 'hidden' ?>>
                            <label class="form-label" for="notes-<?= $itemId ?>">What is wrong?</label>
                            <textarea class="form-textarea" rows="2" id="notes-<?= $itemId ?>"
                                      name="items[<?= $itemId ?>][notes]" maxlength="500" data-autogrow
                                      placeholder="Pads down to the wear line on the near side."><?= e((string) $item['notes']) ?></textarea>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <?php // ======================= Sign off ======================= ?>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><?= icon('check-circle', '', 18) ?> Finish up</h2>
        </div>
        <div class="card-body">
            <?php if ($hasMeter && !$meterOnList && $asksForMeter): ?>
                <?php View::partial('form-field', [
                    'name'   => 'meter_reading',
                    'label'  => 'Meter reading',
                    'type'   => 'meter',
                    'value'  => $inspection['meter_reading'],
                    'suffix' => $meterUnit,
                    'hint'   => 'Optional. Updates the machine if you fill it in.',
                    'noOld'  => true,
                    'attrs'  => ['min' => '0'],
                ]); ?>
            <?php endif; ?>

            <?php View::partial('form-field', [
                'name'        => 'notes',
                'label'       => 'Anything else worth noting?',
                'type'        => 'textarea',
                'value'       => $inspection['notes'],
                'rows'        => 3,
                'noOld'       => true,
                'placeholder' => 'Tyre pressures set to 18 front, 20 rear.',
                'attrs'       => ['maxlength' => 5000, 'data-autogrow' => true],
            ]); ?>

            <?php View::partial('form-field', [
                'name'     => 'signature_name',
                'label'    => 'Your name',
                'type'     => 'text',
                'value'    => $inspection['signature_name'] ?: user_name(),
                'required' => $needsSignature,
                'hint'     => 'Signs off the inspection. This appears on the printed record.',
                'noOld'    => true,
                'attrs'    => ['maxlength' => 120],
            ]); ?>

            <label class="form-check" for="f_oos">
                <input type="checkbox" id="f_oos" name="take_out_of_service" value="1">
                <span class="form-check-label">
                    Take this machine out of service
                    <small>
                        Tick if it is not safe to run. Anything that fails a safety-critical
                        item should almost certainly be ticked.
                    </small>
                </span>
            </label>
        </div>
    </div>

    <?php // ============ Sticky progress bar and actions ============ ?>
    <div class="checklist-progress no-print">
        <div class="checklist-counts">
            <span><strong data-count-answered>0</strong> / <?= (int) $itemCount ?> done</span>
            <span class="text-ok"><strong data-count-pass>0</strong> pass</span>
            <span class="text-danger"><strong data-count-fail>0</strong> fail</span>
        </div>
        <div class="progress" style="flex:1">
            <div class="progress-bar" data-progress-bar style="width:0"></div>
        </div>
        <div class="flex gap-2">
            <button type="submit" name="action" value="save" class="btn btn-secondary" data-no-guard>
                Save for later
            </button>
            <button type="submit" name="action" value="complete" class="btn btn-primary">
                <?= icon('check', '', 17) ?> Finish
            </button>
        </div>
    </div>
</form>
