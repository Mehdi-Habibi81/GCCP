<?php
declare(strict_types=1);

/**
 * Shared helpers for the lab digitization module.
 * Include this after auth/config.php (which provides $pdo and starts the session).
 *
 * Requires: composer require morilog/jalali
 * (run this from the project root, where composer.json already lives)
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Morilog\Jalali\Jalalian;

/**
 * Make sure the user is logged in before showing any lab page.
 */
function lab_require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: /auth/login');
        exit;
    }
}

/**
 * Is the currently-logged-in user an admin? Requires the
 * is_admin column added in schema_v9.sql.
 */
function lab_is_admin(PDO $pdo): bool
{
    if (empty($_SESSION['user_id'])) {
        return false;
    }
    $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $_SESSION['user_id']]);
    return (int)($stmt->fetchColumn() ?: 0) === 1;
}

/**
 * Redirect away (to the dashboard) unless the current user is an
 * admin. Call this at the top of admin-only pages.
 */
function lab_require_admin(PDO $pdo): void
{
    lab_require_login();
    if (!lab_is_admin($pdo)) {
        header('Location: /dashboard');
        exit;
    }
}

/**
 * Record one row in activity_logs. Call this right after any
 * meaningful lab action (sample created/edited, result recorded,
 * main sheet finalized/sent, etc).
 */
function lab_log_activity(PDO $pdo, string $action, ?string $details = null): void
{
    $userId = $_SESSION['user_id'] ?? null;
    $username = null;

    if ($userId) {
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $userId]);
        $username = $stmt->fetchColumn() ?: null;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO activity_logs (user_id, username, action, details)
         VALUES (:user_id, :username, :action, :details)"
    );
    $stmt->execute([
        'user_id'  => $userId,
        'username' => $username,
        'action'   => $action,
        'details'  => $details,
    ]);
}

/**
 * Recent activity log entries for the admin log viewer.
 */
