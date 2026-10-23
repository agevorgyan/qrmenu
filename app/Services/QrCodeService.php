<?php

namespace App\Services;

/**
 * Lightweight, zero-dependency, self-contained SVG QR Code generator (Byte Mode, Level L/M).
 * Guarantees zero data leakage to third-party APIs for sensitive data such as 2FA TOTP secrets.
 */
class QrCodeService
{
    private static ?array $gfExp = null;

    private static ?array $gfLog = null;

    /**
     * Capacity table for Byte mode with Error Correction Level L:
     * [version => ['size' => modules, 'data_bytes' => count, 'ec_bytes' => count, 'blocks' => count, 'align' => [coords]]]
     */
    private const VERSION_TABLE = [
        1 => ['size' => 21, 'data_bytes' => 19, 'ec_bytes' => 7, 'blocks' => 1, 'align' => []],
        2 => ['size' => 25, 'data_bytes' => 34, 'ec_bytes' => 10, 'blocks' => 1, 'align' => [6, 18]],
        3 => ['size' => 29, 'data_bytes' => 55, 'ec_bytes' => 15, 'blocks' => 1, 'align' => [6, 22]],
        4 => ['size' => 33, 'data_bytes' => 80, 'ec_bytes' => 20, 'blocks' => 1, 'align' => [6, 26]],
        5 => ['size' => 37, 'data_bytes' => 108, 'ec_bytes' => 26, 'blocks' => 1, 'align' => [6, 30]],
        6 => ['size' => 41, 'data_bytes' => 136, 'ec_bytes' => 18, 'blocks' => 2, 'align' => [6, 34]],
        7 => ['size' => 45, 'data_bytes' => 156, 'ec_bytes' => 20, 'blocks' => 2, 'align' => [6, 22, 38]],
        8 => ['size' => 49, 'data_bytes' => 194, 'ec_bytes' => 24, 'blocks' => 2, 'align' => [6, 24, 42]],
        9 => ['size' => 53, 'data_bytes' => 232, 'ec_bytes' => 30, 'blocks' => 2, 'align' => [6, 26, 46]],
        10 => ['size' => 57, 'data_bytes' => 274, 'ec_bytes' => 18, 'blocks' => 4, 'align' => [6, 28, 50]],
    ];

    /**
     * Format information strings for Level L (Mask patterns 0 to 7) with BCH error correction XORed with 0x5412.
     */
    private const FORMAT_INFO_L = [
        0 => 0x77C4, // 0111011111000100
        1 => 0x72F3,
        2 => 0x7DAA,
        3 => 0x789D,
        4 => 0x662F,
        5 => 0x6318,
        6 => 0x6C41,
        7 => 0x6976,
    ];

    public function __construct()
    {
        self::initGaloisField();
    }

