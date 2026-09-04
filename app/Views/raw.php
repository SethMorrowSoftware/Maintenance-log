<?php
/**
 * Renders pre-built HTML inside a layout.
 *
 * For the handful of pages that assemble their markup procedurally rather than
 * in a template. The HTML is trusted — it comes from the page itself, never
 * from user input.
 *
 * Variables: $html
 */

echo $html ?? '';
