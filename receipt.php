<?php
require_once 'db_fee.php';

$rno = trim($_GET['r'] ?? '');
if (!$rno) die("<h2 style='text-align:center;padding:100px;color:red;'>Invalid Receipt</h2>");

$res = $conn->query("SELECT * FROM fee_receipts WHERE receipt_no = '" . esc($rno) . "' LIMIT 1");
if (!$res || $res->num_rows == 0) die("<h2 style='text-align:center;padding:100px;color:red;'>Receipt Not Found</h2>");

$r = $res->fetch_assoc();
$is_puc = isPUC($r['course']);
$total_fee = getFee($r['course'], $r['stream']);
$all_paid = getPaidAmount($conn, $r['student_id'], $is_puc ? $r['year'] : $r['semester'], $is_puc);
$balance = max(0, $total_fee - $all_paid);

function inWords($num) {
    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
             'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    $num = (int)$num;
    if ($num == 0) return 'Zero';
    $func = function($n) use (&$func, $ones, $tens) {
        if ($n < 20) return $ones[$n];
        if ($n < 100) return $tens[(int)($n/10)] . ($n%10 ? ' ' . $ones[$n%10] : '');
        if ($n < 1000) return $ones[(int)($n/100)] . ' Hundred' . ($n%100 ? ' ' . $func($n%100) : '');
        if ($n < 100000) return $func((int)($n/1000)) . ' Thousand' . ($n%1000 ? ' ' . $func($n%1000) : '');
        if ($n < 10000000) return $func((int)($n/100000)) . ' Lakh' . ($n%100000 ? ' ' . $func($n%100000) : '');
        return $func((int)($n/10000000)) . ' Crore' . ($n%10000000 ? ' ' . $func($n%10000000) : '');
    };
    return $func($num) . ' Rupees Only';
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Receipt <?=h($r['receipt_no'])?></title>
<style>
body{font-family:Arial;background:#eee;padding:30px;}
.receipt{max-width:800px;margin:0 auto;background:white;padding:40px;border:4px double #000;box-shadow:0 0 20px rgba(0,0,0,.3);}
h1,h2{text-align:center;margin:15px 0;}
table{width:100%;border-collapse:collapse;margin:25px 0;}
td,th{border:1px solid #000;padding:12px;}
th{background:#000;color:white;}
.right{text-align:right;}
.center{text-align:center;}
.big{font-size:26px;font-weight:bold;color:#c00;}
.words{background:#f0f0f0;padding:20px;border-left:5px solid #c00;margin:25px 0;font-style:italic;text-align:center;}
.sign{margin-top:80px;display:flex;justify-content:space-between;font-weight:bold;font-size:18px;}
@media print{body{margin:0;background:white;}.no-print{display:none;}}
</style>
</head>
<body>

<button onclick="window.print()" class="no-print" style="padding:15px 30px;background:#c00;color:white;border:none;border-radius:8px;font-size:18px;cursor:pointer;margin:20px auto;display:block;">
    Print Receipt
</button>

<div class="receipt">
    <h1>AUREON COLLEGE</h1>
    <h2>OFFICIAL FEE RECEIPT</h2>
    <p class="center"><b>Receipt No:</b> <?=h($r['receipt_no'])?> | <b>Date:</b> <?=date('d-m-Y', strtotime($r['date']))?></p>
    <hr style="border:2px dashed #000;margin:20px 0;">

    <table>
        <tr><th width="35%">Student ID</th><td><?=h($r['student_id'])?></td></tr>
        <tr><th>Name</th><td><?=h($r['name'])?></td></tr>
        <tr><th>Course / Stream</th><td><?=h($r['course'])?> - <?=h($r['stream'])?></td></tr>
        <tr><th><?= $is_puc ? 'Year' : 'Year / Semester' ?></th><td><?= $r['year'] ?> <?= !$is_puc ? '/ Semester '.$r['semester'] : '' ?></td></tr>
        <tr><th>Payment Type</th><td><?=h($r['payment_type'])?></td></tr>
        <tr><th>Amount Paid</th><td class="big right">₹ <?=number_format($r['amount_paid'])?>/-</td></tr>
        <tr><th>Payment Mode</th><td><?=h($r['payment_mode'])?> <?= $r['transaction_id'] ? ' | '.$r['transaction_id'] : '' ?></td></tr>
        <?php if($balance > 0): ?>
        <tr><th>Balance Due</th><td class="big right" style="color:red;">₹ <?=number_format($balance)?>/-</td></tr>
        <?php else: ?>
        <tr><th>Status</th><td style="color:green;font-size:22px;font-weight:bold;">PAID IN FULL</td></tr>
        <?php endif; ?>
    </table>

    <div class="words">
        <b>In Words:</b> <?=inWords($r['amount_paid'])?>
    </div>

    <div class="sign">
        <div>_________________<br>Student Signature</div>
        <div>_________________<br>Accounts Officer</div>
    </div>

    <p class="center" style="margin-top:60px;font-size:14px;color:#555;">
        Computer Generated Receipt • No Signature Required
    </p>
</div>
</body>
</html>