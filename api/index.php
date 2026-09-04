<?php

declare(strict_types=1);

/**
 * The JSON API front controller.
 *
 * There is exactly one endpoint, and the route says what to do:
 *
 *     api/index.php?r=assets.list
 *     api/index.php?r=assets.update_meter    (POST)
 *
 * "assets.update_meter" becomes App\Api\AssetsController::updateMeter(). That
 * is the whole routing table — there is no list of routes to keep in step with
 * the controllers, and a typo in a route name is a 404 rather than a call to
 * something unintended.
 *
 * This API exists to serve the application's own pages. Everything is behind
 * the session cookie, every non-GET call must carry the CSRF token, and every
 * page works without it — turn JavaScript off and the site still runs, just
 * with full page loads.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Acl;
use App\Auth;
use App\Csrf;
use App\Request;
use App\Response;
use App\Str;

Response::noStore();

// -----------------------------------------------------------------------------
// The route
// -----------------------------------------------------------------------------

$route = Request::string('r');

if ($route === '' || preg_match('/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$/', $route) !== 1) {
    Response::error('That is not a valid API route.', 'bad_route', 400);
}

[$resource, $action] = explode('.', $route, 2);

$class  = 'App\\Api\\' . Str::studly($resource) . 'Controller';
$method = lcfirst(Str::studly($action));

if (!class_exists($class) || !method_exists($class, $method)) {
    Response::error('There is no such API route.', 'not_found', 404);
}

// A controller only exposes what it lists. Anything else on the class —
// helpers, inherited methods — stays unreachable from a URL.
$exposed = method_exists($class, 'routes') ? (array) $class::routes() : [];

if (!in_array($action, $exposed, true)) {
    Response::error('There is no such API route.', 'not_found', 404);
}

// -----------------------------------------------------------------------------
// Who is asking
// -----------------------------------------------------------------------------

$public = method_exists($class, 'publicActions') ? (array) $class::publicActions() : [];

if (!in_array($action, $public, true) && !Auth::check()) {
    Response::error('Please sign in again.', 'unauthenticated', 401);
}

// -----------------------------------------------------------------------------
// Anything that changes data must prove it came from our own page
// -----------------------------------------------------------------------------

if (!Request::isGet()) {
    if (!Csrf::check()) {
        Response::error(
            'Your session token has expired. Reload the page and try again.',
            'invalid_token',
            419
        );
    }
}

// -----------------------------------------------------------------------------
// Run it
// -----------------------------------------------------------------------------

try {
    $result = $class::$method();

    $meta = [];

    if (is_array($result) && isset($result['__meta']) && is_array($result['__meta'])) {
        $meta = $result['__meta'];
        unset($result['__meta']);
    }

    Response::json($result, $meta);
} catch (Throwable $e) {
    log_error('API ' . $route . ' failed: ' . $e->getMessage(), [
        'trace' => $e->getTraceAsString(),
    ]);

    Response::error('Something went wrong. The error has been recorded.', 'server_error', 500);
}
