<?php

declare(strict_types=1);

namespace App;

use RuntimeException;
use Throwable;

/**
 * Plain PHP templating.
 *
 * A view is a .php file under app/Views. It is rendered in an isolated scope
 * with the supplied data extracted into local variables, captured, and then
 * handed to a layout as $content.
 *
 *     View::render('assets/index', ['title' => 'Assets', 'assets' => $rows]);
 *
 * Views never query the database and never emit unescaped data — use e().
 */
final class View
{
    /** @var array<string, mixed> data available to every view */
    private static array $shared = [];

    /** @var array<string, string> captured named sections */
    private static array $sections = [];

    /** @var list<string> open section stack */
    private static array $sectionStack = [];

    private function __construct()
    {
    }

    private static function basePath(): string
    {
        return rtrim((string) (defined('APP_PATH') ? APP_PATH : __DIR__), '/') . '/Views';
    }

    /**
     * Absolute path of a view name. Rejects traversal outright.
     */
    public static function path(string $view): string
    {
        $view = trim($view, '/');

        if ($view === '' || strpos($view, '..') !== false || strpos($view, "\0") !== false) {
            throw new RuntimeException('Invalid view name.');
        }

        if (!preg_match('#^[A-Za-z0-9_\-/]+$#', $view)) {
            throw new RuntimeException('Invalid view name: ' . $view);
        }

        return self::basePath() . '/' . $view . '.php';
    }

    public static function exists(string $view): bool
    {
        try {
            return is_file(self::path($view));
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Make a value available to every view and layout.
     *
     * @param mixed $value
     */
    public static function share(string $key, $value): void
    {
        self::$shared[$key] = $value;
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function shareMany(array $values): void
    {
        self::$shared = array_merge(self::$shared, $values);
    }

    /**
     * Render a view inside a layout and echo the result.
     *
     * Pass null as the layout to render the view on its own.
     *
     * @param array<string, mixed> $data
     */
    public static function render(string $view, array $data = [], ?string $layout = 'layout'): void
    {
        echo self::capture($view, $data, $layout);
    }

    /**
     * Render to a string instead of echoing.
     *
     * @param array<string, mixed> $data
     */
    public static function capture(string $view, array $data = [], ?string $layout = 'layout'): string
    {
        $data    = array_merge(self::$shared, $data);
        $content = self::renderFile(self::path($view), $data);

        if ($layout === null) {
            return $content;
        }

        // Defaults every layout can rely on.
        $layoutData = array_merge([
            'title'       => 'RideLog',
            'content'     => $content,
            'activeNav'   => '',
            'breadcrumbs' => [],
            'pageActions' => '',
            'bodyClass'   => '',
            'extraCss'    => [],
            'extraJs'     => [],
            'subtitle'    => '',
        ], $data);

        $layoutData['content'] = $content;

        $layoutPath = self::path($layout);

        if (!is_file($layoutPath)) {
            // Without a layout the page still has to render.
            return $content;
        }

        // An ordinary page view is also the moment to see whether a timed check
        // has slipped past its due time, on a site with no frequent cron. It
        // runs after the page has gone out, so nobody waits for it.
        if ($layout === 'layout' && !self::$tickArmed && Auth::check()) {
            self::$tickArmed = true;

            register_shutdown_function(static function (): void {
                if (function_exists('fastcgi_finish_request')) {
                    @fastcgi_finish_request();
                }

                Checks::tick();
            });
        }

        return self::renderFile($layoutPath, $layoutData);
    }

    /** Whether this request has already queued the checks tick. */
    private static bool $tickArmed = false;

    /**
     * Render a partial and echo it. Partials live in app/Views/partials.
     *
     * @param array<string, mixed> $data
     */
    public static function partial(string $name, array $data = []): void
    {
        echo self::partialString($name, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function partialString(string $name, array $data = []): string
    {
        $path = self::path('partials/' . trim($name, '/'));

        if (!is_file($path)) {
            if (Config::get('app.debug', false)) {
                return '<!-- missing partial: ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ' -->';
            }

            return '';
        }

        return self::renderFile($path, array_merge(self::$shared, $data));
    }

    /**
     * Include a view file in an isolated scope and capture its output.
     *
     * @param array<string, mixed> $data
     */
    private static function renderFile(string $path, array $data): string
    {
        if (!is_file($path)) {
            throw new RuntimeException('View not found: ' . basename($path));
        }

        $level = ob_get_level();
        ob_start();

        try {
            // A closure keeps $path and $data out of the template's scope
            // except through extract(), so a view cannot clobber them.
            (static function (string $__path, array $__data): void {
                extract($__data, EXTR_SKIP);
                require $__path;
            })($path, $data);
        } catch (Throwable $e) {
            // Discard the half-rendered output so an exception page is clean.
            while (ob_get_level() > $level) {
                ob_end_clean();
            }

            throw $e;
        }

        $output = ob_get_clean();

        return $output === false ? '' : $output;
    }

    // -------------------------------------------------------------------------
    // Sections — let a view push extra markup into the layout
    // -------------------------------------------------------------------------

    /**
     * Begin capturing a named section.
     *
     *     <?php View::startSection('scripts'); ?>
     *     <script src="..."></script>
     *     <?php View::endSection(); ?>
     */
    public static function startSection(string $name): void
    {
        self::$sectionStack[] = $name;
        ob_start();
    }

    /** Finish the section opened most recently. */
    public static function endSection(): void
    {
        if (self::$sectionStack === []) {
            return;
        }

        $name    = (string) array_pop(self::$sectionStack);
        $content = ob_get_clean();

        if ($content === false) {
            $content = '';
        }

        self::$sections[$name] = (self::$sections[$name] ?? '') . $content;
    }

    /** Output a captured section, or the fallback when it is empty. */
    public static function section(string $name, string $default = ''): string
    {
        return self::$sections[$name] ?? $default;
    }

    public static function hasSection(string $name): bool
    {
        return isset(self::$sections[$name]) && self::$sections[$name] !== '';
    }

    /** Append directly to a section without capturing output. */
    public static function pushSection(string $name, string $content): void
    {
        self::$sections[$name] = (self::$sections[$name] ?? '') . $content;
    }

    /** Reset all state. Used by tests. */
    public static function reset(): void
    {
        self::$shared       = [];
        self::$sections     = [];
        self::$sectionStack = [];
    }
}
