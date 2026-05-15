<?php
session_start();
require_once 'db.php';

$error = '';
$success_msg = '';

// 1. ЛОГИКА АВТОРИЗАЦИИ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_user'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (!empty($username) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['is_approved'] == 1) {
                $_SESSION['authorized'] = true;
                $_SESSION['username'] = $user['username'];
                $_SESSION['is_admin'] = $user['is_admin'];
            } else { $error = "Ваша заявка еще на рассмотрении."; }
        } else { $error = "Неверный логин или пароль."; }
    } else { $error = "Заполните все поля."; }
}

// 2. ЛОГИКА ДОБАВЛЕНИЯ СМЕНЫ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_shift'])) {
    $emp_id = trim($_POST['employee_id']);
    $emp_name = trim($_POST['employee_name']);
    $rate = floatval($_POST['hourly_rate']);
    $start_str = $_POST['shift_start'];
    $input_hours = intval($_POST['worked_hours']);
    $input_minutes = intval($_POST['worked_minutes']);

    if (!empty($emp_id) && !empty($emp_name) && !empty($start_str) && $rate >= 0 && ($input_hours > 0 || $input_minutes > 0)) {
        $start_timestamp = strtotime($start_str);
        $total_minutes = ($input_hours * 60) + $input_minutes;
        $hours_coef = round($total_minutes / 60, 4);
        $end_timestamp = $start_timestamp + ($total_minutes * 60);
        $end_str = date('Y-m-d H:i:s', $end_timestamp);

        try {
            $stmt = $pdo->prepare("INSERT INTO work_log (employee_id, employee_name, shift_start, shift_end, hours_worked, hourly_rate) 
                                   VALUES (?, ?, ?, ?, ?, ?) 
                                   ON DUPLICATE KEY UPDATE 
                                   shift_start = VALUES(shift_start),
                                   shift_end = VALUES(shift_end),
                                   hours_worked = VALUES(hours_worked),
                                   hourly_rate = VALUES(hourly_rate)");
            $stmt->execute([$emp_id, $emp_name, $start_str, $end_str, $hours_coef, $rate]);
            $success_msg = "Данные успешно сохранены в табеле!";
        } catch (Exception $e) {
            $error = "Ошибка записи: " . $e->getMessage();
        }
    } else { $error = "Заполните все поля формы корректно."; }
}

// 3. ОПРЕДЕЛЕНИЕ ТЕКУЩЕГО ФИЛЬТРА МЕСЯЦА
// По умолчанию ставим текущий месяц и год
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Получаем список всех месяцев, за которые есть записи в базе (для выпадающего списка)
$months_list = [];
if (isset($_SESSION['authorized'])) {
    $stmt_months = $pdo->query("SELECT DISTINCT DATE_FORMAT(shift_start, '%Y-%m') as m_date FROM work_log ORDER BY m_date DESC");
    $months_list = $stmt_months->fetchAll(PDO::FETCH_COLUMN);
    
    // Если база пустая, добавим хотя бы текущий месяц в список
    if (empty($months_list)) {
        $months_list[] = date('Y-m');
    }
}

