<?php
/**
 * Search results, grouped by what the thing is.
 */

use App\Dates;
use App\Status;
use App\View;

$hasQuery = mb_strlen($query) >= 2;
?>

<form method="get" action="<?= e(url('search.php')) ?>" class="card mb-5">
    <div class="card-body">
        <div class="flex gap-2 items-end flex-wrap">
            <div style="flex:1 1 280px">
                <?php View::partial('form-field', [
                    'name'        => 'q',
                    'label'       => 'Search everything',
                    'type'        => 'search',
                    'value'       => $query,
                    'noOld'       => true,
                    'placeholder' => 'A kart number, a part, a job, a work order…',
                    'attrs'       => ['autofocus' => true, 'maxlength' => 120],
                ]); ?>
            </div>
            <button type="submit" class="btn btn-primary">
                <?= icon('search', '', 17) ?> Search
            </button>
        </div>
    </div>
</form>

<?php if (!$hasQuery): ?>
    <?php View::partial('empty-state', [
        'icon'    => 'search',
        'title'   => 'What are you looking for?',
        'message' => 'Type at least two characters. ' . asset_word(true, true) . ', maintenance logs, work orders, '
                   . 'parts and inspections are all searched at once.',
    ]); ?>

<?php elseif ($total === 0): ?>
    <?php View::partial('empty-state', [
        'icon'    => 'search',
        'title'   => 'Nothing matches “' . $query . '”',
        'message' => 'Try fewer words, or part of a name — searching for "brake" finds '
                   . '"front brake pad set".',
    ]); ?>

