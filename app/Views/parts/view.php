<?php
/**
 * One part: how many there are, where they went, and what they cost.
 */

use App\Dates;
use App\Status;
use App\View;

$partId    = (int) $part['id'];
$onHand    = (float) $part['quantity_on_hand'];
$level     = (float) $part['reorder_level'];
$state     = Status::stockState($part);
$unit      = (string) $part['unit_of_measure'];
$canAdjust = can('parts.adjust');
?>

<div class="grid grid-sidebar">
    <div>
        <?php // ==================== How many ==================== ?>
        <div class="card">
            <div class="card-body">
                <div class="stock-headline">
                    <div>
                        <span class="stock-big tone-<?= e($state) ?>"><?= e(decimal($onHand)) ?></span>
                        <span class="stock-unit"><?= e($unit) ?> on hand</span>
                    </div>
                    <div class="stock-note">
                        <?php if ($state === 'out'): ?>
                            <strong class="text-danger">None left.</strong>
                            <?php if ((float) $part['reorder_quantity'] > 0): ?>
                                You normally order <?= e(decimal($part['reorder_quantity'])) ?>.
                            <?php endif; ?>
                        <?php elseif ($state === 'low'): ?>
                            <strong class="text-warn">Running low.</strong>
                            The reorder point is <?= e(decimal($level)) ?>
                            <?php if ((float) $part['reorder_quantity'] > 0): ?>
                                and you normally order <?= e(decimal($part['reorder_quantity'])) ?>
                            <?php endif; ?>.
                        <?php elseif ($state === 'untracked'): ?>
                            No reorder point set, so nothing will warn you when this runs down.
                        <?php else: ?>
                            Plenty. You will be warned at <?= e(decimal($level)) ?>.
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($canAdjust): ?>
                    <form method="post" action="<?= e(url('part-view.php', ['id' => $partId])) ?>"
                          class="stock-form is-large mt-4">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="adjust">
                        <div class="input-group" style="max-width:200px">
                            <input type="number" step="0.01" min="0.01" class="form-input"
                                   name="amount" placeholder="0" inputmode="decimal"
                                   aria-label="How many">
                            <span class="input-addon"><?= e($unit) ?></span>
                        </div>
                        <button type="submit" name="way" value="out" class="btn btn-secondary">
                            <?= icon('minus', '', 16) ?> Took some
                        </button>
                        <button type="submit" name="way" value="in" class="btn btn-secondary">
                            <?= icon('plus', '', 16) ?> Put some back
                        </button>
                        <input type="text" class="form-input" name="notes" maxlength="255"
                               placeholder="What for? (optional)" aria-label="Note">
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php // ==================== Where it went ==================== ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><?= icon('wrench', '', 18) ?> Used on</h2>
            </div>
            <?php if ($usage === []): ?>
                <div class="card-body">
                    <p class="text-muted" style="margin:0">
                        This part has not been used on a job yet.
                    </p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table is-stacked">
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>Asset</th>
                                <th>Job</th>
                                <th class="is-numeric">Used</th>
                                <th class="is-numeric">Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usage as $row): ?>
                                <tr>
                                    <td data-label="When"><?= e(Dates::date((string) $row['performed_at'])) ?></td>
                                    <td data-label="Asset"><?= e((string) $row['asset_name']) ?></td>
                                    <td data-label="Job">
                                        <a href="<?= e(url('log-view.php', ['id' => (int) $row['log_id']])) ?>">
                                            <?= e((string) $row['title']) ?>
                                        </a>
                                    </td>
                                    <td data-label="Used" class="is-numeric"><?= e(decimal($row['quantity'])) ?></td>
                                    <td data-label="Cost" class="is-numeric"><?= e(money($row['total_cost'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <?php // ==================== Every movement ==================== ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><?= icon('history', '', 18) ?> Stock movements</h2>
                <p class="card-subtitle">Every change to the count, and who made it</p>
            </div>
            <?php if ($transactions === []): ?>
                <div class="card-body">
                    <p class="text-muted" style="margin:0">Nothing recorded yet.</p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table is-stacked">
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>What happened</th>
                                <th class="is-numeric">Change</th>
                                <th class="is-numeric">Left</th>
                                <th>Who</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $tx): ?>
                                <?php
                                $type   = (string) $tx['transaction_type'];
                                $signed = $type === 'out' ? -(float) $tx['quantity'] : (float) $tx['quantity'];
                                ?>
                                <tr>
                                    <td data-label="When">
                                        <?= e(Dates::datetime((string) $tx['created_at'])) ?>
                                    </td>
                                    <td data-label="What happened">
                                        <?php if ((string) $tx['reference_type'] === 'maintenance_log'
                                                  && $tx['reference_id'] !== null): ?>
                                            <a href="<?= e(url('log-view.php', ['id' => (int) $tx['reference_id']])) ?>">
                                                Used on a job
                                            </a>
                                        <?php elseif ($type === 'in'): ?>
                                            Put back on the shelf
                                        <?php elseif ($type === 'out'): ?>
                                            Taken off the shelf
                                        <?php else: ?>
                                            Count corrected
                                        <?php endif; ?>
                                        <?php if ((string) $tx['notes'] !== ''): ?>
                                            <span class="cell-secondary"><?= e((string) $tx['notes']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Change" class="is-numeric">
                                        <span class="<?= $signed < 0 ? 'text-danger' : 'text-ok' ?>">
                                            <?= $signed > 0 ? '+' : '' ?><?= e(decimal($signed)) ?>
                                        </span>
                                    </td>
                                    <td data-label="Left" class="is-numeric">
                                        <?= $tx['balance_after'] === null
                                            ? '<span class="text-subtle">&mdash;</span>'
                                            : e(decimal($tx['balance_after'])) ?>
                                    </td>
                                    <td data-label="Who">
                                        <?= e(trim((string) $tx['first_name'] . ' ' . (string) $tx['last_name'])
                                            ?: (string) ($tx['username'] ?? 'System')) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php // ==================== Sidebar ==================== ?>
    <div>
        <div class="card">
            <div class="card-header"><h3 class="card-title">Details</h3></div>
            <div class="card-body">
                <dl class="detail-list">
                    <dt>Part number</dt>
                    <dd><code><?= e((string) $part['part_number']) ?></code></dd>

                    <?php if ((string) $part['category'] !== ''): ?>
                        <dt>Category</dt>
                        <dd>
                            <a href="<?= e(url('parts.php', ['category' => (string) $part['category']])) ?>">
                                <?= e((string) $part['category']) ?>
                            </a>
                        </dd>
                    <?php endif; ?>

                    <dt>Cost each</dt>
                    <dd class="tabular"><?= e(money($part['unit_cost'])) ?></dd>

                    <dt>Value on the shelf</dt>
                    <dd class="tabular"><?= e(money($onHand * (float) $part['unit_cost'])) ?></dd>

                    <?php if ((string) $part['location_bin'] !== ''): ?>
                        <dt>Where it lives</dt>
                        <dd><?= e((string) $part['location_bin']) ?></dd>
                    <?php endif; ?>

                    <?php if ((string) $part['manufacturer'] !== ''): ?>
                        <dt>Made by</dt>
                        <dd><?= e((string) $part['manufacturer']) ?></dd>
                    <?php endif; ?>

                    <?php if ((string) $part['supplier'] !== ''): ?>
                        <dt>Bought from</dt>
                        <dd>
                            <?= e((string) $part['supplier']) ?>
                            <?php if ((string) $part['supplier_part_number'] !== ''): ?>
                                <div class="text-sm text-muted">
                                    Their number: <?= e((string) $part['supplier_part_number']) ?>
                                </div>
                            <?php endif; ?>
                        </dd>
                    <?php endif; ?>

                    <?php if ((int) $part['is_active'] === 0): ?>
                        <dt>Status</dt>
                        <dd><span class="badge badge-muted">Retired</span></dd>
                    <?php endif; ?>
                </dl>

                <?php if (!empty($part['description'])): ?>
                    <hr>
                    <p class="text-sm"><?= nl2br_e((string) $part['description']) ?></p>
                <?php endif; ?>

                <?php if (!empty($part['notes'])): ?>
                    <hr>
                    <h4>Notes</h4>
                    <p class="text-sm text-muted"><?= nl2br_e((string) $part['notes']) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
