<?php

declare(strict_types=1);

namespace App;

/**
 * Page-number arithmetic and link building for list screens.
 *
 * Construct it from a total row count; it works out the rest and preserves
 * whatever filters are already in the query string.
 */
final class Paginator
{
    private int $total;

    private int $perPage;

    private int $currentPage;

    private string $baseUrl;

    /** @var array<string, mixed> query parameters to carry into every link */
    private array $query;

    private string $pageParam;

    /**
     * @param array<string, mixed> $query
     */
    public function __construct(
        int $total,
        int $perPage,
        int $currentPage,
        string $baseUrl = '',
        array $query = [],
        string $pageParam = 'page'
    ) {
        $this->total     = max(0, $total);
        $this->perPage   = max(1, $perPage);
        $this->pageParam = $pageParam;

        $lastPage = $this->lastPage();

        $this->currentPage = max(1, min($currentPage, $lastPage));
        $this->baseUrl     = $baseUrl !== '' ? $baseUrl : Request::script();

        unset($query[$pageParam], $query[Csrf::FIELD]);
        $this->query = $query;
    }

    /**
     * Build from the current request, reading page and per_page from the query
     * string and carrying every other parameter forward.
     */
    public static function fromRequest(int $total, ?int $perPage = null, string $baseUrl = ''): self
    {
        return new self(
            $total,
            $perPage ?? Request::perPage(),
            Request::page(),
            $baseUrl,
            $_GET
        );
    }

    public function total(): int
    {
        return $this->total;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function currentPage(): int
    {
        return $this->currentPage;
    }

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    /** SQL OFFSET for the current page. */
    public function offset(): int
    {
        return ($this->currentPage - 1) * $this->perPage;
    }

    /** SQL LIMIT — same as perPage(), named for readability at the call site. */
    public function limit(): int
    {
        return $this->perPage;
    }

    /** 1-based index of the first row on this page, or 0 when empty. */
    public function from(): int
    {
        return $this->total === 0 ? 0 : $this->offset() + 1;
    }

    /** 1-based index of the last row on this page. */
    public function to(): int
    {
        return $this->total === 0 ? 0 : min($this->offset() + $this->perPage, $this->total);
    }

    public function hasPages(): bool
    {
        return $this->lastPage() > 1;
    }

    public function isEmpty(): bool
    {
        return $this->total === 0;
    }

    public function onFirstPage(): bool
    {
        return $this->currentPage <= 1;
    }

    public function onLastPage(): bool
    {
        return $this->currentPage >= $this->lastPage();
    }

    public function previousPage(): ?int
    {
        return $this->onFirstPage() ? null : $this->currentPage - 1;
    }

    public function nextPage(): ?int
    {
        return $this->onLastPage() ? null : $this->currentPage + 1;
    }

    /** URL for a given page number, keeping the current filters. */
    public function urlFor(int $page): string
    {
        $page  = max(1, min($page, $this->lastPage()));
        $query = $this->query;
        $query[$this->pageParam] = $page;

        return url($this->baseUrl, $query);
    }

    public function previousUrl(): ?string
    {
        $page = $this->previousPage();

        return $page === null ? null : $this->urlFor($page);
    }

    public function nextUrl(): ?string
    {
        $page = $this->nextPage();

        return $page === null ? null : $this->urlFor($page);
    }

    /**
     * The page numbers to render, with null marking an elided gap.
     *
     * With $each = 2 and 20 pages on page 10 you get:
     *   1, null, 8, 9, 10, 11, 12, null, 20
     *
     * @return list<int|null>
     */
    public function window(int $each = 2): array
    {
        $last    = $this->lastPage();
        $current = $this->currentPage;

        if ($last <= (($each * 2) + 5)) {
            return range(1, $last);
        }

        $pages = [1];

        $start = max(2, $current - $each);
        $end   = min($last - 1, $current + $each);

        // Keep the window a consistent width near the ends.
        if ($current - $each <= 2) {
            $end = min($last - 1, ($each * 2) + 2);
        }

        if ($current + $each >= $last - 1) {
            $start = max(2, $last - ($each * 2) - 1);
        }

        if ($start > 2) {
            $pages[] = null;
        }

        for ($page = $start; $page <= $end; $page++) {
            $pages[] = $page;
        }

        if ($end < $last - 1) {
            $pages[] = null;
        }

        $pages[] = $last;

        return $pages;
    }

    /** "Showing 26–50 of 312 maintenance logs" */
    public function summary(string $singular = 'record', ?string $plural = null): string
    {
        $plural = $plural ?? $singular . 's';

        if ($this->total === 0) {
            return 'No ' . $plural . ' found';
        }

        if ($this->total === 1) {
            return 'Showing 1 ' . $singular;
        }

        if (!$this->hasPages()) {
            return 'Showing all ' . number_format($this->total) . ' ' . $plural;
        }

        return 'Showing ' . number_format($this->from()) . '–' . number_format($this->to())
             . ' of ' . number_format($this->total) . ' ' . $plural;
    }

    /**
     * Everything a view needs, as a plain array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'total'        => $this->total,
            'per_page'     => $this->perPage,
            'current_page' => $this->currentPage,
            'last_page'    => $this->lastPage(),
            'from'         => $this->from(),
            'to'           => $this->to(),
            'has_pages'    => $this->hasPages(),
        ];
    }
}