    private static function initGaloisField(): void
    {
        if (self::$gfExp !== null) {
            return;
        }

        self::$gfExp = array_fill(0, 512, 0);
        self::$gfLog = array_fill(0, 256, 0);

        $val = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$gfExp[$i] = $val;
            self::$gfLog[$val] = $i;
            $val <<= 1;
            if ($val & 0x100) {
                $val ^= 0x11D; // Primitive polynomial x^8 + x^4 + x^3 + x^2 + 1
            }
        }
        for ($i = 255; $i < 512; $i++) {
            self::$gfExp[$i] = self::$gfExp[$i - 255];
        }
    }

    /**
     * Generate an SVG data URI for given text.
     */
    public function generateDataUri(string $text, int $pixelSize = 200): string
    {
        $svg = $this->generateSvg($text, $pixelSize);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * Generate raw SVG markup for given text.
     */
    public function generateSvg(string $text, int $pixelSize = 200): string
    {
        $matrix = $this->encode($text);
        $size = count($matrix);
        $quietZone = 4;
        $totalSize = $size + ($quietZone * 2);

        $rects = [];
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                if ($matrix[$r][$c]) {
                    $x = $c + $quietZone;
                    $y = $r + $quietZone;
                    $rects[] = "<rect x=\"{$x}\" y=\"{$y}\" width=\"1\" height=\"1\" fill=\"#000000\"/>";
                }
            }
        }

        $rectsContent = implode('', $rects);

        return "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 {$totalSize} {$totalSize}\" width=\"{$pixelSize}\" height=\"{$pixelSize}\" shape-rendering=\"crispEdges\"><rect width=\"100%\" height=\"100%\" fill=\"#ffffff\"/>{$rectsContent}</svg>";
    }

    /**
     * Encode text into boolean module matrix.
     */
    public function encode(string $text): array
    {
        self::initGaloisField();
        $textBytes = array_values(unpack('C*', $text));
        $textLen = count($textBytes);

        // Select minimal version
        $version = null;
        foreach (self::VERSION_TABLE as $ver => $cfg) {
            // Header overhead: 4 bits mode + 8 bits length indicator = 12 bits -> ceil(12/8) + textLen <= data_bytes
            $neededBytes = $textLen + 2;
            if ($neededBytes <= $cfg['data_bytes']) {
                $version = $ver;
                break;
            }
        }

        if ($version === null) {
            throw new \InvalidArgumentException('Text too long for supported QR Code capacity (max 270 bytes).');
        }

        $cfg = self::VERSION_TABLE[$version];
        $totalDataBytes = $cfg['data_bytes'];
        $numBlocks = $cfg['blocks'];
        $ecBytesPerBlock = $cfg['ec_bytes'];

        // Build bit stream
        $bits = '';
        // 1. Mode indicator: 0100 for Byte mode
        $bits .= '0100';
        // 2. Character count indicator: 8 bits (for versions 1-9) or 16 bits (version 10+)
        $bits .= str_pad(decbin($textLen), ($version >= 10 ? 16 : 8), '0', STR_PAD_LEFT);
        // 3. Data bits
        foreach ($textBytes as $byte) {
            $bits .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
        }
        // 4. Terminator: up to 4 zero bits
        $neededBits = $totalDataBytes * 8;
        $terminatorBits = min(4, $neededBits - strlen($bits));
        if ($terminatorBits > 0) {
            $bits .= str_repeat('0', $terminatorBits);
        }
        // 5. Pad to multiple of 8
        while (strlen($bits) % 8 !== 0) {
            $bits .= '0';
        }
        // 6. Pad codewords (alternate 0xEC, 0x11)
        $padBytes = [0xEC, 0x11];
        $padIdx = 0;
        while (strlen($bits) < $neededBits) {
            $bits .= str_pad(decbin($padBytes[$padIdx]), 8, '0', STR_PAD_LEFT);
            $padIdx = 1 - $padIdx;
        }

        // Split data bytes into blocks
        $allDataCodewords = [];
        for ($i = 0; $i < strlen($bits); $i += 8) {
            $allDataCodewords[] = bindec(substr($bits, $i, 8));
        }

        $dataBlocks = [];
        $bytesPerBlock = (int) ($totalDataBytes / $numBlocks);
        $extraBytes = $totalDataBytes % $numBlocks;
        $offset = 0;
        for ($b = 0; $b < $numBlocks; $b++) {
            $blockSize = $bytesPerBlock + ($b >= ($numBlocks - $extraBytes) ? 1 : 0);
            $dataBlocks[$b] = array_slice($allDataCodewords, $offset, $blockSize);
            $offset += $blockSize;
        }

        // Calculate Reed-Solomon EC for each block
        $ecBlocks = [];
        for ($b = 0; $b < $numBlocks; $b++) {
            $ecBlocks[$b] = $this->calculateReedSolomon($dataBlocks[$b], $ecBytesPerBlock);
        }

        // Interleave data codewords
        $finalCodewords = [];
        $maxDataLen = 0;
        foreach ($dataBlocks as $db) {
            $maxDataLen = max($maxDataLen, count($db));
        }
        for ($i = 0; $i < $maxDataLen; $i++) {
            for ($b = 0; $b < $numBlocks; $b++) {
                if (isset($dataBlocks[$b][$i])) {
                    $finalCodewords[] = $dataBlocks[$b][$i];
                }
            }
        }

        // Interleave EC codewords
        for ($i = 0; $i < $ecBytesPerBlock; $i++) {
            for ($b = 0; $b < $numBlocks; $b++) {
                if (isset($ecBlocks[$b][$i])) {
                    $finalCodewords[] = $ecBlocks[$b][$i];
                }
            }
        }

        // Construct matrix
        $matrixSize = $cfg['size'];
        $matrix = array_fill(0, $matrixSize, array_fill(0, $matrixSize, null));
        $reserved = array_fill(0, $matrixSize, array_fill(0, $matrixSize, false));

        // Place function patterns
        $this->placeFinderPatterns($matrix, $reserved, $matrixSize);
        $this->placeTimingPatterns($matrix, $reserved, $matrixSize);
        $this->placeAlignmentPatterns($matrix, $reserved, $cfg['align']);
        $this->reserveFormatInfo($matrix, $reserved, $matrixSize);

        // Place data into matrix
        $finalBits = '';
        foreach ($finalCodewords as $cw) {
            $finalBits .= str_pad(decbin($cw), 8, '0', STR_PAD_LEFT);
        }

        $this->placeDataBits($matrix, $reserved, $finalBits, $matrixSize);

        // Apply best mask pattern (Pattern 0: (r + c) % 2 === 0 is clean and universally supported)
        $bestMask = 0;
        $this->applyMaskAndFormat($matrix, $reserved, $bestMask, $matrixSize);

        return $matrix;
    }

    private function calculateReedSolomon(array $data, int $ecCount): array
    {
        $gen = $this->getRsGeneratorPoly($ecCount);
        $msg = array_pad($data, count($data) + $ecCount, 0);

        for ($i = 0; $i < count($data); $i++) {
            $coef = $msg[$i];
            if ($coef !== 0) {
                $logCoef = self::$gfLog[$coef];
                for ($j = 0; $j < count($gen); $j++) {
                    $msg[$i + $j] ^= self::$gfExp[$logCoef + $gen[$j]];
                }
            }
        }

        return array_slice($msg, count($data));
    }

    private function getRsGeneratorPoly(int $ecCount): array
    {
        $poly = [0]; // 1 * x^0 in alpha exponents
        for ($i = 0; $i < $ecCount; $i++) {
            $newPoly = [];
            for ($j = 0; $j <= count($poly); $j++) {
                $t1 = ($j < count($poly)) ? self::$gfExp[$poly[$j] + $i] : 0;
                $t2 = ($j > 0) ? self::$gfExp[$poly[$j - 1]] : 0;
                $newPoly[$j] = self::$gfLog[$t1 ^ $t2];
            }
            $poly = $newPoly;
        }

        return $poly;
    }

    private function placeFinderPatterns(array &$matrix, array &$reserved, int $size): void
    {
        $corners = [[0, 0], [$size - 7, 0], [0, $size - 7]];
        foreach ($corners as [$r0, $c0]) {
            for ($r = -1; $r <= 7; $r++) {
                for ($c = -1; $c <= 7; $c++) {
                    $row = $r0 + $r;
                    $col = $c0 + $c;
                    if ($row >= 0 && $row < $size && $col >= 0 && $col < $size) {
                        $reserved[$row][$col] = true;
                        if ($r >= 0 && $r <= 6 && $c >= 0 && $c <= 6) {
                            $isBlack = ($r === 0 || $r === 6 || $c === 0 || $c === 6 || ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4));
                            $matrix[$row][$col] = $isBlack;
                        } else {
                            $matrix[$row][$col] = false; // Separator
                        }
                    }
                }
            }
        }
    }

    private function placeTimingPatterns(array &$matrix, array &$reserved, int $size): void
    {
        for ($i = 8; $i < $size - 8; $i++) {
            if (! $reserved[6][$i]) {
                $matrix[6][$i] = ($i % 2 === 0);
                $reserved[6][$i] = true;
            }
            if (! $reserved[$i][6]) {
                $matrix[$i][6] = ($i % 2 === 0);
                $reserved[$i][6] = true;
            }
        }
    }

    private function placeAlignmentPatterns(array &$matrix, array &$reserved, array $coords): void
    {
        $count = count($coords);
        if ($count === 0) {
            return;
        }

        for ($i = 0; $i < $count; $i++) {
            for ($j = 0; $j < $count; $j++) {
                $r0 = $coords[$i];
                $c0 = $coords[$j];

                // Skip if intersects with top-left, top-right, or bottom-left finder patterns
                if ($reserved[$r0][$c0]) {
                    continue;
                }

                for ($r = -2; $r <= 2; $r++) {
                    for ($c = -2; $c <= 2; $c++) {
                        $isBlack = (abs($r) === 2 || abs($c) === 2 || ($r === 0 && $c === 0));
                        $matrix[$r0 + $r][$c0 + $c] = $isBlack;
                        $reserved[$r0 + $r][$c0 + $c] = true;
                    }
                }
            }
        }
    }

    private function reserveFormatInfo(array &$matrix, array &$reserved, int $size): void
    {
        for ($i = 0; $i < 9; $i++) {
            $reserved[8][$i] = true;
            $reserved[$i][8] = true;
            $reserved[$size - 1 - $i][8] = true;
            $reserved[8][$size - 1 - $i] = true;
        }
        // Dark module
        $matrix[$size - 8][8] = true;
        $reserved[$size - 8][8] = true;
    }

    private function placeDataBits(array &$matrix, array &$reserved, string $bits, int $size): void
    {
        $bitIdx = 0;
        $bitLen = strlen($bits);
        $col = $size - 1;
        $dir = -1; // upward

        while ($col > 0) {
            if ($col === 6) {
                $col--; // Skip vertical timing column
            }

            for ($step = 0; $step < $size; $step++) {
                $row = ($dir === -1) ? ($size - 1 - $step) : $step;
                for ($c = 0; $c < 2; $c++) {
                    $currCol = $col - $c;
                    if (! $reserved[$row][$currCol]) {
                        $matrix[$row][$currCol] = ($bitIdx < $bitLen && $bits[$bitIdx] === '1');
                        $bitIdx++;
                    }
                }
            }

            $dir = -$dir;
            $col -= 2;
        }
    }

    private function applyMaskAndFormat(array &$matrix, array $reserved, int $maskPattern, int $size): void
    {
        // Apply mask pattern 0: (row + col) % 2 === 0
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                if (! $reserved[$r][$c]) {
                    if (($r + $c) % 2 === 0) {
                        $matrix[$r][$c] = ! $matrix[$r][$c];
                    }
                }
            }
        }

        // Place 15-bit format information for Level L with mask 0 (0x77C4)
        $formatInfo = self::FORMAT_INFO_L[$maskPattern];
        $bits = str_pad(decbin($formatInfo), 15, '0', STR_PAD_LEFT);

        // Around top-left
        $matrix[8][0] = ($bits[0] === '1');
        $matrix[8][1] = ($bits[1] === '1');
        $matrix[8][2] = ($bits[2] === '1');
        $matrix[8][3] = ($bits[3] === '1');
        $matrix[8][4] = ($bits[4] === '1');
        $matrix[8][5] = ($bits[5] === '1');
        $matrix[8][7] = ($bits[6] === '1');
        $matrix[8][8] = ($bits[7] === '1');
        $matrix[7][8] = ($bits[8] === '1');
        $matrix[5][8] = ($bits[9] === '1');
        $matrix[4][8] = ($bits[10] === '1');
        $matrix[3][8] = ($bits[11] === '1');
        $matrix[2][8] = ($bits[12] === '1');
        $matrix[1][8] = ($bits[13] === '1');
        $matrix[0][8] = ($bits[14] === '1');

        // Split around bottom-left and top-right
        for ($i = 0; $i < 7; $i++) {
            $matrix[$size - 1 - $i][8] = ($bits[$i] === '1');
        }
        for ($i = 7; $i < 15; $i++) {
            $matrix[8][$size - 15 + $i] = ($bits[$i] === '1');
        }
    }
}