function lab_get_activity_logs(PDO $pdo, int $limit = 200): array
{
    $stmt = $pdo->prepare("SELECT * FROM activity_logs ORDER BY id DESC LIMIT :limit");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Recent login attempts (success and failure) for the admin log viewer.
 */
function lab_get_login_logs(PDO $pdo, int $limit = 200): array
{
    $stmt = $pdo->prepare("SELECT * FROM login_logs ORDER BY id DESC LIMIT :limit");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * All test types, for building the "which internal log sheets
 * still need data" or analytics dropdowns.
 */
 */
function lab_get_sample_types(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT id, code, name_fa FROM sample_types ORDER BY code");
    return $stmt->fetchAll();
}

/**
 * The 7 main log sheet product types, for the (now required)
 * "which main log sheet does this sample belong to" dropdown.
 */
function lab_get_main_log_sheet_types(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT id, code, name_fa FROM main_log_sheet_types ORDER BY id");
    return $stmt->fetchAll();
}

/**
 * Generate the sample number as: {jalali_year}-{type_code:02d}-{sequence:03d}
 * e.g. 1405-02-001
 *
 * The sequence is ONE shared counter per Jalali year across ALL sample
 * types — two samples registered in the same year never get the same
 * sequence number, regardless of their type.
 *
 * Uses a dedicated counter table (sample_number_counters) with an
 * atomic INSERT ... ON DUPLICATE KEY UPDATE + LAST_INSERT_ID() trick,
 * so concurrent requests can never be handed the same sequence number.
 */
function lab_generate_sample_number(PDO $pdo, int $typeCode, int $jalaliYear): string
{
    $stmt = $pdo->prepare(
        "INSERT INTO sample_number_counters (jalali_year, last_seq)
         VALUES (:jalali_year, 1)
         ON DUPLICATE KEY UPDATE last_seq = LAST_INSERT_ID(last_seq + 1)"
    );
    $stmt->execute(['jalali_year' => $jalaliYear]);

    $seq = (int) $pdo->lastInsertId();

    return sprintf('%d-%02d-%03d', $jalaliYear, $typeCode, $seq);
}

/**
 * Convert a Jalali date string like "1404/06/16" or "1404-06-16"
 * into a Gregorian "Y-m-d" string for storage. Returns null on
 * invalid input so the caller can show a validation error.
 */
function lab_jalali_to_gregorian(string $jalaliDate): ?string
{
    $normalized = str_replace('-', '/', trim($jalaliDate));
    try {
        return Jalalian::fromFormat('Y/m/d', $normalized)->toCarbon()->format('Y-m-d');
    } catch (\Throwable $e) {
        return null;
    }
}

/**
 * Convert a stored Gregorian "Y-m-d" date back to Jalali "Y/m/d"
 * for display in forms and tables.
 */
function lab_gregorian_to_jalali(string $gregorianDate): string
{
    try {
        return Jalalian::fromFormat('Y-m-d', $gregorianDate)->format('Y/m/d');
    } catch (\Throwable $e) {
        return $gregorianDate;
    }
}

/**
 * Current Jalali year (used for numbering new samples).
 */
function lab_current_jalali_year(): int
{
    return (int) Jalalian::now()->getYear();
}

/**
 * Fetch a single sample by id, with Jalali-formatted dates for
 * pre-filling the edit form.
 */
function lab_get_sample_by_id(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        "SELECT s.*, st.name_fa AS type_name, st.code AS type_code,
                mlt.name_fa AS main_log_sheet_type_name
         FROM samples s
         JOIN sample_types st ON s.sample_type_id = st.id
         LEFT JOIN main_log_sheet_types mlt ON s.main_log_sheet_type_id = mlt.id
         WHERE s.id = :id
         LIMIT 1"
    );
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $row['sampling_date_fa'] = $row['sampling_date'] ? lab_gregorian_to_jalali($row['sampling_date']) : '';
    $row['delivery_date_fa'] = $row['delivery_date'] ? lab_gregorian_to_jalali($row['delivery_date']) : '';
    return $row;
}

/**
 * Fetch recent samples (with type name and Jalali dates) for the
 * listing table on indicator.php
 */
function lab_get_recent_samples(PDO $pdo, int $limit = 50): array
{
    $stmt = $pdo->prepare(
        "SELECT s.id, s.sample_number, mlt.name_fa AS main_log_sheet_type_name,
                st.name_fa AS type_name,
                s.quantity, s.quantity_unit,
                s.sampling_date, s.delivery_date,
                s.sampling_location, s.referrer, s.receiver, s.status
         FROM samples s
         JOIN sample_types st ON s.sample_type_id = st.id
         LEFT JOIN main_log_sheet_types mlt ON s.main_log_sheet_type_id = mlt.id
         ORDER BY s.id DESC
         LIMIT :limit"
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['sampling_date_fa'] = $row['sampling_date'] ? lab_gregorian_to_jalali($row['sampling_date']) : '';
        $row['delivery_date_fa'] = $row['delivery_date'] ? lab_gregorian_to_jalali($row['delivery_date']) : '';
    }

    return $rows;
}

/**
 * Look up a sample_type id by its exact Persian name (used by the
 * Excel importer, which receives type names, not ids).
 */
function lab_get_sample_type_id_by_name(PDO $pdo, string $name): ?int
{
    $stmt = $pdo->prepare("SELECT id FROM sample_types WHERE name_fa = :name LIMIT 1");
    $stmt->execute(['name' => trim($name)]);
    $id = $stmt->fetchColumn();
    return $id !== false ? (int)$id : null;
}

/**
 * Look up a main_log_sheet_type id by its exact Persian name.
 */
function lab_get_main_log_sheet_type_id_by_name(PDO $pdo, string $name): ?int
{
    $stmt = $pdo->prepare("SELECT id FROM main_log_sheet_types WHERE name_fa = :name LIMIT 1");
    $stmt->execute(['name' => trim($name)]);
    $id = $stmt->fetchColumn();
    return $id !== false ? (int)$id : null;
}

/**
 * All samples (no limit) for the Excel export.
 */
function lab_get_all_samples_for_export(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT s.sample_number, st.name_fa AS sample_type_name, mlt.name_fa AS main_log_sheet_type_name,
                s.quantity, s.quantity_unit, s.sampling_date, s.delivery_date,
                s.sampling_location, s.referrer, s.receiver, s.status
         FROM samples s
         JOIN sample_types st ON s.sample_type_id = st.id
         LEFT JOIN main_log_sheet_types mlt ON s.main_log_sheet_type_id = mlt.id
         ORDER BY s.id"
    );
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['sampling_date_fa'] = $row['sampling_date'] ? lab_gregorian_to_jalali($row['sampling_date']) : '';
        $row['delivery_date_fa'] = $row['delivery_date'] ? lab_gregorian_to_jalali($row['delivery_date']) : '';
    }
    return $rows;
}

/**
 * All test types (Density, Viscosity, ...) for the internal log
 * sheet selector.
 */
function lab_get_test_types(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT id, name, unit FROM test_types ORDER BY id");
    return $stmt->fetchAll();
}

