<?php
/**
 * Every report renders through this one page.
 *
 * Reports return columns, rows and an optional totals row in a fixed shape, so
 * this file is the only place that knows how a figure should look. The CSV gets
 * the raw numbers; the screen gets the formatting.
 */

use App\Acl;
use App\Dates;
use App\Reports;
use App\Status;
use App\View;

$printing = $printing ?? false;
$columns  = $result['columns'];
$rows     = $result['rows'];
$totals   = $result['totals'] ?? [];
$chart    = $result['chart'] ?? null;

/**
 * Turn one cell into what a person should read.
 *
 * @param mixed $value
 */
$render = static function ($value, string $format): string {
    if ($value === null || $value === '') {
        return '<span class="text-subtle">&mdash;</span>';
    }

    switch ($format) {
        case 'money':    return e(money($value));
        case 'number':   return e(num($value));
        case 'decimal':  return e(decimal($value));
        case 'percent':  return e(num($value, 1)) . '%';
        case 'hours':    return e(Dates::humanHours((float) $value));
        case 'duration': return e(Dates::humanDuration((int) $value));
        case 'datetime': return e(Dates::datetime((string) $value));
        case 'date':     return e(Dates::date((string) $value));
        case 'date_only':return e(Dates::dateOnly((string) $value));
        case 'meter':    return e(decimal($value));
        case 'log_type':     return badge((string) $value, 'log_type');
        case 'asset_status': return badge((string) $value, 'asset');
        case 'role':         return e(Acl::roleLabel((string) $value));
        default:         return e((string) $value);
    }
};
?>

