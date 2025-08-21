<?php
include __DIR__ . '/../src/google_sheet.php';

// Remove first row (headers)
array_shift($values);

// Extract unique categories & make/models from sheet
$categories = [];
$makeModels = [];
foreach ($values as $row) {
    if (!empty($row[3])) $categories[] = trim($row[3]);
    if (!empty($row[4])) $makeModels[] = trim($row[4]);
}
$categories = array_unique($categories);
$makeModels = array_unique($makeModels);
sort($categories);
sort($makeModels);

// Get filter inputs
$category     = $_GET['category']     ?? '';
$makeModel    = $_GET['make_model']   ?? '';
$lastIndent   = $_GET['last_indent']  ?? '';
$fromDate     = $_GET['from_date']    ?? '';
$entryPage    = $_GET['entry_page']   ?? '';
$entryRegister= $_GET['entry_register'] ?? '';
$entryProduct = $_GET['entry_product']  ?? '';
$entryValue   = isset($_GET['entry_value']) ? (int)$_GET['entry_value'] : null;
$resultLimit   = isset($_GET['limit']) ? (int)$_GET['limit'] : 100000;

// --- Initialize ---
$filteredData = [];
if (!empty($_GET)) {
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

    // --- Sort by date + indent ---
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

    $filteredData = array_slice($filteredData, 0, $resultLimit);

    // --- Pagination ---
    $limit = 1000;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($page - 1) * $limit;

    $totalRows = count($filteredData);
    $paginatedData = array_slice($filteredData, $offset, $limit);
    $rowCount = count($filteredData);
}
?>