/**
 * Per-test-type field definitions, matching every column on the
 * corresponding paper form (داخلی.pdf), plus which field is the
 * form's "final result" (stored in test_results.result_value —
 * this is what later feeds into the main log sheet). Every other
 * field is stored in test_results.raw_data as JSON.
 *
 * test_type ids match the seed order in schema.sql / schema_v9.sql:
 *   1 دانسیته, 2 ویسکوزیته, 3 توانائی جداسازی هوا,
 *   4 توانائی جداسازی آب, 5 اندازه‌گیری کف, 6 مقدار آب,
 *   7 عدد خنثی‌سازی, 8 نقطه ابری شدن, 9 نقطه انجماد,
 *   10 نقطه ریزش, 11 خوردگی مس, 12 خوردگی فولاد
 */
function lab_get_internal_test_fields(int $testTypeId): array
{
    $defs = [
        1 => [ // دانسیته — FG-PC-0865
            'fields' => [
                ['key' => 'pycnometer_empty_weight',  'label' => 'وزن پیکنومتر خالی', 'unit' => 'g'],
                ['key' => 'pycnometer_sample_weight', 'label' => 'وزن پیکنومتر و نمونه', 'unit' => 'g'],
                ['key' => 'temperature',              'label' => 'درجه حرارت', 'unit' => '°C'],
                ['key' => 'sample_weight',            'label' => 'وزن نمونه', 'unit' => 'g'],
            ],
            'result_label' => 'دانسیته در ۱۵ درجه سانتیگراد',
            'result_unit'  => 'g/cm3',
        ],
        2 => [ // ویسکوزیته — FG-PC-0864
            'fields' => [
                ['key' => 'density_at_test_temp',   'label' => 'دانسیته در درجه حرارت آزمایش', 'unit' => 'g/cm3'],
                ['key' => 'avg_dynamic_viscosity',  'label' => 'ویسکوزیته دینامیک متوسط', 'unit' => 'mPa.S'],
                ['key' => 'torque',                 'label' => 'گشتاور', 'unit' => 'N.Cm'],
                ['key' => 'temperature',            'label' => 'درجه حرارت', 'unit' => '°C'],
                ['key' => 'speed',                  'label' => 'سرعت', 'unit' => null],
                ['key' => 'instrument_number',      'label' => 'شماره (دستگاه)', 'unit' => null],
            ],
            'result_label' => 'ویسکوزیته سینماتیک',
            'result_unit'  => 'mm2/s',
        ],
        3 => [ // توانائی جداسازی هوا — FG-PC-0861
            'fields' => [
                ['key' => 'initial_density', 'label' => 'دانسیته اولیه', 'unit' => null],
                ['key' => 'final_density',   'label' => 'دانسیته نهایی', 'unit' => null],
            ],
            'result_label' => 'زمان جداسازی',
            'result_unit'  => 'min',
        ],
        4 => [ // توانائی جداسازی آب — FG-PC-0860
            'fields' => [
                ['key' => 'test_method',              'label' => 'روش آزمایش', 'unit' => null],
                ['key' => 'collected_water_volume',   'label' => 'حجم آب جمع‌آوری‌شده', 'unit' => null],
            ],
            'result_label' => 'زمان جداسازی',
            'result_unit'  => 'Sec',
        ],
        5 => [ // اندازه‌گیری کف — FG-PC-0863 (3 stages: 24°C / 93.5°C / 24°C)
            'fields' => [
                ['key' => 'report_number', 'label' => 'شماره گزارش', 'unit' => null],

                ['key' => 'foam_volume_s1',      'label' => 'حجم کف — نمونه اول در ۲۴°C', 'unit' => 'cm3'],
                ['key' => 'foam_stability_s1',   'label' => 'پایداری کف — نمونه اول در ۲۴°C', 'unit' => null],
                ['key' => 'foam_break_time_s1',  'label' => 'زمان از بین رفتن کف — نمونه اول در ۲۴°C', 'unit' => null],
                ['key' => 'air_volume_s1',       'label' => 'حجم هوای مصرفی — نمونه اول در ۲۴°C', 'unit' => null],

                ['key' => 'foam_volume_s2',      'label' => 'حجم کف — نمونه دوم در ۹۳.۵°C', 'unit' => 'cm3'],
                ['key' => 'foam_stability_s2',   'label' => 'پایداری کف — نمونه دوم در ۹۳.۵°C', 'unit' => null],
                ['key' => 'foam_break_time_s2',  'label' => 'زمان از بین رفتن کف — نمونه دوم در ۹۳.۵°C', 'unit' => null],
                ['key' => 'air_volume_s2',       'label' => 'حجم هوای مصرفی — نمونه دوم در ۹۳.۵°C', 'unit' => null],

                ['key' => 'foam_volume_s3',      'label' => 'حجم کف — نمونه دوم در ۲۴°C', 'unit' => 'cm3'],
                ['key' => 'foam_stability_s3',   'label' => 'پایداری کف — نمونه دوم در ۲۴°C', 'unit' => null],
                ['key' => 'foam_break_time_s3',  'label' => 'زمان از بین رفتن کف — نمونه دوم در ۲۴°C', 'unit' => null],
                ['key' => 'air_volume_s3',       'label' => 'حجم هوای مصرفی — نمونه دوم در ۲۴°C', 'unit' => null],
            ],
            'result_label' => 'نتیجه‌ی کلی (اختیاری)',
            'result_unit'  => null,
        ],
        6 => [ // مقدار آب — FG-PC-0868
            'fields' => [
                ['key' => 'sample_weight', 'label' => 'وزن نمونه', 'unit' => 'g'],
            ],
            'result_label' => 'مقدار آب',
            'result_unit'  => '%wt',
        ],
        7 => [ // عدد خنثی‌سازی — FG-PC-0859
            'fields' => [
                ['key' => 'sample_weight',      'label' => 'وزن نمونه', 'unit' => null],
                ['key' => 'sample_titration',   'label' => 'تیتراسیون نمونه KOH/HCl', 'unit' => null],
                ['key' => 'blank_titration',    'label' => 'تیتراسیون صفر KOH/HCl', 'unit' => null],
            ],
            'result_label' => 'عدد خنثی‌سازی',
            'result_unit'  => 'mgKOH/g',
        ],
        8 => [ // نقطه ابری شدن — FG-PC-1150
            'fields' => [
                ['key' => 'bath_temperature', 'label' => 'درجه حرارت حمام', 'unit' => '°C'],
            ],
            'result_label' => 'نقطه ابری شدن',
            'result_unit'  => '°C',
        ],
        9 => [ // نقطه انجماد — FG-PC-1150
            'fields' => [
                ['key' => 'bath_temperature', 'label' => 'درجه حرارت حمام', 'unit' => '°C'],
            ],
            'result_label' => 'نقطه انجماد',
            'result_unit'  => '°C',
        ],
        10 => [ // نقطه ریزش — FG-PC-1150
            'fields' => [
                ['key' => 'bath_temperature', 'label' => 'درجه حرارت حمام', 'unit' => '°C'],
            ],
            'result_label' => 'نقطه ریزش',
            'result_unit'  => '°C',
        ],
        11 => [ // خوردگی مس — FG-PC-0866
            'fields' => [
                ['key' => 'test_duration', 'label' => 'مدت آزمایش', 'unit' => null],
                ['key' => 'temperature',   'label' => 'درجه حرارت', 'unit' => '°C'],
            ],
            'result_label' => 'درجه خوردگی',
            'result_unit'  => null,
        ],
        12 => [ // خوردگی فولاد — FG-PC-0867
            'fields' => [
                ['key' => 'test_duration',     'label' => 'مدت آزمایش', 'unit' => null],
                ['key' => 'corrosion_status',  'label' => 'وضعیت خوردگی', 'unit' => null],
            ],
            'result_label' => 'درجه خوردگی',
            'result_unit'  => null,
        ],
    ];

    return $defs[$testTypeId] ?? ['fields' => [], 'result_label' => 'نتیجه', 'result_unit' => null];
}

