<?php

declare(strict_types=1);

/**
 * The page Apache shows when it, rather than the application, refuses a request.
 *
 * A request for a file that does not exist, or for one the server has been told
 * to deny, never reaches any of the application's pages — Apache handles it and
 * shows its own bare grey error page. Pointing ErrorDocument here instead means
 * somebody who mistypes a URL gets a page that looks like the rest of the site
 * and has a way back to the dashboard on it.
 *
 * Wire it up by uncommenting the ErrorDocument lines at the bottom of .htaccess.
 * Nothing here needs a login: it must work for somebody who is not signed in,
 * which is exactly who is most likely to hit a 403.
 */

require __DIR__ . '/app/bootstrap.php';

use App\Request;
use App\Response;

// Apache sets REDIRECT_STATUS; the query parameter is the manual fallback.
$code = (int) ($_SERVER['REDIRECT_STATUS'] ?? 0);

if ($code === 0) {
    $code = Request::int('code');
}

// Only render codes there is actually a page for, so a stray ?code=1 cannot
// produce a nonsense heading.
if (!in_array($code, [400, 403, 404, 419, 429, 500, 503], true)) {
    $code = 404;
}

Response::abortPage($code);
