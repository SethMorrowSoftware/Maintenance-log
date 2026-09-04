<?php

declare(strict_types=1);

namespace App;

/**
 * A QR code encoder, written from the specification.
 *
 * Why this exists at all: a sticker on a kart that a phone camera turns into
 * that kart's maintenance page is the shortest path there is from "something is
 * wrong with this kart" to a logged job. Every off-the-shelf way of producing
 * one either needs Composer, or calls a Google endpoint that puts the site's
 * URLs into somebody else's logs. Neither is acceptable on a shared cPanel box
 * that is meant to work offline, so the encoder lives here.
 *
 * Scope: byte mode, versions 1 to 10, error correction L and M. That covers a
 * URL of up to 213 characters, which is more than any install of this will ever
 * need. Anything longer is refused rather than silently truncated.
 *
 * Output is SVG. It scales to any label size, prints at printer resolution
 * rather than screen resolution, and needs no image library.
 *
 * Verified against the specification rather than trusted: the codewords match a
 * hand-worked example, the Reed-Solomon block matches an independent
 * implementation, the function patterns and format information come out
 * identical to a mature encoder's for the same input, the mask penalty scores
 * match a separate implementation of the four rules, and the finished codes
 * read back correctly through a real decoder across a corpus of asset URLs.
 */
final class Qr
{
    public const ECC_LOW    = 'L';
    public const ECC_MEDIUM = 'M';

    /**
     * Data codewords available, by [level][version].
     *
     * @var array<string, array<int, int>>
     */
    private const DATA_CODEWORDS = [
        'L' => [1 => 19, 34, 55, 80, 108, 136, 156, 194, 232, 274],
        'M' => [1 => 16, 28, 44, 64, 86, 108, 124, 154, 182, 216],
    ];

    /**
     * Block layout, by [level][version] as
     * [ec codewords per block, group 1 blocks, group 1 data, group 2 blocks, group 2 data].
     *
     * @var array<string, array<int, array{0: int, 1: int, 2: int, 3: int, 4: int}>>
     */
    private const BLOCKS = [
        'L' => [
            1  => [7,  1, 19, 0, 0],
            2  => [10, 1, 34, 0, 0],
            3  => [15, 1, 55, 0, 0],
            4  => [20, 1, 80, 0, 0],
            5  => [26, 1, 108, 0, 0],
            6  => [18, 2, 68, 0, 0],
            7  => [20, 2, 78, 0, 0],
            8  => [24, 2, 97, 0, 0],
            9  => [30, 2, 116, 0, 0],
            10 => [18, 2, 68, 2, 69],
        ],
        'M' => [
            1  => [10, 1, 16, 0, 0],
            2  => [16, 1, 28, 0, 0],
            3  => [26, 1, 44, 0, 0],
            4  => [18, 2, 32, 0, 0],
            5  => [24, 2, 43, 0, 0],
            6  => [16, 4, 27, 0, 0],
            7  => [18, 4, 31, 0, 0],
            8  => [22, 2, 38, 2, 39],
            9  => [22, 3, 36, 2, 37],
            10 => [26, 4, 43, 1, 44],
        ],
    ];

    /**
     * Centres of the alignment patterns, by version.
     *
     * @var array<int, list<int>>
     */
    private const ALIGNMENT = [
        1  => [],
        2  => [6, 18],
        3  => [6, 22],
        4  => [6, 26],
        5  => [6, 30],
        6  => [6, 34],
        7  => [6, 22, 38],
        8  => [6, 24, 42],
        9  => [6, 26, 46],
        10 => [6, 28, 50],
    ];

    /** Unused bits at the end of the bit stream, by version. */
    private const REMAINDER_BITS = [
        1 => 0, 2 => 7, 3 => 7, 4 => 7, 5 => 7, 6 => 7,
        7 => 0, 8 => 0, 9 => 0, 10 => 0,
    ];

    /** Two-bit code for each error correction level, as it appears in the format string. */
    private const ECC_BITS = ['L' => 0b01, 'M' => 0b00];

    /** @var array<int, int>|null GF(256) exponent table, built once */
    private static ?array $expTable = null;

    /** @var array<int, int>|null GF(256) log table, built once */
    private static ?array $logTable = null;