/**
 * Get the single ongoing internal log sheet for a test type,
 * creating it the first time it's needed. This models the paper
 * workflow: one notebook per test type that keeps accumulating
 * entries over time (rather than one sheet per day/batch).
 */
function lab_get_or_create_internal_sheet(PDO $pdo, int $testTypeId): int
{
    $stmt = $pdo->prepare(
        "SELECT id FROM internal_log_sheets WHERE test_type_id = :test_type_id LIMIT 1"
    );
    $stmt->execute(['test_type_id' => $testTypeId]);
    $row = $stmt->fetch();
    if ($row) {
        return (int) $row['id'];
    }

    $typeStmt = $pdo->prepare("SELECT name FROM test_types WHERE id = :id");
    $typeStmt->execute(['id' => $testTypeId]);
    $typeName = $typeStmt->fetchColumn() ?: '';

    $insert = $pdo->prepare(
        "INSERT INTO internal_log_sheets (test_type_id, sheet_date, title)
         VALUES (:test_type_id, CURDATE(), :title)"
    );
    $insert->execute([
        'test_type_id' => $testTypeId,
        'title'        => 'لاگ‌شیت ' . $typeName,
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * Samples for the "which sample is this result for" dropdown.
 * Ordered newest first; limited so the dropdown stays usable.
 */
function lab_get_samples_for_select(PDO $pdo, int $limit = 200): array
{
    $stmt = $pdo->prepare(
        "SELECT s.id, s.sample_number, mlt.name_fa AS main_log_sheet_type_name
         FROM samples s
         LEFT JOIN main_log_sheet_types mlt ON s.main_log_sheet_type_id = mlt.id
         ORDER BY s.id DESC
         LIMIT :limit"
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Test results already recorded on a given internal log sheet,
 * with the related sample number/type and Jalali test date.
 */
function lab_get_results_for_sheet(PDO $pdo, int $sheetId): array
{
    $stmt = $pdo->prepare(
        "SELECT tr.id, tr.result_value, tr.raw_data, tr.tested_date, tr.is_used_in_main_sheet,
                s.sample_number, mlt.name_fa AS main_log_sheet_type_name
         FROM test_results tr
         JOIN samples s ON tr.sample_id = s.id
         LEFT JOIN main_log_sheet_types mlt ON s.main_log_sheet_type_id = mlt.id
         WHERE tr.internal_log_sheet_id = :sheet_id
         ORDER BY tr.id DESC"
    );
    $stmt->execute(['sheet_id' => $sheetId]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['tested_date_fa'] = lab_gregorian_to_jalali($row['tested_date']);
        $row['raw_data_decoded'] = $row['raw_data'] ? json_decode($row['raw_data'], true) : [];
    }

    return $rows;
}

/**
 * Get the main log sheet for a sample, creating it the first
 * time it's needed (one main log sheet per sample).
 */
function lab_get_or_create_main_sheet(PDO $pdo, int $sampleId): int
{
    $stmt = $pdo->prepare("SELECT id FROM main_log_sheets WHERE sample_id = :sample_id LIMIT 1");
    $stmt->execute(['sample_id' => $sampleId]);
    $row = $stmt->fetch();
    if ($row) {
        return (int) $row['id'];
    }

    $insert = $pdo->prepare(
        "INSERT INTO main_log_sheets (sample_id, compiled_date, status)
         VALUES (:sample_id, CURDATE(), 'draft')"
    );
    $insert->execute(['sample_id' => $sampleId]);

    return (int) $pdo->lastInsertId();
}

/**
 * The fixed test rows for a sample's main log sheet type, each
 * joined to any result already saved on this main log sheet.
 */
function lab_get_main_sheet_rows(PDO $pdo, int $mainLogSheetId, int $mainLogSheetTypeId): array
{
    $stmt = $pdo->prepare(
        "SELECT d.id AS test_definition_id, d.row_order, d.test_name, d.unit, d.method,
                d.limit_new, d.limit_used, d.test_location,
                r.result_value
         FROM main_log_sheet_test_definitions d
         LEFT JOIN main_log_sheet_results r
                ON r.test_definition_id = d.id AND r.main_log_sheet_id = :main_log_sheet_id
         WHERE d.main_log_sheet_type_id = :main_log_sheet_type_id
         ORDER BY d.row_order"
    );
    $stmt->execute([
        'main_log_sheet_id'      => $mainLogSheetId,
        'main_log_sheet_type_id' => $mainLogSheetTypeId,
    ]);
    return $stmt->fetchAll();
}

/**
 * Save (insert or update) the result value for one test row on a
 * main log sheet. Uses the uniq_sheet_test constraint (schema_v8)
 * so repeated saves update in place instead of duplicating rows.
 */
function lab_save_main_sheet_result(PDO $pdo, int $mainLogSheetId, int $testDefinitionId, string $resultValue): void
{
    $stmt = $pdo->prepare(
        "INSERT INTO main_log_sheet_results (main_log_sheet_id, test_definition_id, result_value)
         VALUES (:main_log_sheet_id, :test_definition_id, :result_value)
         ON DUPLICATE KEY UPDATE result_value = VALUES(result_value)"
    );
    $stmt->execute([
        'main_log_sheet_id'  => $mainLogSheetId,
        'test_definition_id' => $testDefinitionId,
        'result_value'       => $resultValue,
    ]);
}
