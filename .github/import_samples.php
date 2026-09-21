<?php
declare(strict_types=1);

require __DIR__ . '/../auth/config.php';
require __DIR__ . '/functions.php';

lab_require_login();

use PhpOffice\PhpSpreadsheet\IOFactory;

$results = []; // one entry per processed row: ['row'=>n,'status'=>'ok'|'error','message'=>...]
$importedCount = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $results[] = ['row' => '-', 'status' => 'error', 'message' => 'آپلود فایل ناموفق بود.'];
    } else {
        try {
            $spreadsheet = IOFactory::load($file['tmp_name']);
            $sheet = $spreadsheet->getSheetByName('نمونه‌ها') ?? $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestDataRow();

            for ($rowNum = 2; $rowNum <= $highestRow; $rowNum++) {
                $sampleTypeName     = trim((string)$sheet->getCell('A' . $rowNum)->getValue());
                $mainLogTypeName    = trim((string)$sheet->getCell('B' . $rowNum)->getValue());
                $quantity           = trim((string)$sheet->getCell('C' . $rowNum)->getValue());
                $quantityUnit       = trim((string)$sheet->getCell('D' . $rowNum)->getValue());
                $samplingDateJ      = trim((string)$sheet->getCell('E' . $rowNum)->getValue());
                $deliveryDateJ      = trim((string)$sheet->getCell('F' . $rowNum)->getValue());
                $samplingLocation   = trim((string)$sheet->getCell('G' . $rowNum)->getValue());
                $referrer           = trim((string)$sheet->getCell('H' . $rowNum)->getValue());
                $receiver           = trim((string)$sheet->getCell('I' . $rowNum)->getValue());

                // Skip fully empty rows
                if ($sampleTypeName === '' && $mainLogTypeName === '') {
                    continue;
                }

                $rowErrors = [];

                $sampleTypeId = $sampleTypeName !== '' ? lab_get_sample_type_id_by_name($pdo, $sampleTypeName) : null;
                if ($sampleTypeName === '' || $sampleTypeId === null) {
                    $rowErrors[] = 'نوع نمونه "' . $sampleTypeName . '" شناخته‌شده نیست (به شیت «نام‌های مجاز» مراجعه کنید).';
                }

                $mainLogTypeId = $mainLogTypeName !== '' ? lab_get_main_log_sheet_type_id_by_name($pdo, $mainLogTypeName) : null;
                if ($mainLogTypeName === '' || $mainLogTypeId === null) {
                    $rowErrors[] = 'نوع لاگ‌شیت اصلی "' . $mainLogTypeName . '" شناخته‌شده نیست.';
                }

                $samplingDateG = null;
                if ($samplingDateJ !== '') {
                    $samplingDateG = lab_jalali_to_gregorian($samplingDateJ);
                    if ($samplingDateG === null) {
                        $rowErrors[] = 'تاریخ نمونه‌گیری معتبر نیست: ' . $samplingDateJ;
                    }
                }

                $deliveryDateG = null;
                if ($deliveryDateJ !== '') {
                    $deliveryDateG = lab_jalali_to_gregorian($deliveryDateJ);
                    if ($deliveryDateG === null) {
                        $rowErrors[] = 'تاریخ تحویل معتبر نیست: ' . $deliveryDateJ;
                    }
                }

                if ($quantity !== '' && !is_numeric($quantity)) {
                    $rowErrors[] = 'مقدار نمونه باید عدد باشد: ' . $quantity;
                }

                if ($rowErrors) {
                    $results[] = ['row' => $rowNum, 'status' => 'error', 'message' => implode(' / ', $rowErrors)];
                    continue;
                }

                $jalaliYear = lab_current_jalali_year();
                $typeCodeStmt = $pdo->prepare("SELECT code FROM sample_types WHERE id = :id");
                $typeCodeStmt->execute(['id' => $sampleTypeId]);
                $typeCode = (int)$typeCodeStmt->fetchColumn();

                $sampleNumber = lab_generate_sample_number($pdo, $typeCode, $jalaliYear);

                $stmt = $pdo->prepare(
                    "INSERT INTO samples
                        (sample_number, sample_type_id, main_log_sheet_type_id, jalali_year, quantity, quantity_unit,
                         sampling_date, delivery_date, sampling_location, referrer, receiver, status)
                     VALUES
                        (:sample_number, :sample_type_id, :main_log_sheet_type_id, :jalali_year, :quantity, :quantity_unit,
                         :sampling_date, :delivery_date, :sampling_location, :referrer, :receiver, 'in_progress')"
                );
                $stmt->execute([
                    'sample_number'          => $sampleNumber,
                    'sample_type_id'         => $sampleTypeId,
                    'main_log_sheet_type_id' => $mainLogTypeId,
                    'jalali_year'            => $jalaliYear,
                    'quantity'               => $quantity !== '' ? $quantity : null,
                    'quantity_unit'          => $quantityUnit !== '' ? $quantityUnit : null,
                    'sampling_date'          => $samplingDateG,
                    'delivery_date'          => $deliveryDateG,
                    'sampling_location'      => $samplingLocation !== '' ? $samplingLocation : null,
                    'referrer'               => $referrer !== '' ? $referrer : null,
                    'receiver'               => $receiver !== '' ? $receiver : null,
                ]);

                $importedCount++;
                $results[] = ['row' => $rowNum, 'status' => 'ok', 'message' => 'ثبت شد با شماره ' . $sampleNumber];
            }

            if ($importedCount > 0) {
                lab_log_activity($pdo, 'samples_imported', $importedCount . ' نمونه از طریق اکسل');
            }
        } catch (\Throwable $e) {
            $results[] = ['row' => '-', 'status' => 'error', 'message' => 'خطا در خواندن فایل: ' . $e->getMessage()];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="/assets/fonts.css">
    <title>ایمپورت نمونه‌ها از اکسل</title>
    <style>
        body { font-family: 'IRANSans', Tahoma, sans-serif; background:#f7f7f9; margin:0; padding:24px; }
        .card { background:#fff; border-radius:10px; padding:24px; max-width:820px; margin:0 auto 24px; box-shadow:0 1px 4px rgba(0,0,0,.08); }
        h1 { font-size:20px; margin-top:0; }
        .back-link { display:inline-block; margin-bottom:16px; color:#2f6fed; text-decoration:none; font-size:14px; }
        .btn { display:inline-block; padding:10px 20px; border-radius:6px; text-decoration:none; font-size:14px; margin-left:8px; }
        .btn-primary { background:#2f6fed; color:#fff; border:none; cursor:pointer; }
        .btn-secondary { background:#6c757d; color:#fff; }
        table { width:100%; border-collapse:collapse; font-size:13px; margin-top:16px; }
        th, td { padding:8px; border-bottom:1px solid #eee; text-align:right; }
        th { color:#666; font-weight:normal; }
        .badge { padding:2px 8px; border-radius:10px; font-size:12px; }
        .badge-ok { background:#d4edda; color:#155724; }
        .badge-error { background:#f8d7da; color:#721c24; }
        .summary { margin-top:12px; font-size:14px; }
    </style>
</head>
<body>

    <div class="card">
        <a class="back-link" href="indicator.php">→ بازگشت به دفتر اندیکاتور</a>
        <h1>ایمپورت گروهی نمونه‌ها از اکسل</h1>
        <p style="font-size:14px;color:#555;">
            اول قالب رو دانلود کن، طبق ستون‌هاش (و نام‌های دقیق نوع نمونه/لاگ‌شیت که توی شیت دوم فایل نوشته شده) پرش کن، بعد همینجا آپلودش کن.
        </p>

        <a class="btn btn-secondary" href="download_import_template.php">دانلود قالب اکسل</a>
        <a class="btn btn-secondary" href="export_samples.php">خروجی اکسل نمونه‌های فعلی</a>

        <form method="post" enctype="multipart/form-data" style="margin-top:20px;">
            <input type="file" name="excel_file" accept=".xlsx,.xls" required>
            <button type="submit" class="btn btn-primary">آپلود و ثبت</button>
        </form>

        <?php if ($results): ?>
            <div class="summary">
                <b><?= (int)$importedCount ?></b> ردیف با موفقیت ثبت شد از مجموع <b><?= count($results) ?></b> ردیف بررسی‌شده.
            </div>
            <table>
                <thead>
                    <tr><th>ردیف</th><th>وضعیت</th><th>پیام</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)$r['row']) ?></td>
                            <td><span class="badge <?= $r['status'] === 'ok' ? 'badge-ok' : 'badge-error' ?>">
                                <?= $r['status'] === 'ok' ? 'موفق' : 'خطا' ?>
                            </span></td>
                            <td><?= htmlspecialchars($r['message']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</body>
</html>