<?php else: ?>
    <?php // ==================== Machines ==================== ?>
    <?php if (!empty($results[asset_word(true, true)])): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><?= icon('assets', '', 18) ?> <?= e(asset_word(true, true)) ?></h2>
                <span class="badge badge-muted"><?= count($results[asset_word(true, true)]) ?></span>
            </div>
            <div class="table-wrap">
                <table class="table is-stacked">
                    <thead>
                        <tr><th>Name</th><th>Tag</th><th>Category</th><th>Where</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results[asset_word(true, true)] as $row): ?>
                            <tr>
                                <td data-label="Name">
                                    <a class="cell-primary"
                                       href="<?= e(url('asset-view.php', ['id' => (int) $row['id']])) ?>">
                                        <?= e((string) $row['name']) ?>
                                    </a>
                                    <?php if ((string) $row['manufacturer'] !== '' || (string) $row['model'] !== ''): ?>
                                        <span class="cell-secondary">
                                            <?= e(trim((string) $row['manufacturer'] . ' ' . (string) $row['model'])) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Tag"><code><?= e((string) $row['asset_tag']) ?></code></td>
                                <td data-label="Category"><?= e((string) ($row['category_name'] ?? '')) ?></td>
                                <td data-label="Where"><?= e((string) ($row['location_name'] ?? '')) ?></td>
                                <td data-label="Status">
                                    <?php View::partial('status-badge', [
                                        'value' => (string) $row['status'], 'vocabulary' => 'asset',
                                    ]); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <?php // ==================== Maintenance logs ==================== ?>
    <?php if (!empty($results['Maintenance logs'])): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><?= icon('wrench', '', 18) ?> Maintenance logs</h2>
                <span class="badge badge-muted"><?= count($results['Maintenance logs']) ?></span>
            </div>
            <div class="table-wrap">
                <table class="table is-stacked">
                    <thead>
                        <tr><th>Job</th><th><?= e(asset_word(false, true)) ?></th><th>When</th><th>Who</th><?php if (costs_visible()): ?><th class="is-numeric">Cost</th><?php endif; ?></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results['Maintenance logs'] as $row): ?>
                            <tr>
                                <td data-label="Job">
                                    <a class="cell-primary"
                                       href="<?= e(url('log-view.php', ['id' => (int) $row['id']])) ?>">
                                        <?= e((string) $row['title']) ?>
                                    </a>
                                    <?php if ((string) $row['description'] !== ''): ?>
                                        <span class="cell-secondary">
                                            <?= e(str_limit((string) $row['description'], 100)) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="<?= attr(asset_word(false, true)) ?>"><?= e((string) $row['asset_name']) ?></td>
                                <td data-label="When"><?= e(Dates::date((string) $row['performed_at'])) ?></td>
                                <td data-label="Who"><?= e((string) $row['technician']) ?></td>
                                <?php if (costs_visible()): ?>
                                    <td data-label="Cost" class="is-numeric"><?= e(money($row['total_cost'])) ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <?php // ==================== Work orders ==================== ?>
    <?php if (!empty($results['Work orders'])): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><?= icon('work-order', '', 18) ?> Work orders</h2>
                <span class="badge badge-muted"><?= count($results['Work orders']) ?></span>
            </div>
            <div class="table-wrap">
                <table class="table is-stacked">
                    <thead>
                        <tr><th>Number</th><th>Title</th><th><?= e(asset_word(false, true)) ?></th><th>Status</th><th>Raised</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results['Work orders'] as $row): ?>
                            <tr>
                                <td data-label="Number">
                                    <a href="<?= e(url('workorder-view.php', ['id' => (int) $row['id']])) ?>">
                                        <code><?= e((string) $row['wo_number']) ?></code>
                                    </a>
                                </td>
                                <td data-label="Title">
                                    <span class="cell-primary"><?= e((string) $row['title']) ?></span>
                                </td>
                                <td data-label="<?= attr(asset_word(false, true)) ?>"><?= e((string) ($row['asset_name'] ?? '')) ?></td>
                                <td data-label="Status">
                                    <?php View::partial('status-badge', [
                                        'value' => (string) $row['status'], 'vocabulary' => 'work_order',
                                    ]); ?>
                                </td>
                                <td data-label="Raised"><?= e(Dates::date((string) $row['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <?php // ==================== Parts ==================== ?>
    <?php if (!empty($results['Parts'])): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><?= icon('package', '', 18) ?> Parts</h2>
                <span class="badge badge-muted"><?= count($results['Parts']) ?></span>
            </div>
            <div class="table-wrap">
                <table class="table is-stacked">
                    <thead>
                        <tr><th>Part</th><th>Number</th><th>Supplier</th><th class="is-numeric">On hand</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results['Parts'] as $row): ?>
                            <tr>
                                <td data-label="Part">
                                    <a class="cell-primary"
                                       href="<?= e(url('part-view.php', ['id' => (int) $row['id']])) ?>">
                                        <?= e((string) $row['name']) ?>
                                    </a>
                                    <?php if ((string) $row['category'] !== ''): ?>
                                        <span class="cell-secondary"><?= e((string) $row['category']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Number"><code><?= e((string) $row['part_number']) ?></code></td>
                                <td data-label="Supplier"><?= e((string) $row['supplier']) ?></td>
                                <td data-label="On hand" class="is-numeric">
                                    <span class="stock-count tone-<?= e(Status::stockState($row)) ?>">
                                        <?= e(decimal($row['quantity_on_hand'])) ?>
                                    </span>
                                    <span class="cell-secondary"><?= e((string) $row['unit_of_measure']) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <?php // ==================== Inspections ==================== ?>
    <?php if (!empty($results['Inspections'])): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><?= icon('clipboard-check', '', 18) ?> Inspections</h2>
                <span class="badge badge-muted"><?= count($results['Inspections']) ?></span>
            </div>
            <div class="table-wrap">
                <table class="table is-stacked">
                    <thead>
                        <tr><th>Checklist</th><th><?= e(asset_word(false, true)) ?></th><th>When</th><th>Result</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results['Inspections'] as $row): ?>
                            <tr>
                                <td data-label="Checklist">
                                    <a class="cell-primary"
                                       href="<?= e(url('inspection-view.php', ['id' => (int) $row['id']])) ?>">
                                        <?= e((string) $row['checklist_name']) ?>
                                    </a>
                                </td>
                                <td data-label="<?= attr(asset_word(false, true)) ?>"><?= e((string) $row['asset_name']) ?></td>
                                <td data-label="When"><?= e(Dates::date((string) $row['started_at'])) ?></td>
                                <td data-label="Result">
                                    <?php View::partial('status-badge', [
                                        'value' => (string) $row['status'], 'vocabulary' => 'inspection',
                                    ]); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
