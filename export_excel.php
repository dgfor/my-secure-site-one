<?php
session_start();
require_once 'db.php';

// Защита: скачивать могут только авторизованные пользователи
if (!isset($_SESSION['authorized'])) {
    header("Location: index.php");
    exit;
}

$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$month_title = date('m.Y', strtotime($selected_month . '-01'));

// Выставляем заголовки браузера, чтобы он понял, что мы отдаем Excel файл
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=Work_Report_($month_title).xls");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Cache-Control: private", false);

// Формируем SQL запрос точно такой же, как на главной
$stmt_hours = $pdo->prepare("SELECT employee_id, employee_name, MAX(hourly_rate) as current_rate,
                           GROUP_CONCAT(CONCAT(DATE_FORMAT(shift_start, '%d.%m.%Y'), ': ', hours_worked) ORDER BY shift_start SEPARATOR '; ') as days_detail,
                           SUM(hours_worked) as total_hours,
                           SUM(hours_worked * hourly_rate) as total_salary
                           FROM work_log 
                           WHERE DATE_FORMAT(shift_start, '%Y-%m') = ?
                           GROUP BY employee_id");
$stmt_hours->execute([$selected_month]);
$raw_data = $stmt_hours->fetchAll();

// Генерируем HTML разметку, которую Excel откроет как родную таблицу
echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://w3.org">';
echo '<head><meta http-serif="Content-Type" content="text/html; charset=utf-8" /><style>td { font-family: Arial; font-size: 11pt; border: 0.5pt solid #000; } th { font-family: Arial; font-size: 11pt; font-weight: bold; background-color: #dcdcdc; border: 0.5pt solid #000; }</style></head>';
echo '<body>';
echo '<h3>Сводный табель за период ' . $month_title . '</h3>';
echo '<table>';
echo '<thead>';
echo '<tr>';
echo '<th>Табельный номер</th>';
echo '<th>ФИО сотрудника</th>';
echo '<th>Ставка (руб/час)</th>';
echo '<th>Календарные дни и часы</th>';
echo '<th>Итого часов</th>';
echo '<th>Сумма заработка (руб)</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

if (!empty($raw_data)) {
    foreach ($raw_data as $row) {
        // Форматируем часы в текстовый вид для Excel
        $total_minutes_all = round($row['total_hours'] * 60);
        $total_h_all = floor($total_minutes_all / 60);
        $total_m_all = $total_minutes_all % 60;
        $hours_text = $total_h_all . "ч " . ($total_m_all > 0 ? $total_m_all . "м" : "00м");

        echo '<tr>';
        echo '<td>' . htmlspecialchars($row['employee_id']) . '</td>';
        echo '<td>' . htmlspecialchars($row['employee_name']) . '</td>';
        echo '<td>' . number_format($row['current_rate'], 2, '.', ' ') . '</td>';
        echo '<td>' . htmlspecialchars($row['days_detail']) . '</td>';
        echo '<td>' . $hours_text . '</td>';
        echo '<td>' . number_format($row['total_salary'], 2, '.', ' ') . '</td>';
        echo '</tr>';
    }
} else {
    echo '<tr><td colspan="6" style="text-align:center;">Нет данных за указанный период</td></tr>';
}

echo '</tbody>';
echo '</table>';
echo '</body>';
echo '</html>';
exit;
?>
