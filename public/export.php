<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_auth();
$currentUser = auth_user();
$isAdmin = ($currentUser['role'] ?? '') === 'admin';
$from = export_filter((string)($_GET['from'] ?? ''));
$to = export_filter((string)($_GET['to'] ?? ''));
if ($to !== null) $to = substr($to, 0, 16) . ':59';
if (!$isAdmin) {
    $from = (new DateTimeImmutable('now'))->modify('-1 month')->format('Y-m-d H:i:s');
    $to = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
}

$type = (string)($_GET['type'] ?? '');
$dateColumns = [];
$numericColumns = [];

if ($type === 'appeals') {
    $sheetName = 'Обращения';
    $filename = 'appeals_' . date('Ymd_His') . '.xlsx';
    $headers = ['ID', 'Дата и время звонка', 'ФИО оператора, зарегистрировавшего обращение', 'Адрес', 'ФИО заявителя', 'Телефон', 'Состав обращения', 'Тип обращения', 'Статус', 'Создано', 'Изменено'];
    $dateColumns = [1, 9, 10];
    $numericColumns = [0];
    $records = (new AppealRepository())->list(null, false, [], 'all', $from, $to);
    $rows = [$headers];
    foreach ($records as $record) {
        $rows[] = [
            $record['id'], $record['call_at'], $record['operator_name'], $record['address'],
            $record['fio'], $record['phone'], $record['subject'], export_appeal_type((string)$record['type']),
            $record['status'] === 'completed' ? 'Завершено' : 'В работе', $record['created_at'], $record['updated_at'],
        ];
    }
} elseif ($type === 'outages') {
    $sheetName = 'Отключения';
    $filename = 'outages_' . date('Ymd_His') . '.xlsx';
    $headers = ['ID', 'Адрес', 'ID дома', 'Тип / вид', 'Причина', 'Планируемое начало', 'Фактическое начало', 'Плановое восстановление', 'МКД, шт.', 'Социальное жильё, шт.', 'ФИО оператора, зарегистрировавшего отключение', 'Статус', 'Дата регистрации'];
    $dateColumns = [5, 6, 7, 12];
    $numericColumns = [0, 2, 8, 9];
    $records = (new OutageRepository())->list(null, 'all', 'all', $from, $to);
    $rows = [$headers];
    foreach ($records as $record) {
        $rows[] = [
            $record['id'], $record['address'], $record['house_id'],
            export_outage_type((string)$record['type']) . ' — ' . export_outage_detail((string)$record['detail_type']), $record['reason'],
            $record['type'] === 'improvement' ? null : $record['planned_start'],
            $record['type'] === 'improvement' ? null : $record['actual_start'],
            $record['type'] === 'improvement' ? null : $record['planned_end'],
            (int)($record['mkd_count'] ?? 0), (int)($record['social_count'] ?? 0), $record['operator_name'] ?? '',
            (int)$record['is_active'] === 1 ? 'Активно' : 'Восстановлено', $record['created_at'],
        ];
    }
} else {
    http_response_code(400);
    exit('Неизвестный тип экспорта.');
}

$workbook = create_xlsx($rows, $dateColumns, $numericColumns, $sheetName);
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($workbook));
header('Cache-Control: private, no-store, max-age=0');
echo $workbook;
exit;

function export_filter(string $value): ?string
{
    if ($value === '') return null;
    $value = str_replace('T', ' ', trim($value));
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $value);
    if ($date === false || $date->format('Y-m-d H:i') !== $value) {
        http_response_code(400);
        exit('Некорректный период экспорта.');
    }
    return $date->format('Y-m-d H:i:s');
}

function export_outage_type(string $type): string
{
    return ['shutdown' => 'Отключение', 'damage' => 'Повреждение', 'improvement' => 'Благоустройство'][$type] ?? $type;
}

function export_outage_detail(string $detail): string
{
    return [
        'full_shutdown' => 'Полное отключение',
        'supply_without_circulation' => 'Подача без циркуляции',
        'damage_without_shutdown' => 'Повреждение без отключения',
    ][$detail] ?? $detail;
}