<?php // ==================== Which report ==================== ?>
<?php if (!$printing): ?>
    <nav class="report-tabs no-print" aria-label="Reports">
        <?php foreach ($catalogue as $key => $meta): ?>
            <a class="report-tab<?= $key === $report ? ' is-active' : '' ?>"
               href="<?= e(url('reports.php', array_filter([
                   'report' => $key,
                   'from'   => $filters['from'],
                   'to'     => $filters['to'],
               ]))) ?>"
               <?= $key === $report ? 'aria-current="page"' : '' ?>>
                <?= icon((string) $meta['icon'], '', 17) ?>
                <span><?= e((string) $meta['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php // ==================== Filters ==================== ?>
    <?php
    // On a phone the filters fold away unless one is in use.
    $reportFiltered = (int) $filters['asset_id'] > 0 || (int) $filters['category_id'] > 0 || (int) $filters['location_id'] > 0
        || (string) $filters['log_type'] !== '' || (int) $filters['user_id'] > 0 || (string) $filters['status'] !== '';
    ?>
    <form method="get" action="<?= e(url('reports.php')) ?>" class="filter-bar no-print<?= $reportFiltered ? '' : ' is-collapsed' ?>">
        <button type="button" class="btn btn-secondary btn-sm filter-toggle" data-filter-toggle
                aria-expanded="<?= $reportFiltered ? 'true' : 'false' ?>">
            <span><?= icon('filter', '', 15) ?> Filters<?= $reportFiltered ? ' (on)' : '' ?></span>
            <?= icon('chevron-down', '', 15) ?>
        </button>
        <input type="hidden" name="report" value="<?= e($report) ?>">

        <?php if ($usesDates): ?>
            <?php View::partial('form-field', [
                'name'    => 'preset',
                'label'   => 'Period',
                'type'    => 'select',
                'value'   => $preset,
                'options' => $preset === 'custom'
                    ? array_merge(['custom' => 'Between two dates'], Dates::presets())
                    : Dates::presets(),
                'empty'   => null,
                'noOld'   => true,
                'attrs'   => ['data-reveal' => 'period'],
            ]); ?>

            <div data-reveal-for="period" data-reveal-when="custom" class="filter-dates">
                <?php View::partial('form-field', [
                    'name'  => 'from',
                    'label' => 'From',
                    'type'  => 'date',
                    'value' => $filters['from'],
                    'noOld' => true,
                ]); ?>
                <?php View::partial('form-field', [
                    'name'  => 'to',
                    'label' => 'To',
                    'type'  => 'date',
                    'value' => $filters['to'],
                    'noOld' => true,
                ]); ?>
            </div>
        <?php endif; ?>

        <?php
        $assetPairs = [];

        foreach (($assets ?? []) as $assetRow) {
            $assetPairs[(int) $assetRow['id']] = (string) $assetRow['name'] . ' — ' . (string) $assetRow['asset_tag'];
        }
        ?>
        <?php if ($assetPairs !== []): ?>
            <?php View::partial('form-field', [
                'name'    => 'asset_id',
                'label'   => asset_word(false, true),
                'type'    => 'select',
                'value'   => $filters['asset_id'],
                'options' => $assetPairs,
                'empty'   => 'All ' . asset_word(true),
                'noOld'   => true,
            ]); ?>
        <?php endif; ?>

        <?php View::partial('form-field', [
            'name'    => 'category_id',
            'label'   => 'Category',
            'type'    => 'select',
            'value'   => $filters['category_id'],
            'options' => $categories,
            'empty'   => 'All',
            'noOld'   => true,
        ]); ?>

        <?php View::partial('form-field', [
            'name'    => 'location_id',
            'label'   => 'Where',
            'type'    => 'select',
            'value'   => $filters['location_id'],
            'options' => $locations,
            'empty'   => 'Everywhere',
            'noOld'   => true,
        ]); ?>

        <?php if ($usesType): ?>
            <?php View::partial('form-field', [
                'name'    => 'log_type',
                'label'   => 'Type of work',
                'type'    => 'select',
                'value'   => $filters['log_type'],
                'options' => Status::options('log_type'),
                'empty'   => 'All',
                'noOld'   => true,
            ]); ?>
        <?php endif; ?>

        <?php if ($usesWho): ?>
            <?php View::partial('form-field', [
                'name'    => 'user_id',
                'label'   => 'Who',
                'type'    => 'select',
                'value'   => $filters['user_id'],
                'options' => $technicians,
                'empty'   => 'Anyone',
                'noOld'   => true,
            ]); ?>
        <?php endif; ?>

        <?php if ($usesState): ?>
            <?php View::partial('form-field', [
                'name'    => 'status',
                'label'   => 'Status',
                'type'    => 'select',
                'value'   => $filters['status'],
                'options' => Status::options('asset'),
                'empty'   => 'Any',
                'noOld'   => true,
            ]); ?>
        <?php endif; ?>

        <div class="filter-actions">
            <button type="submit" class="btn btn-primary btn-sm">
                <?= icon('filter', '', 15) ?> Show it
            </button>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('reports.php', ['report' => $report])) ?>">
                Reset
            </a>
        </div>
    </form>
<?php endif; ?>

<?php if ($printing): ?>
    <h1 class="print-title"><?= e((string) $catalogue[$report]['label']) ?></h1>
<?php endif; ?>

<?php // ==================== The chart ==================== ?>
<?php if ($chart !== null && $rows !== []): ?>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><?= e((string) $chart['title']) ?></h2>
        </div>
        <div class="card-body">
            <div data-chart="<?= e((string) $chart['type']) ?>" data-chart-src="report-chart"
                 data-chart-height="240"
                 data-chart-format="<?= e((string) ($chart['series'][0]['format'] ?? '')) ?>"
                 data-chart-title="<?= attr((string) $chart['title']) ?>"></div>
            <script type="application/json" id="report-chart"><?= js([
                'labels' => $chart['labels'],
                'series' => array_map(static function (array $series): array {
                    return ['name' => $series['label'], 'values' => $series['values']];
                }, $chart['series']),
            ]) ?></script>
        </div>
    </div>
<?php endif; ?>

<?php // ==================== The table ==================== ?>
<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title"><?= e((string) $catalogue[$report]['label']) ?></h2>
            <p class="card-subtitle">
                <?= (int) count($rows) ?> row<?= count($rows) === 1 ? '' : 's' ?>
                <?php if ($usesDates && ($filters['from'] !== '' || $filters['to'] !== '')): ?>
                    &middot;
                    <?= $filters['from'] !== '' ? e(Dates::dateOnly($filters['from'])) : 'the beginning' ?>
                    to
                    <?= $filters['to'] !== '' ? e(Dates::dateOnly($filters['to'])) : 'today' ?>
                <?php elseif ($usesDates): ?>
                    &middot; all time
                <?php endif; ?>
            </p>
        </div>
    </div>

    <?php if ($rows === []): ?>
        <div class="card-body">
            <?php View::partial('empty-state', [
                'icon'    => (string) $catalogue[$report]['icon'],
                'title'   => 'Nothing to show',
                'message' => (string) ($result['empty'] ?? 'No records match those filters.'),
            ]); ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table table-compact<?= $printing ? '' : ' table-sortable is-stacked' ?>">
                <thead>
                    <tr>
                        <?php foreach ($columns as $column): ?>
                            <th<?= ($column['align'] ?? '') === 'right' ? ' class="is-numeric"' : '' ?>>
                                <?= e((string) $column['label']) ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php foreach ($columns as $index => $column): ?>
                                <td class="<?= ($column['align'] ?? '') === 'right' ? 'is-numeric' : '' ?><?= $index === 0 ? ' is-row-title' : '' ?>"
                                    data-label="<?= attr((string) $column['label']) ?>">
                                    <?= $render($row[$column['key']] ?? null, (string) ($column['format'] ?? 'text')) ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php if ($totals !== []): ?>
                    <tfoot>
                        <tr>
                            <?php foreach ($columns as $index => $column): ?>
                                <td class="<?= ($column['align'] ?? '') === 'right' ? 'is-numeric' : '' ?><?= $index === 0 ? ' is-row-title' : (array_key_exists($column['key'], $totals) ? '' : ' is-empty-mobile') ?>"
                                    data-label="<?= attr((string) $column['label']) ?>">
                                    <?php if (array_key_exists($column['key'], $totals)): ?>
                                        <strong><?= $render(
                                            $totals[$column['key']],
                                            (string) ($column['format'] ?? 'text')
                                        ) ?></strong>
                                    <?php elseif ($index === 0): ?>
                                        <strong>Total</strong>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if (!$printing && count($rows) >= 5000): ?>
    <p class="text-sm text-muted mt-3">
        <?= icon('info', '', 15) ?>
        Only the first 5,000 rows are shown. Narrow the dates to see the rest.
    </p>
<?php endif; ?>
