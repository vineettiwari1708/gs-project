<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/google_sheet.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// ✅ check data
if (empty($values)) {
    die("No data found from Google Sheet.");
}
array_shift($values); // remove header row

// Filters
$category     = $_GET['category']     ?? '';
$makeModel    = $_GET['make_model']   ?? '';
$lastIndent   = $_GET['last_indent']  ?? '';
$fromDate     = $_GET['from_date']    ?? '';
$entryPage    = $_GET['entry_page']   ?? '';
$entryRegister= $_GET['entry_register'] ?? '';
$entryProduct = $_GET['entry_product']  ?? '';
$entryValue   = isset($_GET['entry_value']) ? (int)$_GET['entry_value'] : null;

// --- Filtering (same as print.php) ---
$filteredData = array_filter($values, function($row) use ($category, $makeModel, $lastIndent, $fromDate) {
    $catMatch  = $category ? (isset($row[3]) && $row[3] === $category) : true;
    $makeMatch = $makeModel ? (isset($row[4]) && $row[4] === $makeModel) : true;
    if (!$catMatch || !$makeMatch) return false;

    if (empty($row[1])) return false;
    $parts = explode('/', $row[1]);
    if (count($parts) !== 3) return false;
    $rowDate = sprintf('%04d-%02d-%02d', $parts[2], $parts[1], $parts[0]);

    $rowIndent = isset($row[6]) ? (int)$row[6] : 0;

    if ($fromDate) {
        $fromDateObj = date_create_from_format('Y-m-d', $fromDate);
        $rowDateObj  = date_create_from_format('Y-m-d', $rowDate);
        if (!$fromDateObj || !$rowDateObj) return false;

        if ($rowDateObj > $fromDateObj) return true;
        if ($rowDateObj == $fromDateObj) {
            return $lastIndent ? ($rowIndent > (int)$lastIndent) : false;
        }
        return false;
    }
    return true;
});

// --- Sort ---
usort($filteredData, function($a, $b) {
    $partsA = explode('/', $a[1] ?? '');
    $partsB = explode('/', $b[1] ?? '');
    $dateA = sprintf('%04d-%02d-%02d', $partsA[2] ?? 0, $partsA[1] ?? 0, $partsA[0] ?? 0);
    $dateB = sprintf('%04d-%02d-%02d', $partsB[2] ?? 0, $partsB[1] ?? 0, $partsB[0] ?? 0);

    if ($dateA === $dateB) {
        return ((int)($a[6] ?? 0)) <=> ((int)($b[6] ?? 0));
    }
    return strcmp($dateA, $dateB);
});

$filteredData = array_values($filteredData);

// --- Build HTML ---
date_default_timezone_set("Asia/Kolkata");

$html = '
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>CEA - INVENTORY REPORT (PDF)</title>
  <style>
    body { font-family: sans-serif; font-size: 12px; }
    table { border-collapse: collapse; width: 100%; margin: 20px auto 100px; }
    th, td { border: 1px solid #444; padding: 4px; font-size: 11px; }
    th { background: #f4f4f4; }
    h1, h4 { text-align:center; margin:0; }
    .footer { text-align:right; margin-top:50px; font-size:11px; }
  </style>
</head>
<body>
  <h1>STOCK REGISTER</h1>
  <h4>CEA - INVENTORY MANAGEMENT SYSTEM</h4>
  <hr style="width:400px; border:1px solid black;">
';

if ($entryPage || $entryRegister || $entryProduct || $entryValue !== null) {
    $html .= "<div style='text-align:center; margin:10px 0; font-weight:bold;'>
        Page: ".htmlspecialchars($entryPage)." &nbsp;|&nbsp;
        Register: ".htmlspecialchars($entryRegister)." &nbsp;|&nbsp;
        Product: ".htmlspecialchars($entryProduct)." &nbsp;|&nbsp;
        Banlance: ".htmlspecialchars($entryValue)."
    </div>";
}

$html .= "
<div style='text-align:center; margin-bottom:10px;'>
  <strong>Category:</strong> ".htmlspecialchars($category ?: 'All')." &nbsp;&nbsp;
  <strong>Make & Model:</strong> ".htmlspecialchars($makeModel ?: 'All')." &nbsp;&nbsp;
  <strong>Pre. Indent:</strong> ".htmlspecialchars($lastIndent ?: 'All')." &nbsp;&nbsp;
  <strong>Pre. Date:</strong> ".($fromDate ? date('d/m/Y', strtotime($fromDate)) : "All")."
</div>

<table>
  <tr>
    <th>DATE</th>
    <th>PARTICULARS</th>
    <th>BILL No.</th>
    <th>RECEIPT</th>
    <th>ISSUED</th>
    <th>BALANCE</th>
    <th>REMARK</th>
  </tr>
";

$runningBalance = $entryValue;
foreach ($filteredData as $row) {
    $issuedValue   = '-';
    $adjustedValue = $runningBalance;

    if (isset($row[2])) {
        $action = strtolower(trim($row[2]));
        if ($action === 'issue') {
            $issuedValue = '-1';
            $runningBalance -= 1;
        } elseif ($action === 'return') {
            $issuedValue = '+1';
            $runningBalance += 1;
        }
        $adjustedValue = $runningBalance;
    }

    $html .= "<tr>
        <td>".($row[1] ?? '')."</td>
        <td>".($row[2] ?? '')." to ".($row[7] ?? '')." (".($row[9] ?? '').")<br>".($row[5] ?? '')."</td>
        <td>".($row[6] ?? '')."</td>
        <td>-</td>
        <td>$issuedValue</td>
        <td>$adjustedValue</td>
        <td>-</td>
    </tr>";
}
$html .= "</table>";

$html .= "
<div class='footer'>
  Authorized Signature <br><br>
  Date: ".date("d/m/Y")." <br>
  Time: ".date("h:i A")."
</div>
</body>
</html>
";

// --- Dompdf setup ---
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// ✅ fix blank/corrupt output
if (ob_get_length()) ob_end_clean();

$dompdf->stream("inventory_report.pdf", ["Attachment" => 1]);
