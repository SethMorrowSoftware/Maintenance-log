<?php

declare(strict_types=1);

namespace App;

/**
 * Inline SVG icons.
 *
 * Icons are inlined rather than loaded from a sprite or a font so there is no
 * extra request, no flash of missing glyphs, and no external dependency. Every
 * path is drawn on a 24x24 grid with no fill and a currentColor stroke, so an
 * icon takes the colour of whatever it sits inside.
 */
final class Icon
{
    /**
     * name => SVG path data (everything between <svg> and </svg>).
     *
     * @var array<string, string>
     */
    private const PATHS = [
        // --- Navigation ------------------------------------------------------
        'dashboard'      => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
        'home'           => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V20a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V9.5"/><path d="M9.5 21v-6h5v6"/>',
        'menu'           => '<path d="M3 6h18"/><path d="M3 12h18"/><path d="M3 18h18"/>',
        'grid'           => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'list'           => '<path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M3.5 6h.01"/><path d="M3.5 12h.01"/><path d="M3.5 18h.01"/>',
        'folder'         => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2.5h8a2 2 0 0 1 2 2V18a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',

        // --- Domain ----------------------------------------------------------
        'assets'         => '<path d="M3 20h18"/><path d="M5 20V9l7-5 7 5v11"/><path d="M9.5 20v-5h5v5"/><path d="M9.5 11h5"/>',
        'kart'           => '<circle cx="6.5" cy="17" r="2.5"/><circle cx="17.5" cy="17" r="2.5"/><path d="M4 17h-.5A1.5 1.5 0 0 1 2 15.5V13l3-1 1.5-3H12l3 4h3.5A2.5 2.5 0 0 1 21 15.5V17h-1"/><path d="M9 17h6"/><path d="M8 9V6.5h4"/>',
        'ride'           => '<circle cx="12" cy="10" r="7"/><circle cx="12" cy="10" r="2"/><path d="M12 3v3"/><path d="M12 14v3"/><path d="M5 10h3"/><path d="M16 10h3"/><path d="M4 21h16"/><path d="M8.5 21 12 17l3.5 4"/>',
        'wrench'         => '<path d="M15.5 7.5a4.5 4.5 0 0 1-5.9 4.28L5 16.4a2.1 2.1 0 1 0 2.97 2.97l4.62-4.6A4.5 4.5 0 1 0 15.5 7.5z"/><path d="m14.8 4.2 2.5-1.2 3.7 3.7-1.2 2.5"/>',
        'tool'           => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94z"/>',
        'work-order'     => '<path d="M6 3h9l4 4v13a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"/><path d="M14 3v4h4"/><path d="M8.5 13h7"/><path d="M8.5 16.5h4"/>',
        'clipboard'      => '<path d="M9 3h6a1 1 0 0 1 1 1v1H8V4a1 1 0 0 1 1-1z"/><path d="M8 5H6.5A1.5 1.5 0 0 0 5 6.5v13A1.5 1.5 0 0 0 6.5 21h11a1.5 1.5 0 0 0 1.5-1.5v-13A1.5 1.5 0 0 0 17.5 5H16"/>',
        'clipboard-check'=> '<path d="M9 3h6a1 1 0 0 1 1 1v1H8V4a1 1 0 0 1 1-1z"/><path d="M8 5H6.5A1.5 1.5 0 0 0 5 6.5v13A1.5 1.5 0 0 0 6.5 21h11a1.5 1.5 0 0 0 1.5-1.5v-13A1.5 1.5 0 0 0 17.5 5H16"/><path d="m9 13.5 2 2 4-4"/>',
        'checklist'      => '<path d="M4 6.5 5.5 8 8 5.5"/><path d="M4 12.5 5.5 14 8 11.5"/><path d="M4 18.5 5.5 20 8 17.5"/><path d="M11.5 7h9"/><path d="M11.5 13h9"/><path d="M11.5 19h9"/>',
        'parts'          => '<path d="M12 2.5 20 7v10l-8 4.5L4 17V7z"/><path d="m4 7 8 4.5L20 7"/><path d="M12 11.5V21"/>',
        'package'        => '<path d="M12 2.5 20 7v10l-8 4.5L4 17V7z"/><path d="m4 7 8 4.5L20 7"/><path d="M12 11.5V21"/><path d="m8 4.75 8 4.5"/>',
        'box'            => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M3 11h18"/><path d="M9 7V4h6v3"/>',
        'truck'          => '<path d="M3 6h11v10H3z"/><path d="M14 9h3.5l2.5 3v4h-6z"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
        'gauge'          => '<path d="M4.5 18a9 9 0 1 1 15 0"/><path d="m12 14 3.5-4"/><circle cx="12" cy="14" r="1.2"/>',
        'fuel'           => '<path d="M4 21V5a2 2 0 0 1 2-2h5a2 2 0 0 1 2 2v16"/><path d="M3 21h11"/><path d="M4 10h9"/><path d="M13 8h3.5a1.5 1.5 0 0 1 1.5 1.5V16a2 2 0 0 0 3 1.7"/><path d="M17.5 5.5 20 8"/>',
        'activity'       => '<path d="M3 12h4l3 8 4-16 3 8h4"/>',

        // --- Reporting -------------------------------------------------------
        'report'         => '<path d="M6 3h9l4 4v13a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"/><path d="M14 3v4h4"/><path d="M9 17v-3"/><path d="M12 17v-6"/><path d="M15 17v-4"/>',
        'chart-bar'      => '<path d="M3 21h18"/><rect x="4.5" y="12" width="4" height="6" rx="1"/><rect x="10" y="7" width="4" height="11" rx="1"/><rect x="15.5" y="4" width="4" height="14" rx="1"/>',
        'chart-line'     => '<path d="M4 4v16h16"/><path d="m7 15 3.5-4 3 2.5L19 7"/>',
        'trending-up'    => '<path d="m3 17 6-6 4 4 8-8"/><path d="M15 7h6v6"/>',
        'trending-down'  => '<path d="m3 7 6 6 4-4 8 8"/><path d="M15 17h6v-6"/>',
        'dollar-sign'    => '<path d="M12 2v20"/><path d="M17 6.5C17 4.6 14.8 3.5 12 3.5S7 4.6 7 6.5s2 2.8 5 3.5 5 1.6 5 3.5-2.2 3-5 3-5-1.1-5-3"/>',

        // --- People ----------------------------------------------------------
        'user'           => '<circle cx="12" cy="8" r="4"/><path d="M4.5 20.5a7.5 7.5 0 0 1 15 0"/>',
        'users'          => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.75a3.5 3.5 0 0 1 0 6.5"/><path d="M17.5 14.2A6.5 6.5 0 0 1 21.5 20"/>',
        'shield'         => '<path d="M12 3l7.5 3v5.5c0 4.6-3.1 8.2-7.5 9.5-4.4-1.3-7.5-4.9-7.5-9.5V6z"/><path d="m9 12 2 2 4-4"/>',
        'lock'           => '<rect x="4.5" y="10" width="15" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/><path d="M12 14v2.5"/>',
        'key'            => '<circle cx="8" cy="15" r="4"/><path d="m11 12 9-9"/><path d="m17 6 2.5 2.5"/><path d="m14.5 8.5 2 2"/>',
        'login'          => '<path d="M14 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/>',
        'logout'         => '<path d="M10 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',

        // --- Actions ---------------------------------------------------------
        'plus'           => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'plus-circle'    => '<circle cx="12" cy="12" r="9"/><path d="M12 8.5v7"/><path d="M8.5 12h7"/>',
        'minus'          => '<path d="M5 12h14"/>',
        'edit'           => '<path d="M11 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/>',
        'trash'          => '<path d="M3.5 6h17"/><path d="M8.5 6V4.5A1.5 1.5 0 0 1 10 3h4a1.5 1.5 0 0 1 1.5 1.5V6"/><path d="M6 6v13.5A1.5 1.5 0 0 0 7.5 21h9a1.5 1.5 0 0 0 1.5-1.5V6"/><path d="M10.5 10.5v6"/><path d="M13.5 10.5v6"/>',
        'save'           => '<path d="M5 3h11l3 3v13a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M8 3v5h7V3"/><path d="M7.5 13h9v8h-9z"/>',
        'x'              => '<path d="M6 6l12 12"/><path d="M18 6 6 18"/>',
        'check'          => '<path d="m4.5 12.5 5 5 10-10"/>',
        'check-circle'   => '<circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 4.5-5"/>',
        'circle'         => '<circle cx="12" cy="12" r="9"/>',
        'copy'           => '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M6 15H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v1"/>',
        'link'           => '<path d="M10 13.5a4 4 0 0 0 5.7.3l3-3a4 4 0 0 0-5.7-5.7l-1.7 1.7"/><path d="M14 10.5a4 4 0 0 0-5.7-.3l-3 3a4 4 0 0 0 5.7 5.7l1.7-1.7"/>',
        'refresh'        => '<path d="M20 11a8 8 0 0 0-13.7-5.2L3 9"/><path d="M4 13a8 8 0 0 0 13.7 5.2L21 15"/><path d="M3 4v5h5"/><path d="M21 20v-5h-5"/>',
        'play'           => '<path d="M7 4.5v15l13-7.5z"/>',
        'pause'          => '<rect x="6.5" y="4.5" width="4" height="15" rx="1"/><rect x="13.5" y="4.5" width="4" height="15" rx="1"/>',
        'star'           => '<path d="m12 3.5 2.7 5.6 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1L3.2 10l6.1-.9z"/>',
        'more-vertical'  => '<circle cx="12" cy="5.5" r="1.3"/><circle cx="12" cy="12" r="1.3"/><circle cx="12" cy="18.5" r="1.3"/>',
        'archive'        => '<rect x="3" y="4" width="18" height="4" rx="1"/><path d="M5 8v11a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8"/><path d="M10 12h4"/>',
        'sparkle'        => '<path d="m12 3 1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z"/><path d="M18.5 16.5 19 18l1.5.5L19 19l-.5 1.5L18 19l-1.5-.5L18 18z"/>',

        // --- Status ----------------------------------------------------------
        'alert-triangle' => '<path d="M10.3 4.3 2.8 17.2A2 2 0 0 0 4.5 20.2h15a2 2 0 0 0 1.7-3L13.7 4.3a2 2 0 0 0-3.4 0z"/><path d="M12 9.5v4"/><path d="M12 17h.01"/>',
        'alert-circle'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7.5v5"/><path d="M12 16h.01"/>',
        'info'           => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5.5"/><path d="M12 8h.01"/>',
        'help-circle'    => '<circle cx="12" cy="12" r="9"/><path d="M9.6 9.5a2.5 2.5 0 0 1 4.9.6c0 1.7-2.5 2.4-2.5 2.4"/><path d="M12 17h.01"/>',
        'clock'          => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5.5l3.5 2"/>',
        'history'        => '<path d="M3.5 9A9 9 0 1 1 3 12"/><path d="M3 4v5h5"/><path d="M12 7.5V12l3.5 2"/>',
        'calendar'       => '<rect x="3.5" y="5" width="17" height="16" rx="2"/><path d="M3.5 10h17"/><path d="M8 3v4"/><path d="M16 3v4"/>',
        'bell'           => '<path d="M18 8.5a6 6 0 0 0-12 0c0 5-2 6.5-2 6.5h16s-2-1.5-2-6.5"/><path d="M13.7 19a2 2 0 0 1-3.4 0"/>',

        // --- Data ------------------------------------------------------------
        'search'         => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m20 20-4.9-4.9"/>',
        'filter'         => '<path d="M3.5 5h17l-6.5 8v6l-4 2v-8z"/>',
        'sort'           => '<path d="M7 4v16"/><path d="m3.5 8 3.5-4 3.5 4"/><path d="M17 20V4"/><path d="m13.5 16 3.5 4 3.5-4"/>',
        'download'       => '<path d="M12 3v12"/><path d="m7.5 11 4.5 4.5L16.5 11"/><path d="M4 20h16"/>',
        'upload'         => '<path d="M12 16V4"/><path d="M7.5 8 12 3.5 16.5 8"/><path d="M4 20h16"/>',
        'printer'        => '<path d="M7 8V3.5h10V8"/><rect x="3.5" y="8" width="17" height="8" rx="2"/><path d="M7 13h10v7.5H7z"/>',
        'file-text'      => '<path d="M6 3h8l5 5v12a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"/><path d="M13.5 3v5.5H19"/><path d="M8.5 13h7"/><path d="M8.5 16.5h5"/>',
        'paperclip'      => '<path d="M20 11.5 12 19.4a5 5 0 0 1-7.1-7.1l8.5-8.4a3.3 3.3 0 0 1 4.7 4.7l-8.4 8.4a1.7 1.7 0 0 1-2.4-2.4l7.8-7.7"/>',
        'image'          => '<rect x="3.5" y="4.5" width="17" height="15" rx="2"/><circle cx="9" cy="10" r="1.6"/><path d="m4.5 17 4.8-4.5 3.4 3.2 3-2.7 4.3 4"/>',
        'camera'         => '<path d="M4 8h3l1.7-2.5h6.6L17 8h3a1 1 0 0 1 1 1v9.5a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z"/><circle cx="12" cy="13" r="3.6"/>',
        'qr-code'        => '<rect x="3.5" y="3.5" width="7" height="7" rx="1"/><rect x="13.5" y="3.5" width="7" height="7" rx="1"/><rect x="3.5" y="13.5" width="7" height="7" rx="1"/><path d="M13.5 13.5h3v3h-3z"/><path d="M20.5 13.5v3"/><path d="M17.5 20.5h3v-3"/><path d="M13.5 20.5h1"/>',
        'barcode'        => '<path d="M4 6v12"/><path d="M7 6v12"/><path d="M10 6v12"/><path d="M13.5 6v12"/><path d="M17 6v12"/><path d="M20 6v12"/>',
        'tag'            => '<path d="M11 3H4a1 1 0 0 0-1 1v7l9.5 9.5a1.5 1.5 0 0 0 2.1 0l6-6a1.5 1.5 0 0 0 0-2.1z"/><circle cx="7.5" cy="7.5" r="1.4"/>',
        'map-pin'        => '<path d="M12 21s7-6.3 7-11a7 7 0 1 0-14 0c0 4.7 7 11 7 11z"/><circle cx="12" cy="10" r="2.6"/>',
        'mail'           => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 6.5 8.5 6 8.5-6"/>',
        'phone'          => '<path d="M7.5 3.5h-2A2.5 2.5 0 0 0 3 6c0 8.3 6.7 15 15 15a2.5 2.5 0 0 0 2.5-2.5v-2l-4.5-2-2 2.5a15.6 15.6 0 0 1-5.5-5.5L11 9.5z"/>',
        'eye'            => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/>',
        'eye-off'        => '<path d="M6 6.3C3.9 7.9 2.5 10.2 2.5 12c0 0 3.5 6.5 9.5 6.5 1.6 0 3-.4 4.2-1"/><path d="M9.9 5.8A9.4 9.4 0 0 1 12 5.5c6 0 9.5 6.5 9.5 6.5a15 15 0 0 1-3.3 4"/><path d="M10 10a2.8 2.8 0 0 0 4 4"/><path d="m3.5 3.5 17 17"/>',

        // --- Chevrons and arrows ---------------------------------------------
        'chevron-down'   => '<path d="m6 9.5 6 6 6-6"/>',
        'chevron-up'     => '<path d="m6 14.5 6-6 6 6"/>',
        'chevron-left'   => '<path d="m14.5 6-6 6 6 6"/>',
        'chevron-right'  => '<path d="m9.5 6 6 6-6 6"/>',
        'arrow-left'     => '<path d="M20 12H4"/><path d="m10 6-6 6 6 6"/>',
        'arrow-right'    => '<path d="M4 12h16"/><path d="m14 6 6 6-6 6"/>',
        'arrow-up'       => '<path d="M12 20V4"/><path d="m6 10 6-6 6 6"/>',
        'arrow-down'     => '<path d="M12 4v16"/><path d="m6 14 6 6 6-6"/>',
        'external-link'  => '<path d="M13 4h7v7"/><path d="M20 4 10 14"/><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',

        // --- Settings and theme ----------------------------------------------
        'settings'       => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-2.9 1.2V21a2 2 0 1 1-4 0v-.1A1.7 1.7 0 0 0 7 19.4a1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0-1.2-2.9H1a2 2 0 1 1 0-4h.1A1.7 1.7 0 0 0 2.6 7a1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 2.9-1.2V1a2 2 0 1 1 4 0v.1A1.7 1.7 0 0 0 17 2.6a1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0 1.2 2.9H23a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1.7z"/>',
        'cog'            => '<circle cx="12" cy="12" r="3.2"/><path d="M12 2.5v2.2"/><path d="M12 19.3v2.2"/><path d="M21.5 12h-2.2"/><path d="M4.7 12H2.5"/><path d="m18.7 5.3-1.6 1.6"/><path d="m6.9 17.1-1.6 1.6"/><path d="m18.7 18.7-1.6-1.6"/><path d="M6.9 6.9 5.3 5.3"/>',
        'sun'            => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2.5"/><path d="M12 19.5V22"/><path d="M22 12h-2.5"/><path d="M4.5 12H2"/><path d="m19.07 4.93-1.77 1.77"/><path d="m6.7 17.3-1.77 1.77"/><path d="m19.07 19.07-1.77-1.77"/><path d="M6.7 6.7 4.93 4.93"/>',
        'moon'           => '<path d="M20.5 14.5A8.5 8.5 0 1 1 9.5 3.5a7 7 0 0 0 11 11z"/>',
    ];