/** Собирает минимальный XLSX/OOXML-пакет через стандартные функции PHP. */
function create_xlsx(array $rows, array $dateColumns, array $numericColumns, string $sheetName): string
{
    $xmlRows = [];
    $maxColumns = 0;

    foreach ($rows as $rowIndex => $row) {
        $maxColumns = max($maxColumns, count($row));
        $cells = [];
        foreach (array_values($row) as $columnIndex => $value) {
            $reference = xlsx_column_name($columnIndex + 1) . ($rowIndex + 1);
            $cells[] = xlsx_cell(
                $reference,
                $value,
                $rowIndex === 0,
                in_array($columnIndex, $dateColumns, true),
                in_array($columnIndex, $numericColumns, true)
            );
        }
        $xmlRows[] = '<row r="' . ($rowIndex + 1) . '">' . implode('', $cells) . '</row>';
    }

    $lastCell = xlsx_column_name(max(1, $maxColumns)) . max(1, count($rows));
    $escapedSheetName = xlsx_escape($sheetName);
    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<dimension ref="A1:' . $lastCell . '"/><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="15"/><sheetData>' . implode('', $xmlRows) . '</sheetData>'
        . '<autoFilter ref="A1:' . $lastCell . '"/><pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>'
        . '</worksheet>';

    $files = [
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<bookViews><workbookView/></bookViews><sheets><sheet name="' . $escapedSheetName . '" sheetId="1" r:id="rId1"/></sheets></workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>',
        'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="1"><numFmt numFmtId="164" formatCode="dd.mm.yyyy hh:mm"/></numFmts>'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/><xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>',
        'xl/worksheets/sheet1.xml' => $sheetXml,
    ];

    return xlsx_zip($files);
}

function xlsx_cell(string $reference, $value, bool $header, bool $date, bool $numeric): string
{
    if ($value === null || $value === '') {
        return '<c r="' . $reference . '"/>';
    }
    if ($date) {
        $serial = xlsx_date_serial((string)$value);
        if ($serial === null) {
            return '<c r="' . $reference . '" t="inlineStr"><is><t>' . xlsx_escape((string)$value) . '</t></is></c>';
        }
        return '<c r="' . $reference . '" s="2"><v>' . $serial . '</v></c>';
    }
    if (!$header && $numeric && is_numeric($value)) {
        return '<c r="' . $reference . '" t="n"><v>' . xlsx_escape((string)$value) . '</v></c>';
    }
    return '<c r="' . $reference . '" t="inlineStr"' . ($header ? ' s="1"' : '') . '><is><t xml:space="preserve">'
        . xlsx_escape((string)$value) . '</t></is></c>';
}

function xlsx_date_serial(string $value): ?string
{
    try {
        $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
        $epoch = new DateTimeImmutable('1899-12-30 00:00:00', new DateTimeZone('UTC'));
        $days = ((float)$date->format('U') - (float)$epoch->format('U')) / 86400;
        return rtrim(rtrim(number_format($days, 10, '.', ''), '0'), '.');
    } catch (Throwable $error) {
        return null;
    }
}

function xlsx_column_name(int $number): string
{
    $name = '';
    while ($number > 0) {
        $number--;
        $name = chr(65 + ($number % 26)) . $name;
        $number = intdiv($number, 26);
    }
    return $name;
}

function xlsx_escape(string $value): string
{
    $value = preg_replace('/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F]/u', '', $value) ?? '';
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
}

/** Формирует ZIP-контейнер через zlib, так как ZipArchive не входит в XAMPP по умолчанию. */
function xlsx_zip(array $files): string
{
    $body = '';
    $directory = '';
    $offset = 0;
    $dosTime = ((int)date('H') << 11) | ((int)date('i') << 5) | (int)(date('s') / 2);
    $dosDate = (((int)date('Y') - 1980) << 9) | ((int)date('n') << 5) | (int)date('j');

    foreach ($files as $name => $contents) {
        $name = (string)$name;
        $compressed = gzdeflate((string)$contents, 6);
        if ($compressed === false) {
            throw new RuntimeException('Не удалось сжать Excel-файл.');
        }
        $crc = (int)hexdec(hash('crc32b', (string)$contents));
        $nameLength = strlen($name);
        $compressedLength = strlen($compressed);
        $contentsLength = strlen((string)$contents);
        $localHeader = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 8, $dosTime, $dosDate, $crc, $compressedLength, $contentsLength, $nameLength, 0);
        $body .= $localHeader . $name . $compressed;

        $directory .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 8, $dosTime, $dosDate, $crc, $compressedLength, $contentsLength, $nameLength, 0, 0, 0, 0, 0, $offset)
            . $name;
        $offset += strlen($localHeader) + $nameLength + $compressedLength;
    }

    $end = pack('VvvvvVVv', 0x06054b50, 0, 0, count($files), count($files), strlen($directory), strlen($body), 0);
    return $body . $directory . $end;
}

function export_appeal_type(string $type): string
{
    return [
        'no_hot_water' => 'Нет ГВС',
        'no_heating' => 'Нет теплоснабжения',
        'information' => 'Предоставление информации',
        'other' => 'Другое',
        'false_call' => 'Ложный вызов',
    ][$type] ?? 'Другое';
}