<!DOCTYPE html>
<html>
<head>
  <title>CEA - INVENTORY MANAGEMENT SYSTEM</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 0;
      background: #f9f9f9;
    }
    h1 {
      text-align: center;
      margin-top: 20px;
      font-size: 26px;
      color: #222;
    }
    h2 {
      text-align: center;
      font-size: 18px;
      margin: 5px 0 20px;
      color: #555;
    }
    .filter-form {
      width: 600px;
      max-width: 95%;
      margin: 20px auto;
      padding: 20px;
      border: 1px solid #ddd;
      border-radius: 8px;
      background: #fff;
      box-shadow: 0px 2px 6px rgba(0,0,0,0.1);
    }
    .form-row {
      display: flex;
      flex-direction: column;
      margin-bottom: 15px;
    }
    .form-row label {
      font-size: 14px;
      margin-bottom: 6px;
      font-weight: bold;
      color: #333;
    }
    .filter-form select,
    .filter-form input {
      padding: 8px 10px;
      border: 1px solid #ccc;
      border-radius: 5px;
      font-size: 14px;
    }
    .form-actions {
      text-align: center;
      margin-top: 15px;
    }
    .form-actions button {
      background: #007bff;
      border: none;
      color: white;
      padding: 10px 20px;
      font-size: 15px;
      border-radius: 6px;
      cursor: pointer;
    }
    .form-actions button:hover {
      background: #0056b3;
    }
    table { border-collapse: collapse; width: 90%; margin: 20px auto;}
    th, td { border: 1px solid #ddd; padding: 2px; font-size: small;}
    th { background: #f4f4f4; }
    .pagination { text-align: center; margin-top: 20px; }
    .pagination a { display: inline-block; margin: 0 5px; padding: 6px 12px; border: 1px solid #ddd; text-decoration: none; color: #333; border-radius: 4px; }
    .pagination a:hover { background: #f0f0f0; }
    .pagination a.active { background: #007bff; color: white; border-color: #007bff; font-weight: bold; }
    button { margin: 10px; padding: 10px 20px; }

    @media print {
      .main-header,.pagination, form, a[target="_blank"], button { display: none !important; }

      body { margin: 0; }

      table { page-break-inside: auto; width: 100%; }
      tr { page-break-inside: avoid; page-break-after: auto; }

      thead { display: table-header-group; }
      tfoot { display: table-footer-group; }

      @page {
        margin-top: 10mm;
        margin-bottom: 10mm;
      }

      .footer {
        text-align: right;
        font-size: 12px;
        font-weight: bold;
        padding-right: 0mm;
      }
    }
  </style>
</head>
<body>

  <!-- Page Heading -->
   <div class="main-header" style="margin:20px auto;width:100%;"><h1 style="text-align:center;">STOCK REGISTER</h1>
        <h4 style="text-align:center;">CEA - INVENTORY MANAGEMENT SYSTEM</h4>
        <hr style="width:500px; border:1px solid black; margin:10px auto;"></div>


  <!-- Filter Form -->
  <form method="GET" class="filter-form">
    <div class="form-row">
      <label for="category">Category</label>
      <select name="category" id="category">
        <option value="">All</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= htmlspecialchars($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>>
            <?= htmlspecialchars($cat) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-row">
      <label for="make_model">Make & Model</label>
      <select name="make_model" id="make_model">
        <option value="">All</option>
        <?php foreach ($makeModels as $mm): ?>
          <option value="<?= htmlspecialchars($mm) ?>" <?= $makeModel === $mm ? 'selected' : '' ?>>
            <?= htmlspecialchars($mm) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-row">
      <label for="from_date">From Date</label>
      <input type="date" name="from_date" id="from_date" value="<?= htmlspecialchars($fromDate) ?>">
    </div>

    <div class="form-row">
      <label for="last_indent">Last Indent</label>
      <input type="text" name="last_indent" id="last_indent" value="<?= htmlspecialchars($lastIndent) ?>">
    </div>

    <div class="form-row">
      <label for="entry_page">Entry Page</label>
      <input type="number" name="entry_page" id="entry_page" value="<?= htmlspecialchars($entryPage) ?>">
    </div>

    <div class="form-row">
      <label for="entry_register">Entry Register</label>
      <input type="text" name="entry_register" id="entry_register" value="<?= htmlspecialchars($entryRegister) ?>">
    </div>

    <div class="form-row">
      <label for="entry_product">Entry Product</label>
      <input type="text" name="entry_product" id="entry_product" value="<?= htmlspecialchars($entryProduct) ?>">
    </div>

    <div class="form-row">
      <label for="entry_value">Entry Balance</label>
      <input type="number" name="entry_value" id="entry_value" value="<?= htmlspecialchars($entryValue) ?>">
    </div>

     <div class="form-row">
    <label for="limit">Result Limit</label>
    <input type="number" name="limit" id="limit" value="<?= htmlspecialchars($resultLimit) ?>">
   
  </div>

    <div class="form-actions">
      <button type="submit">Apply Filter</button>
    </div>
  </form>

  <!-- Table will only show if filter is applied -->
  <?php if (!empty($_GET)): ?>
   <table border="1" cellspacing="0" cellpadding="5" width="100%">
  <thead>
    <tr>
      <th colspan="7" style="text-align:center; border:none;">
        <h1 style="margin:0; font-size:22px;">STOCK REGISTER</h1>
        <h4 style="margin:5px 0 10px; font-size:16px;">CEA - INVENTORY MANAGEMENT SYSTEM</h4>
        <hr style="width:500px; border:1px solid black; margin:10px auto;">
        <?php if ($entryPage || $entryRegister || $entryProduct || $entryValue !== null): ?>
          <div style="text-align:center; margin:15px 0; font-weight:bold; font-size:14px;">
  <span style="display:inline-block; min-width:120px;">Page: <?= htmlspecialchars($entryPage ?? '') ?></span>
  <span style="display:inline-block; min-width:120px;">Register: <?= htmlspecialchars($entryRegister ?? '') ?></span>
  <span style="display:inline-block; min-width:120px;">Product: <?= htmlspecialchars($entryProduct ?? '') ?></span>
  <span style="display:inline-block; min-width:120px;">Balance: <?= htmlspecialchars($entryValue ?? '') ?></span>
  <span style="display:inline-block; min-width:120px;">Rows: <?= $rowCount ?? ''?> </span>
  
</div>
</div>

        <?php endif; ?>
        <div style="margin:10px 0; font-size:14px;">
          <strong>Category:</strong> <?= htmlspecialchars($category ?: 'All') ?> &nbsp;&nbsp;|
          <strong>Make & Model:</strong> <?= htmlspecialchars($makeModel ?: 'All') ?> &nbsp;&nbsp;|
          <strong>Pre. Indent:</strong> <?= htmlspecialchars($lastIndent ?: 'All') ?> &nbsp;&nbsp;|
          <strong>Pre. Date:</strong> <?= $fromDate ? date("d/m/Y", strtotime($fromDate)) : "All" ?>
        </div>
      </th>
    </tr>
    <tr>
      <th>Date</th>
      <th>Particulars</th>
      <th>Indent No.</th>
      <th>Page</th>
      <th>Issued</th>
      <th>Balance</th>
      <th>Receipt</th>
    </tr>
  </thead>
  <tbody>
<?php 
$runningBalance = $entryValue; 
foreach ($paginatedData as $row): 
    $receiptValue  = '-'; 
    $issuedValue   = '-'; 
    $adjustedValue = $runningBalance; 
    $particulars   = ($row[5] ?? ''); 

    if (isset($row[2])) {
        $action = strtolower(trim($row[2]));
        
        if ($action === 'issue') {
            $issuedValue = '-1';     
            $runningBalance -= 1;    
            $particulars = "to " . ($row[7] ?? '') . " (" . ($row[9] ?? '') . ")<br>" . ($row[5] ?? '');
        } elseif ($action === 'return') {
            $receiptValue = '+1';    
            $runningBalance += 1;    
            $particulars = "from " . ($row[7] ?? '') . " (" . ($row[9] ?? '') . ")<br>" . ($row[5] ?? '');
        }
        $adjustedValue = $runningBalance; 
    }
?>
  <tr>
    <td style="text-align:center;"><?= $row[1] ?? '' ?></td>
    <td><?= ucfirst($row[2] ?? '') ?> <?= $particulars ?></td>
    <td style="text-align:center;"><?= $row[6] ?? '' ?></td>
    <td style="text-align:center;"><?= $receiptValue ?></td>
    <td style="text-align:center;"><?= $issuedValue ?></td>
    <td style="text-align:center;"><?= $adjustedValue ?></td>
    <td style="text-align:center;">-</td>
  </tr>
<?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr>
      <td colspan="7" style="text-align: right;padding-right:0px;">
        <?php date_default_timezone_set("Asia/Kolkata"); ?>
        <div class="footer">
          Authorized Signature
          <br><br>
          Date: <?= date("d/m/Y") ?>
          <br>
          Time: <?= date("h:i A") ?>
        </div>
        
      </td>
    </tr>
  </tfoot>
</table>
<div class="pagination">
  <?php
  $totalPages = ceil($totalRows / $limit);
  $range = 2; 
  if ($page > 1): ?>
    <a href="?page=<?= $page - 1 ?>&category=<?= urlencode($category) ?>&make_model=<?= urlencode($makeModel) ?>">Prev</a>
  <?php endif; ?>

  <?php if ($page > ($range + 2)): ?>
    <a href="?page=1&category=<?= urlencode($category) ?>&make_model=<?= urlencode($makeModel) ?>">1</a>
    <span>...</span>
  <?php endif; ?>

  <?php for ($i = max(1, $page - $range); $i <= min($totalPages, $page + $range); $i++): ?>
    <a href="?page=<?= $i ?>&category=<?= urlencode($category) ?>&make_model=<?= urlencode($makeModel) ?>" class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
  <?php endfor; ?>

  <?php if ($page < $totalPages - $range - 1): ?>
    <span>...</span>
    <a href="?page=<?= $totalPages ?>&category=<?= urlencode($category) ?>&make_model=<?= urlencode($makeModel) ?>"><?= $totalPages ?></a>
  <?php endif; ?>

  <?php if ($page < $totalPages): ?>
    <a href="?page=<?= $page + 1 ?>&category=<?= urlencode($category) ?>&make_model=<?= urlencode($makeModel) ?>">Next</a>
  <?php endif; ?>
</div>

<div style="text-align:center;">
  <button type="button" onclick="window.print()">Print Page</button>
  <a href="print.php?category=<?= urlencode($category) ?>&make_model=<?= urlencode($makeModel) ?>" target="_blank" style="text-decoration:none;">
    <button type="button">Export All to PDF</button>
  </a>
</div>
<?php endif; ?>

</body>
</html>

