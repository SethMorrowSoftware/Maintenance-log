<?php
/**
 * Variables: $paginator (App\Paginator), $singular, $plural
 */

use App\Paginator;

if (!isset($paginator) || !$paginator instanceof Paginator) {
    return;
}

$singular = $singular ?? 'record';
$plural   = $plural   ?? $singular . 's';
?>
<div class="pagination-bar no-print">
    <div><?= e($paginator->summary($singular, $plural)) ?></div>

    <?php if ($paginator->hasPages()): ?>
        <nav aria-label="Pagination">
            <ul class="pagination">
                <li>
                    <?php $prev = $paginator->previousUrl(); ?>
                    <a class="page-link<?= $prev === null ? ' is-disabled' : '' ?>"
                       href="<?= e($prev ?? '#') ?>"
                       aria-label="Previous page"<?= $prev === null ? ' aria-disabled="true" tabindex="-1"' : '' ?>>
                        <?= icon('chevron-left', '', 15) ?>
                    </a>
                </li>

                <?php foreach ($paginator->window() as $page): ?>
                    <li>
                        <?php if ($page === null): ?>
                            <span class="page-gap" aria-hidden="true">…</span>
                        <?php elseif ($page === $paginator->currentPage()): ?>
                            <span class="page-link is-active" aria-current="page"><?= (int) $page ?></span>
                        <?php else: ?>
                            <a class="page-link" href="<?= e($paginator->urlFor((int) $page)) ?>"
                               aria-label="Page <?= (int) $page ?>"><?= (int) $page ?></a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>

                <li>
                    <?php $next = $paginator->nextUrl(); ?>
                    <a class="page-link<?= $next === null ? ' is-disabled' : '' ?>"
                       href="<?= e($next ?? '#') ?>"
                       aria-label="Next page"<?= $next === null ? ' aria-disabled="true" tabindex="-1"' : '' ?>>
                        <?= icon('chevron-right', '', 15) ?>
                    </a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>