// 4. СБОР ДАННЫХ ДЛЯ СВОДНОЙ ТАБЛИЦЫ С УЧЕТОМ ФИЛЬТРА
$work_data = [];
$rates_archive = [];
if (isset($_SESSION['authorized'])) {
    $stmt_hours = $pdo->prepare("SELECT employee_id, employee_name, MAX(hourly_rate) as current_rate,
                               GROUP_CONCAT(CONCAT(DATE_FORMAT(shift_start, '%d.%m.%Y'), '|', hours_worked) ORDER BY shift_start SEPARATOR ';') as raw_days,
                               SUM(hours_worked) as total_hours,
                               SUM(hours_worked * hourly_rate) as total_salary
                               FROM work_log 
                               WHERE DATE_FORMAT(shift_start, '%Y-%m') = ?
                               GROUP BY employee_id");
    $stmt_hours->execute([$selected_month]);
    $raw_data = $stmt_hours->fetchAll();

    foreach ($raw_data as $row) {
        $formatted_days = [];
        if (!empty($row['raw_days'])) {
            $days_array = explode(';', $row['raw_days']);
            foreach ($days_array as $day) {
                list($date, $math_hours) = explode('|', $day);
                $total_min = round($math_hours * 60);
                $h = floor($total_min / 60);
                $m = $total_min % 60;
                $formatted_days[] = $date . ": " . $h . "ч " . ($m > 0 ? $m . "м" : "00м");
            }
        }

        $total_minutes_all = round($row['total_hours'] * 60);
        $total_h_all = floor($total_minutes_all / 60);
        $total_m_all = $total_minutes_all % 60;

        $work_data[] = [
            'employee_id' => $row['employee_id'],
            'employee_name' => $row['employee_name'],
            'current_rate' => $row['current_rate'],
            'days_detail' => implode('<br>', $formatted_days),
            'total_hours_text' => $total_h_all . "ч " . ($total_m_all > 0 ? $total_m_all . "м" : "00м"),
            'total_salary' => $row['total_salary']
        ];

        $rates_archive[$row['employee_id']] = [
            'name' => $row['employee_name'],
            'rate' => $row['current_rate']
        ];
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Учет времени и зарплаты</title>
    <style>
        body { font-family: sans-serif; background: #121212; color: white; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; flex-direction: column; padding: 20px; box-sizing: border-box; }
        .box { background: #1e1e1e; padding: 40px; border-radius: 15px; text-align: center; box-shadow: 0 5px 20px rgba(0,0,0,0.5); width: 300px; }
        input, select { padding: 12px; font-size: 14px; width: 100%; box-sizing: border-box; border-radius: 8px; border: 1px solid #333; background: #2a2a2a; color: white; margin-bottom: 15px; outline: none; }
        input:focus, select:focus { border-color: #007bff; }
        button { padding: 12px; width: 100%; background: #007bff; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: bold; }
        button:hover { background: #0056b3; }
        .error { background: #4a1c1c; color: #f44336; border: 1px solid #f44336; padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 14px; }
        .success { background: #1e4620; color: #4caf50; border: 1px solid #4caf50; padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 14px; text-align: center; }
        .link { margin-top: 15px; display: block; color: #aaa; text-decoration: none; font-size: 14px; }
        .link:hover { color: #fff; }
        
        .secure-content { background: #1e1e1e; padding: 30px; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.5); width: 100%; max-width: 950px; }
        .form-inline { display: grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap: 15px; background: #252525; padding: 20px; border-radius: 10px; margin-bottom: 25px; }
        .form-inline div { display: flex; flex-direction: column; }
        .form-inline label { font-size: 12px; color: #aaa; margin-bottom: 5px; }
        .form-inline input { margin-bottom: 0; }
        .form-inline button { height: 42px; margin-top: auto; background: #28a745; }
        .form-inline button:hover { background: #218838; }

        .filter-panel { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; background: #252525; padding: 15px; border-radius: 10px; }
        .filter-panel form { display: flex; align-items: center; gap: 10px; }
        .filter-panel select { width: 150px; margin-bottom: 0; }
        .btn-excel { background: #1f7246; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 14px; }
        .btn-excel:hover { background: #154c2e; }

        .work-table { width: 100%; border-collapse: collapse; margin-top: 10px; background: #2a2a2a; }
        .work-table th, .work-table td { padding: 12px; border: 1px solid #444; text-align: left; }
        .work-table th { background: #333; color: #ffc107; }
        .total-cell { font-weight: bold; color: #28a745; }
        .money-cell { font-weight: bold; color: #20c997; }
    </style>
</head>
<body>

<?php if (!isset($_SESSION['authorized'])): ?>
    <!-- ЭКРАН ВХОДА -->
    <div class="box">
        <h2>Войти в систему</h2>
        <?php if (!empty($error)): ?><div class="error"><?php echo $error; ?></div><?php endif; ?>
        <form method="POST">
            <input type="text" name="username" placeholder="Ваш логин" required>
            <input type="password" name="password" placeholder="Ваш пароль" required>
            <button type="submit" name="login_user">Войти</button>
        </form>
        <a href="register.php" class="link">Подать заявку на доступ</a>
    </div>
<?php else: ?>
    <!-- ЗАЩИЩЕННЫЙ ИНТЕРФЕЙС -->
    <div class="secure-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2>Учет времени и заработной платы</h2>
            <a href="?logout=1" style="color: #ff4d4d; text-decoration: none; font-weight: bold;">Выйти</a>
        </div>
        
        <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1): ?>
            <div style="background: #2a2a2a; padding: 12px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #ffc107; display: flex; justify-content: space-between; align-items: center;">
                <span style="color: #ffc107; font-weight: bold;">Вы вошли как Администратор.</span> 
                <a href="admin.php" style="color: #fff; font-weight: bold; text-decoration: none;">Панель пользователей →</a>
            </div>

            <!-- ФОРМА ВВОДА СМЕН -->
            <h3>Добавить рабочую смену</h3>
            <?php if (!empty($error)): ?><div class="error"><?php echo $error; ?></div><?php endif; ?>
            <?php if (!empty($success_msg)): ?><div class="success"><?php echo $success_msg; ?></div><?php endif; ?>
            
            <form method="POST" class="form-inline" id="shiftForm">
                <div>
                    <label>Табельный номер</label>
                    <input type="text" name="employee_id" id="empIdInput" placeholder="ТН-001" required>
                </div>
                <div>
                    <label>ФИО сотрудника</label>
                    <input type="text" name="employee_name" id="empNameInput" placeholder="Иванов И.И." required>
                </div>
                <div>
                    <label>Ставка (руб/час)</label>
                    <input type="number" name="hourly_rate" id="empRateInput" placeholder="500.00" step="0.01" min="0" required>
                </div>
                <div>
                    <label>Заступил (Дата/Время)</label>
                    <input type="datetime-local" name="shift_start" required>
                </div>
                <div>
                    <label>Отработано (Часов)</label>
                    <input type="number" name="worked_hours" min="0" max="100" value="0" required>
                </div>
                <div>
                    <label>Отработано (Минут)</label>
                    <input type="number" name="worked_minutes" min="0" max="59" value="0" required>
                </div>
                <button type="submit" name="add_shift">Записать</button>
            </form>
        <?php endif; ?>

        <!-- ПАНЕЛЬ ФИЛЬТРАЦИИ И СКАЧИВАНИЯ -->
        <div class="filter-panel">
            <form method="GET" action="index.php">
                <label style="font-size: 14px; font-weight: bold;">Период отчета:</label>
                <select name="month" onchange="this.form.submit()">
                    <?php foreach ($months_list as $m): ?>
                        <option value="<?php echo $m; ?>" <?php echo ($m === $selected_month) ? 'selected' : ''; ?>>
                            <?php echo date('m.Y', strtotime($m . '-01')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            
            <!-- Ссылка на скрипт генерации Excel с передачей выбранного месяца -->
            <a href="export_excel.php?month=<?php echo $selected_month; ?>" class="btn-excel">📊 Скачать в Excel</a>
        </div>

        <!-- СВОДНЫЙ ТАБЕЛЬ -->
        <table class="work-table">
            <thead>
                <tr>
                    <th>Табельный номер</th>
                    <th>ФИО сотрудника</th>
                    <th>Ставка (руб/час)</th>
                    <th>Календарные дни и часы</th>
                    <th>Итого часов</th>
                    <th>Сумма заработка</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($work_data)): ?>
                    <?php foreach ($work_data as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['employee_id']); ?></td>
                            <td><?php echo htmlspecialchars($row['employee_name']); ?></td>
                            <td><?php echo number_format($row['current_rate'], 2, '.', ' '); ?> руб.</td>
                            <td><?php echo $row['days_detail']; ?></td>
                            <td class="total-cell"><?php echo $row['total_hours_text']; ?></td>
                            <td class="money-cell"><?php echo number_format($row['total_salary'], 2, '.', ' '); ?> руб.</td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align: center; color: #aaa;">За выбранный период данные отсутствуют.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Скрипт автозаполнения -->
    <script>
    const employeeArchive = <?php echo json_encode($rates_archive); ?>;
    const idInput = document.getElementById('empIdInput');
    const nameInput = document.getElementById('empNameInput');
    const rateInput = document.getElementById('empRateInput');

    if (idInput) {
        idInput.addEventListener('input', function() {
            const enteredId = this.value.trim();
            if (employeeArchive[enteredId]) {
                nameInput.value = employeeArchive[enteredId]['name'];
                rateInput.value = employeeArchive[enteredId]['rate'];
            }
        });
    }
    </script>
<?php endif; ?>

</body>
</html>