    private function __construct()
    {
    }

    /**
     * Render an icon as inline SVG.
     *
     * An unknown name renders a neutral placeholder rather than nothing, so a
     * typo is visible in development but never breaks a page.
     */
    public static function render(string $name, string $class = '', int $size = 20): string
    {
        $path = self::PATHS[$name] ?? self::PATHS['circle'];

        $size    = max(8, min(96, $size));
        $classes = trim('icon icon-' . preg_replace('/[^a-z0-9\-]/', '', $name) . ' ' . $class);

        return sprintf(
            '<svg class="%s" width="%d" height="%d" viewBox="0 0 24 24" fill="none" '
            . 'stroke="currentColor" stroke-width="1.75" stroke-linecap="round" '
            . 'stroke-linejoin="round" aria-hidden="true" focusable="false">%s</svg>',
            htmlspecialchars($classes, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            $size,
            $size,
            $path
        );
    }

    /**
     * An icon that conveys meaning on its own, with an accessible label.
     */
    public static function labelled(string $name, string $label, string $class = '', int $size = 20): string
    {
        $svg = self::render($name, $class, $size);

        // Replace aria-hidden with a real label.
        $svg = str_replace(
            'aria-hidden="true"',
            'role="img" aria-label="' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"',
            $svg
        );

        return $svg;
    }

    public static function exists(string $name): bool
    {
        return isset(self::PATHS[$name]);
    }

    /**
     * Every icon name, for the category picker and the style guide.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        $names = array_keys(self::PATHS);
        sort($names);

        return $names;
    }

    /**
     * Names offered when choosing an icon for an asset category.
     *
     * @return array<string, string> name => label
     */
    public static function categoryChoices(): array
    {
        $names = [
            'kart', 'ride', 'activity', 'grid', 'map-pin', 'shield', 'truck',
            'tool', 'wrench', 'package', 'gauge', 'star', 'box', 'home',
        ];

        $out = [];

        foreach ($names as $name) {
            $out[$name] = Str::humanize($name);
        }

        return $out;
    }
}