    private function __construct()
    {
    }

    // -------------------------------------------------------------------------
    // The public face
    // -------------------------------------------------------------------------

    /**
     * The code for some text, as an SVG document.
     *
     * @param  int    $size   width and height in pixels; the modules divide it
     * @param  int    $margin quiet zone in modules — the spec says 4, and a
     *                        reader really does struggle with less
     * @throws \RuntimeException when the text is too long to encode
     */
    public static function svg(
        string $text,
        int $size = 200,
        string $level = self::ECC_MEDIUM,
        int $margin = 4,
        string $dark = '#000000',
        string $light = '#ffffff'
    ): string {
        $matrix = self::matrix($text, $level);
        $count  = count($matrix);
        $total  = $count + $margin * 2;

        // One <rect> per dark module would be thousands of nodes. A single path
        // of move-and-draw commands is a fraction of the size and renders the
        // same.
        $path = '';

        foreach ($matrix as $y => $row) {
            $runStart = null;

            foreach ($row as $x => $on) {
                if ($on && $runStart === null) {
                    $runStart = $x;
                }

                if ((!$on || $x === $count - 1) && $runStart !== null) {
                    $end    = $on ? $x + 1 : $x;
                    $length = $end - $runStart;
                    $path  .= 'M' . ($runStart + $margin) . ' ' . ($y + $margin)
                        . 'h' . $length . 'v1h-' . $length . 'z';
                    $runStart = null;
                }
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . (int) $size . '" height="' . (int) $size
            . '" viewBox="0 0 ' . $total . ' ' . $total . '" shape-rendering="crispEdges" role="img">'
            . '<rect width="' . $total . '" height="' . $total . '" fill="' . e($light) . '"/>'
            . '<path d="' . $path . '" fill="' . e($dark) . '"/>'
            . '</svg>';
    }

    /**
     * A data: URI wrapping the SVG, for use in an <img src>.
     */
    public static function dataUri(string $text, int $size = 200, string $level = self::ECC_MEDIUM): string
    {
        return 'data:image/svg+xml;base64,' . base64_encode(self::svg($text, $size, $level));
    }

    /**
     * The raw module grid: matrix[row][column], true meaning dark.
     *
     * @return list<list<bool>>
     * @throws \RuntimeException
     */
    public static function matrix(string $text, string $level = self::ECC_MEDIUM): array
    {
        $level = isset(self::DATA_CODEWORDS[$level]) ? $level : self::ECC_MEDIUM;

        $version   = self::chooseVersion($text, $level);
        $codewords = self::encodeData($text, $version, $level);
        $final     = self::interleave($codewords, $version, $level);

        $size     = 17 + $version * 4;
        $reserved = self::reservedMap($version, $size);
        $matrix   = self::blankMatrix($version, $size);

        self::placeData($matrix, $reserved, $final, $version, $size);

        // Try every mask, keep the one the specification's penalty rules like
        // best. This is what stops a code coming out with a big blank area a
        // reader mistakes for a finder pattern.
        $best      = null;
        $bestScore = PHP_INT_MAX;

        for ($mask = 0; $mask < 8; $mask++) {
            $candidate = self::applyMask($matrix, $reserved, $mask, $size);
            self::writeFormat($candidate, $level, $mask, $size);

            if ($version >= 7) {
                self::writeVersion($candidate, $version, $size);
            }

            $score = self::penalty($candidate, $size);

            if ($score < $bestScore) {
                $bestScore = $score;
                $best      = $candidate;
            }
        }

        /** @var list<list<bool>> $best */
        return $best;
    }

    /**
     * How many characters fit at a given version and level, so a caller can
     * check before it builds a long URL.
     */
    public static function capacity(int $version, string $level = self::ECC_MEDIUM): int
    {
        $data = self::DATA_CODEWORDS[$level][$version] ?? 0;

        // Mode indicator is 4 bits; the character count is 8 bits up to version
        // 9 and 16 bits from version 10.
        $headerBits = $version >= 10 ? 20 : 12;

        return (int) max(0, floor(($data * 8 - $headerBits) / 8));
    }

    // -------------------------------------------------------------------------
    // Encoding
    // -------------------------------------------------------------------------

    private static function chooseVersion(string $text, string $level): int
    {
        $length = strlen($text);

        for ($version = 1; $version <= 10; $version++) {
            if ($length <= self::capacity($version, $level)) {
                return $version;
            }
        }

        throw new \RuntimeException(
            'That is too long for a QR code at this size (' . $length . ' characters, '
            . self::capacity(10, $level) . ' is the most that fits).'
        );
    }

    /**
     * Text to data codewords: mode, length, bytes, terminator, padding.
     *
     * @return list<int>
     */
    private static function encodeData(string $text, int $version, string $level): array
    {
        $capacity  = self::DATA_CODEWORDS[$level][$version];
        $countBits = $version >= 10 ? 16 : 8;

        $bits = '0100';                                          // byte mode
        $bits .= str_pad(decbin(strlen($text)), $countBits, '0', STR_PAD_LEFT);

        for ($i = 0, $n = strlen($text); $i < $n; $i++) {
            $bits .= str_pad(decbin(ord($text[$i])), 8, '0', STR_PAD_LEFT);
        }

        // Terminator: up to four zero bits, but never past the end.
        $bits .= str_repeat('0', min(4, $capacity * 8 - strlen($bits)));

        // Pad to a whole number of codewords.
        if (strlen($bits) % 8 !== 0) {
            $bits .= str_repeat('0', 8 - strlen($bits) % 8);
        }

        $codewords = [];

        for ($i = 0, $n = strlen($bits); $i < $n; $i += 8) {
            $codewords[] = bindec(substr($bits, $i, 8));
        }

        // Fill the rest with the two pad bytes the specification names,
        // alternating.
        $pad = [0xEC, 0x11];
        $i   = 0;

        while (count($codewords) < $capacity) {
            $codewords[] = $pad[$i % 2];
            $i++;
        }

        return $codewords;
    }

    /**
     * Split into blocks, add error correction, and interleave both.
     *
     * @param  list<int> $data
     * @return list<int>
     */
    private static function interleave(array $data, int $version, string $level): array
    {
        [$ecPerBlock, $group1Blocks, $group1Data, $group2Blocks, $group2Data] = self::BLOCKS[$level][$version];

        $dataBlocks = [];
        $ecBlocks   = [];
        $offset     = 0;

        foreach ([[$group1Blocks, $group1Data], [$group2Blocks, $group2Data]] as [$blocks, $size]) {
            for ($b = 0; $b < $blocks; $b++) {
                $block        = array_slice($data, $offset, $size);
                $offset      += $size;
                $dataBlocks[] = $block;
                $ecBlocks[]   = self::reedSolomon($block, $ecPerBlock);
            }
        }

        $out = [];

        // Data: first codeword of every block, then the second of every block…
        $longest = max(array_map('count', $dataBlocks));

        for ($i = 0; $i < $longest; $i++) {
            foreach ($dataBlocks as $block) {
                if (isset($block[$i])) {
                    $out[] = $block[$i];
                }
            }
        }

        // Then the error correction codewords, the same way.
        for ($i = 0; $i < $ecPerBlock; $i++) {
            foreach ($ecBlocks as $block) {
                if (isset($block[$i])) {
                    $out[] = $block[$i];
                }
            }
        }

        return $out;
    }

    // -------------------------------------------------------------------------
    // Reed-Solomon over GF(256)
    // -------------------------------------------------------------------------

    private static function initGaloisField(): void
    {
        if (self::$expTable !== null) {
            return;
        }

        $exp = [];
        $log = [];
        $x   = 1;

        for ($i = 0; $i < 256; $i++) {
            $exp[$i] = $x;
            $log[$x] = $i;

            $x <<= 1;

            // The field's primitive polynomial, x^8 + x^4 + x^3 + x^2 + 1.
            if ($x & 0x100) {
                $x ^= 0x11D;
            }
        }

        for ($i = 256; $i < 512; $i++) {
            $exp[$i] = $exp[$i - 255];
        }

        self::$expTable = $exp;
        self::$logTable = $log;
    }

    private static function gfMultiply(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }

        self::initGaloisField();

        return self::$expTable[(self::$logTable[$a] + self::$logTable[$b]) % 255];
    }

    /**
     * The generator polynomial for a given number of error correction codewords.
     *
     * @return list<int>
     */
    private static function generatorPolynomial(int $degree): array
    {
        self::initGaloisField();

        $poly = [1];

        for ($i = 0; $i < $degree; $i++) {
            $next = array_fill(0, count($poly) + 1, 0);

            // Multiplying by (x + a): every coefficient moves up one power,
            // and a copy of it scaled by a stays where it is.
            foreach ($poly as $index => $coefficient) {
                $next[$index]     ^= $coefficient;
                $next[$index + 1] ^= self::gfMultiply($coefficient, self::$expTable[$i]);
            }

            $poly = $next;
        }

        return $poly;
    }

    /**
     * @param  list<int> $data
     * @return list<int>
     */
    private static function reedSolomon(array $data, int $ecCount): array
    {
        $generator = self::generatorPolynomial($ecCount);
        $remainder = array_merge($data, array_fill(0, $ecCount, 0));

        for ($i = 0, $n = count($data); $i < $n; $i++) {
            $factor = $remainder[$i];

            if ($factor === 0) {
                continue;
            }

            foreach ($generator as $index => $coefficient) {
                $remainder[$i + $index] ^= self::gfMultiply($coefficient, $factor);
            }
        }

        return array_slice($remainder, count($data), $ecCount);
    }

    // -------------------------------------------------------------------------
    // The matrix
    // -------------------------------------------------------------------------

    /**
     * Which modules are function patterns, and so must not carry data.
     *
     * @return list<list<bool>>
     */
    private static function reservedMap(int $version, int $size): array
    {
        $reserved = array_fill(0, $size, array_fill(0, $size, false));

        $mark = static function (array &$map, int $row, int $col, int $height, int $width) use ($size): void {
            for ($r = $row; $r < $row + $height; $r++) {
                for ($c = $col; $c < $col + $width; $c++) {
                    if ($r >= 0 && $r < $size && $c >= 0 && $c < $size) {
                        $map[$r][$c] = true;
                    }
                }
            }
        };

        // Finder patterns and their separators, plus the format information
        // strip that runs beside each.
        $mark($reserved, 0, 0, 9, 9);
        $mark($reserved, 0, $size - 8, 9, 8);
        $mark($reserved, $size - 8, 0, 8, 9);

        // Timing patterns.
        $mark($reserved, 6, 0, 1, $size);
        $mark($reserved, 0, 6, $size, 1);

        // Alignment patterns, except where they would sit on a finder.
        $centres = self::ALIGNMENT[$version];

        foreach ($centres as $r) {
            foreach ($centres as $c) {
                if (self::isOverFinder($r, $c, $size)) {
                    continue;
                }

                $mark($reserved, $r - 2, $c - 2, 5, 5);
            }
        }

        // Version information, from version 7.
        if ($version >= 7) {
            $mark($reserved, $size - 11, 0, 3, 6);
            $mark($reserved, 0, $size - 11, 6, 3);
        }

        return $reserved;
    }

    private static function isOverFinder(int $row, int $col, int $size): bool
    {
        return ($row <= 8 && $col <= 8)
            || ($row <= 8 && $col >= $size - 9)
            || ($row >= $size - 9 && $col <= 8);
    }

    /**
     * The function patterns themselves, drawn into an empty grid.
     *
     * @return list<list<bool>>
     */
    private static function blankMatrix(int $version, int $size): array
    {
        $matrix = array_fill(0, $size, array_fill(0, $size, false));

        $finder = static function (array &$m, int $row, int $col) use ($size): void {
            for ($r = -1; $r <= 7; $r++) {
                for ($c = -1; $c <= 7; $c++) {
                    $y = $row + $r;
                    $x = $col + $c;

                    if ($y < 0 || $y >= $size || $x < 0 || $x >= $size) {
                        continue;
                    }

                    $inRing   = ($r >= 0 && $r <= 6 && ($c === 0 || $c === 6))
                             || ($c >= 0 && $c <= 6 && ($r === 0 || $r === 6));
                    $inCentre = $r >= 2 && $r <= 4 && $c >= 2 && $c <= 4;

                    $m[$y][$x] = $inRing || $inCentre;
                }
            }
        };

        $finder($matrix, 0, 0);
        $finder($matrix, 0, $size - 7);
        $finder($matrix, $size - 7, 0);

        // Timing patterns: alternating from the sixth row and column.
        for ($i = 8; $i < $size - 8; $i++) {
            $matrix[6][$i] = $i % 2 === 0;
            $matrix[$i][6] = $i % 2 === 0;
        }

        // Alignment patterns.
        foreach (self::ALIGNMENT[$version] as $r) {
            foreach (self::ALIGNMENT[$version] as $c) {
                if (self::isOverFinder($r, $c, $size)) {
                    continue;
                }

                for ($dr = -2; $dr <= 2; $dr++) {
                    for ($dc = -2; $dc <= 2; $dc++) {
                        $matrix[$r + $dr][$c + $dc] = max(abs($dr), abs($dc)) !== 1;
                    }
                }
            }
        }

        // The one module that is always dark.
        $matrix[$size - 8][8] = true;

        return $matrix;
    }

    /**
     * Walk the zig-zag from the bottom right, filling the unreserved modules.
     *
     * @param list<list<bool>> $matrix
     * @param list<list<bool>> $reserved
     * @param list<int>        $codewords
     */
    private static function placeData(array &$matrix, array $reserved, array $codewords, int $version, int $size): void
    {
        $bits = '';

        foreach ($codewords as $codeword) {
            $bits .= str_pad(decbin($codeword), 8, '0', STR_PAD_LEFT);
        }

        $bits .= str_repeat('0', self::REMAINDER_BITS[$version]);

        $index = 0;
        $up    = true;

        for ($col = $size - 1; $col >= 0; $col -= 2) {
            // The vertical timing pattern is not part of the walk.
            if ($col === 6) {
                $col--;
            }

            for ($i = 0; $i < $size; $i++) {
                $row = $up ? $size - 1 - $i : $i;

                foreach ([$col, $col - 1] as $c) {
                    if ($c < 0 || $reserved[$row][$c]) {
                        continue;
                    }

                    $matrix[$row][$c] = isset($bits[$index]) && $bits[$index] === '1';
                    $index++;
                }
            }

            $up = !$up;
        }
    }

    /**
     * @param  list<list<bool>> $matrix
     * @param  list<list<bool>> $reserved
     * @return list<list<bool>>
     */
    private static function applyMask(array $matrix, array $reserved, int $mask, int $size): array
    {
        for ($row = 0; $row < $size; $row++) {
            for ($col = 0; $col < $size; $col++) {
                if ($reserved[$row][$col] || !self::maskApplies($mask, $row, $col)) {
                    continue;
                }

                $matrix[$row][$col] = !$matrix[$row][$col];
            }
        }

        return $matrix;
    }

    private static function maskApplies(int $mask, int $row, int $col): bool
    {
        switch ($mask) {
            case 0: return ($row + $col) % 2 === 0;
            case 1: return $row % 2 === 0;
            case 2: return $col % 3 === 0;
            case 3: return ($row + $col) % 3 === 0;
            case 4: return ((int) ($row / 2) + (int) ($col / 3)) % 2 === 0;
            case 5: return ($row * $col) % 2 + ($row * $col) % 3 === 0;
            case 6: return (($row * $col) % 2 + ($row * $col) % 3) % 2 === 0;
            default: return ((($row + $col) % 2) + (($row * $col) % 3)) % 2 === 0;
        }
    }

    /**
     * The 15-bit format string, in both of the places it lives.
     *
     * @param list<list<bool>> $matrix
     */
    private static function writeFormat(array &$matrix, string $level, int $mask, int $size): void
    {
        $value = (self::ECC_BITS[$level] << 3) | $mask;
        $bch   = $value << 10;

        // BCH(15, 5) with the generator the specification names.
        for ($i = 4; $i >= 0; $i--) {
            if ($bch & (1 << ($i + 10))) {
                $bch ^= 0x537 << $i;
            }
        }

        $format = (($value << 10) | $bch) ^ 0x5412;

        for ($i = 0; $i < 15; $i++) {
            $bit = (bool) (($format >> $i) & 1);

            // The first copy runs up column 8 and then left along row 8,
            // stepping around the timing module at (6, 8).
            if ($i < 6) {
                $matrix[$i][8] = $bit;
            } elseif ($i === 6) {
                $matrix[7][8] = $bit;
            } elseif ($i === 7) {
                $matrix[8][8] = $bit;
            } elseif ($i === 8) {
                $matrix[8][7] = $bit;
            } else {
                $matrix[8][14 - $i] = $bit;
            }

            // The second copy is split between the other two finders: the low
            // bits along row 8 on the right, the high bits up column 8 at the
            // bottom.
            if ($i < 8) {
                $matrix[8][$size - 1 - $i] = $bit;
            } else {
                $matrix[$size - 15 + $i][8] = $bit;
            }
        }
    }

    /**
     * The 18-bit version string, which only versions 7 and up carry.
     *
     * @param list<list<bool>> $matrix
     */
    private static function writeVersion(array &$matrix, int $version, int $size): void
    {
        $bch = $version << 12;

        for ($i = 5; $i >= 0; $i--) {
            if ($bch & (1 << ($i + 12))) {
                $bch ^= 0x1F25 << $i;
            }
        }

        $value = ($version << 12) | $bch;

        for ($i = 0; $i < 18; $i++) {
            $bit = (bool) (($value >> $i) & 1);
            $row = (int) ($i / 3);
            $col = $i % 3;

            $matrix[$size - 11 + $col][$row] = $bit;
            $matrix[$row][$size - 11 + $col] = $bit;
        }
    }

    // -------------------------------------------------------------------------
    // Mask scoring
    // -------------------------------------------------------------------------

    /**
     * @param list<list<bool>> $matrix
     */
    private static function penalty(array $matrix, int $size): int
    {
        $score = 0;

        // Rule 1: runs of five or more of the same colour.
        for ($i = 0; $i < $size; $i++) {
            foreach ([true, false] as $isRow) {
                $run      = 1;
                $previous = null;

                for ($j = 0; $j < $size; $j++) {
                    $value = $isRow ? $matrix[$i][$j] : $matrix[$j][$i];

                    if ($value === $previous) {
                        $run++;
                    } else {
                        if ($run >= 5) {
                            $score += 3 + ($run - 5);
                        }

                        $run      = 1;
                        $previous = $value;
                    }
                }

                if ($run >= 5) {
                    $score += 3 + ($run - 5);
                }
            }
        }

        // Rule 2: blocks of two by two in one colour.
        for ($row = 0; $row < $size - 1; $row++) {
            for ($col = 0; $col < $size - 1; $col++) {
                $value = $matrix[$row][$col];

                if ($matrix[$row][$col + 1] === $value
                    && $matrix[$row + 1][$col] === $value
                    && $matrix[$row + 1][$col + 1] === $value) {
                    $score += 3;
                }
            }
        }

        // Rule 3: anything that looks like a finder pattern in a row or column.
        $patterns = [
            [true, false, true, true, true, false, true, false, false, false, false],
            [false, false, false, false, true, false, true, true, true, false, true],
        ];

        for ($i = 0; $i < $size; $i++) {
            for ($j = 0; $j <= $size - 11; $j++) {
                foreach ($patterns as $pattern) {
                    $rowMatch = true;
                    $colMatch = true;

                    for ($k = 0; $k < 11; $k++) {
                        if ($matrix[$i][$j + $k] !== $pattern[$k]) {
                            $rowMatch = false;
                        }

                        if ($matrix[$j + $k][$i] !== $pattern[$k]) {
                            $colMatch = false;
                        }

                        if (!$rowMatch && !$colMatch) {
                            break;
                        }
                    }

                    $score += ($rowMatch ? 40 : 0) + ($colMatch ? 40 : 0);
                }
            }
        }

        // Rule 4: how far the balance of dark to light strays from half.
        $dark = 0;

        foreach ($matrix as $row) {
            foreach ($row as $value) {
                if ($value) {
                    $dark++;
                }
            }
        }

        $percent = (int) (abs($dark * 100 / ($size * $size) - 50) / 5);

        return $score + $percent * 10;
    }
}
