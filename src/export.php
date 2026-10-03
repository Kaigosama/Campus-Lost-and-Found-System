<?php
declare(strict_types=1);

/**
 * File downloads without libraries: CSV and a text-table PDF.
 * A table is a list of rows; the first row is the header.
 * ponytail: the PDF is plain Courier text with a bold header. If exports ever need wrapping text or Unicode beyond
 * Windows-1252, switch to a PDF library via Composer.
 */

/* ---------------------------------------------------------------- CSV */

/**
 * CSV with a UTF-8 byte-order mark so Excel shows accents correctly. Cells that a spreadsheet would run as a
 * formula (starting with = + - @, tab or CR) get a leading apostrophe: log data includes text typed by anyone,
 * such as the email on a failed log-in.
 */
function csv_build(array $rows): string
{
    $out = fopen('php://temp', 'w+');
    fwrite($out, "\xEF\xBB\xBF");
    foreach ($rows as $row) {
        fputcsv($out, array_map(fn ($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'" . $v : (string) $v, $row), ',', '"', '');
    }
    rewind($out);
    return (string) stream_get_contents($out);
}

/* ---------------------------------------------------------------- PDF */

/**
 * A landscape A4 PDF of text tables in Courier (fixed width, so columns line up without font metrics).
 * $sections: heading => ['widths' => [chars per column…], 'rows' => rows]. Long cells are cut with "...".
 */
function pdf_build(string $title, string $subtitle, array $sections): string
{
    $pageW = 842; $pageH = 595; $margin = 30; $size = 7; $lead = 9.5;
    $charW = $size * 0.6;   // every Courier glyph is 600/1000 em wide
    $text  = fn ($s) => str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '],
        (string) mb_convert_encoding((string) $s, 'Windows-1252', 'UTF-8'));
    $fit   = fn ($s, $w) => mb_strlen($s) > $w ? mb_substr($s, 0, max(1, $w - 3)) . '...' : $s;

    // Lay out every line first so the footer can say "page i of n".
    $pages = [[]];
    $y = $pageH - $margin - 34;
    $newPage = function () use (&$pages, &$y, $pageH, $margin) { $pages[] = []; $y = $pageH - $margin - 34; };
    $add     = function (array $line) use (&$pages) { $pages[array_key_last($pages)][] = $line; };
    foreach ($sections as $heading => $section) {
        if ($y < $margin + 80) $newPage();
        $add(['F2', 10, $margin, $y, $heading]);
        $y -= 16;
        $rows = array_values($section['rows']);
        $drawRow = function (array $row, bool $isHeader) use ($section, $add, $fit, &$y, $margin, $size, $charW, $lead) {
            $x = $margin;
            foreach (array_values($row) as $c => $cell) {
                $w = $section['widths'][$c];
                $add([$isHeader ? 'F2' : 'F1', $size, $x, $y, $fit((string) $cell, $w - 1)]);
                $x += $w * $charW;
            }
            if ($isHeader) $add(['rule', $y - 3]);
            $y -= $lead;
        };
        foreach ($rows as $r => $row) {
            if ($r > 0 && $y < $margin + 20) {
                $newPage();
                $drawRow($rows[0], true);   // repeat the header row on every page
            }
            $drawRow($row, $r === 0);
        }
        $y -= 14;
    }

    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Courier-Bold /Encoding /WinAnsiEncoding >>',
    ];
    $kids  = [];
    $total = count($pages);
    foreach ($pages as $i => $lines) {
        $s = 'BT /F2 13 Tf ' . $margin . ' ' . ($pageH - $margin) . ' Td (' . $text($title) . ') Tj ET '
            . 'BT /F1 7 Tf ' . $margin . ' ' . ($pageH - $margin - 11) . ' Td (' . $text($subtitle) . ') Tj ET '
            . 'BT /F1 7 Tf ' . ($pageW - $margin - 70) . ' ' . ($margin - 12) . ' Td (Page ' . ($i + 1) . ' of ' . $total . ') Tj ET ';
        foreach ($lines as $line) {
            $s .= $line[0] === 'rule'
                ? sprintf('0.6 G 0.5 w %d %.2F m %d %.2F l S ', $margin, $line[1], $pageW - $margin, $line[1])
                : sprintf('BT /%s %d Tf %.2F %.2F Td (%s) Tj ET ', $line[0], $line[1], $line[2], $line[3], $text($line[4]));
        }
        $stream = gzcompress($s);
        $pageNo = 5 + $i * 2;
        $objects[$pageNo] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . $pageW . ' ' . $pageH . '] '
            . '/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . ($pageNo + 1) . ' 0 R >>';
        $objects[$pageNo + 1] = '<< /Length ' . strlen($stream) . " /Filter /FlateDecode >>\nstream\n" . $stream . "\nendstream";
        $kids[] = "$pageNo 0 R";
    }
    $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $total . ' >>';
    ksort($objects);

    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [];
    foreach ($objects as $num => $body) {
        $offsets[$num] = strlen($pdf);
        $pdf .= "$num 0 obj\n$body\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= 'xref' . "\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
    foreach ($offsets as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }
    return $pdf . 'trailer << /Size ' . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
}
