<?php
session_start();
require_once 'db.php';

$error = '';
$success_msg = '';

// 1. ЛОГИКА АВТОРИЗАЦИИ (Вход из таблицы testLogin)
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
                
                // Сохраняем права на объекты и подтаблицы
                $_SESSION['allowed_object'] = !empty($user['allowed_object']) ? $user['allowed_object'] : '1';
                $_SESSION['allowed_sub_table'] = !empty($user['allowed_sub_table']) ? $user['allowed_sub_table'] : '1';
                
                header("Location: index.php");
                exit;
            } else { $error = "Ваша заявка еще на рассмотрении."; }
        } else { $error = "Неверный логин или пароль."; }
    } else { $error = "Заполните все поля."; }
}

// ЛОГИКА ВЫХОДА
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

// 2. БЕЗОПАСНОЕ ОПРЕДЕЛЕНИЕ ТЕКУЩЕГО ОБЪЕКТА И ТАБЛИЦЫ
$currentObject = '1';
$currentSubTable = '1';

if (isset($_SESSION['authorized'])) {
    if ($_SESSION['is_admin'] == 2) {
        // Супер-администратор переключает кликами
        $currentObject = isset($_GET['object']) ? $_GET['object'] : '1';
        if (!in_array($currentObject, ['1', '2', '3', '4'])) { $currentObject = '1'; }

        $currentSubTable = isset($_GET['table']) ? $_GET['table'] : '1';
        if (!in_array($currentSubTable, ['1', '2'])) { $currentSubTable = '1'; }
    } else {
        // Обычный админ привязан к своим значениям из БД
        $currentObject = isset($_SESSION['allowed_object']) ? $_SESSION['allowed_object'] : '1';
        $currentSubTable = isset($_SESSION['allowed_sub_table']) ? $_SESSION['allowed_sub_table'] : '1';
    }
}

// Имя таблицы в базе данных: work_log_1_1, work_log_3_2 и т.д.
$db_table = "work_log_" . $currentObject . "_" . $currentSubTable;


// 3. ЛОГИКА ДОБАВЛЕНИЯ СМЕНЫ
if (isset($_SESSION['authorized']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_shift'])) {
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
            $stmt = $pdo->prepare("INSERT INTO {$db_table} (employee_id, employee_name, shift_start, shift_end, hours_worked, hourly_rate) 
                                   VALUES (?, ?, ?, ?, ?, ?) 
                                   ON DUPLICATE KEY UPDATE 
                                   shift_start = VALUES(shift_start),
                                   shift_end = VALUES(shift_end),
                                   hours_worked = VALUES(hours_worked),
                                   hourly_rate = VALUES(hourly_rate)");
            $stmt->execute([$emp_id, $emp_name, $start_str, $end_str, $hours_coef, $rate]);
            $success_msg = "Данные успешно сохранены в объекте №{$currentObject} (Таблица №{$currentSubTable})!";
        } catch (Exception $e) {
            $error = "Ошибка записи: " . $e->getMessage();
        }
    } else { $error = "Заполните все поля формы корректно."; }
}

// 4. ОПРЕДЕЛЕНИЕ ТЕКУЩЕГО ФИЛЬТРА МЕСЯЦА
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// 5. СБОР ДАННЫХ ДЛЯ ТАБЛИЦЫ
$months_list = [];
$work_data = [];
$rates_archive = [];

if (isset($_SESSION['authorized'])) {
    $stmt_months = $pdo->query("SELECT DISTINCT DATE_FORMAT(shift_start, '%Y-%m') as m_date FROM {$db_table} ORDER BY m_date DESC");
    $months_list = $stmt_months->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($months_list)) { $months_list[] = date('Y-m'); }

    $stmt_hours = $pdo->prepare("SELECT employee_id, employee_name, MAX(hourly_rate) as current_rate,
                               GROUP_CONCAT(CONCAT(DATE_FORMAT(shift_start, '%d.%m.%Y'), '|', hours_worked) ORDER BY shift_start SEPARATOR ';') as raw_days,
                               SUM(hours_worked) as total_hours,
                               SUM(hours_worked * hourly_rate) as total_salary
                               FROM {$db_table} 
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
    }
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
        .work-table th, .work-table td { border: 1px solid #444; padding: 12px; text-align: center; }
        .work-table th { background: #333; color: #007bff; }
        .work-table tr:nth-child(even) { background: #222; }
    </style>
</head>
<body>

<?php if (!isset($_SESSION['authorized'])): ?>
    <!-- ФОРМА ВХОДА ДЛЯ НЕАВТОРИЗОВАННЫХ -->
    <div class="box">
        <h2 style="margin-top:0; margin-bottom:20px;">Вход в систему</h2>
        <?php if(!empty($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST" action="index.php">
            <input type="text" name="username" placeholder="Логин" required>
				<input type="password" name="password" placeholder="Пароль" required>
            <button type="submit" name="login_user">Войти</button>
        </form>
		  <a href="register.php" class="link">Зарегистрироваться</a>
    </div>
<?php else: ?>
    <!-- ПАНЕЛЬ УПРАВЛЕНИЯ ДЛЯ АВТОРИЗОВАННЫХ -->
    <div class="secure-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2>Учет времени (Объект №<?php echo $currentObject; ?>, Таблица №<?php echo $currentSubTable; ?>)</h2>
            <a href="?logout=1" style="color: #ff4d4d; text-decoration: none; font-weight: bold;">Выйти</a>
        </div>
        
        <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] >= 1): ?>
           <div style="background: #2a2a2a; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #ffc107; display: flex; flex-direction: column; gap: 15px;">
                
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: #ffc107; font-weight: bold;">
                        Вы вошли как <?php echo $_SESSION['is_admin'] == 2 ? 'Супер-администратор' : 'Администратор'; ?>.
                    </span> 
                    <?php if ($_SESSION['is_admin'] == 2): ?>
                        <a href="admin.php" style="color: #ffc107; font-weight: bold; text-decoration: none;">Панель пользователей →</a>
                    <?php endif; ?>
                </div>

                <!-- ДВУХУРОВНЕВОЕ МЕНЮ ДЛЯ СУПЕР-АДМИНИСТРАТОРА -->
                <?php if ($_SESSION['is_admin'] == 2): ?>
                    <div style="border-top: 1px solid #444; padding-top: 12px; display: flex; flex-direction: column; gap: 10px;">
                        
                        <!-- 1 РЯД: Выбор объекта -->
                        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                            <span style="font-size: 13px; color: #aaa; width: 110px;">Выбор объекта:</span>
                            <?php for($o = 1; $o <= 4; $o++): ?>
                                <a href="index.php?object=<?php echo $o; ?>&table=<?php echo $currentSubTable; ?>&month=<?php echo $selected_month; ?>" 
                                   style="padding: 5px 10px; border-radius: 4px; text-decoration: none; font-weight: bold; font-size: 12px;
                                          background: <?php echo $currentObject == $o ? '#ffc107' : '#1e1e1e'; ?>; 
                                          color: <?php echo $currentObject == $o ? '#121212' : '#ffc107'; ?>;
                                          border: 1px solid #ffc107;">
                                   Объект №<?php echo $o; ?>
                                </a>
                            <?php endfor; ?>
                        </div>

                        <!-- 2 РЯД: Выбор таблицы -->
                        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                            <span style="font-size: 13px; color: #aaa; width: 110px;">Таблицы объекта:</span>
                            <?php for($t = 1; $t <= 2; $t++): ?>
                                <a href="index.php?object=<?php echo $currentObject; ?>&table=<?php echo $t; ?>&month=<?php echo $selected_month; ?>" 
                                   style="padding: 5px 10px; border-radius: 4px; text-decoration: none; font-weight: bold; font-size: 12px;
                                          background: <?php echo $currentSubTable == $t ? '#007bff' : '#1e1e1e'; ?>; 
                                          color: <?php echo $currentSubTable == $t ? 'white' : '#007bff'; ?>;
                                          border: 1px solid #007bff;">
                                   Таблица №<?php echo $t; ?>
                                </a>
                            <?php endfor; ?>
                        </div>

                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if(!empty($success_msg)): ?>
            <div class="success"><?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if(!empty($error) && isset($_POST['add_shift'])): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- ФОРМА ДОБАВЛЕНИЯ СМЕНЫ -->
        <form action="index.php?object=<?php echo $currentObject; ?>&table=<?php echo $currentSubTable; ?>" method="POST" class="form-inline">
            <div>
                <label>ID сотрудника</label>
                <input type="text" name="employee_id" required>
            </div>
            <div>
                <label>ФИО сотрудника</label>
                <input type="text" name="employee_name" required>
            </div>
            <div>
                <label>Ставка (руб/час)</label>
                <input type="number" step="0.01" name="hourly_rate" required>
            </div>
            <div>
                <label>Начало смены</label>
                <input type="datetime-local" name="shift_start" required>
            </div>
            <div>
                <label>Отработано часов</label>
                <input type="number" name="worked_hours" min="0" value="0" required>
            </div>
            <div>
                <label>минут</label>
                <input type="number" name="worked_minutes" min="0" max="59" value="0" required>
            </div>
            <button type="submit" name="add_shift">Сохранить</button>
        </form>

        <!-- ПАНЕЛЬ ФИЛЬТРАЦИИ МЕСЯЦЕВ -->
        <div class="filter-panel">
            <form method="GET" action="index.php">
                <input type="hidden" name="object" value="<?php echo $currentObject; ?>">
                <input type="hidden" name="table" value="<?php echo $currentSubTable; ?>">
                <label style="margin-right: 10px;">Период:</label>
                <select name="month" onchange="this.form.submit()">
                    <?php foreach ($months_list as $m): ?>
                        <option value="<?php echo $m; ?>" <?php echo $m === $selected_month ? 'selected' : ''; ?>>
                            <?php echo date('m.Y', strtotime($m . '-01')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <!-- СВОДНАЯ ТАБЛИЦА -->
        <table class="work-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Сотрудник</th>
                    <th>Ставка</th>
                    <th>Детализация по дням</th>
                    <th>Всего часов</th>
                    <th>Зарплата</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($work_data)): ?>
                    <tr><td colspan="6">Нет данных за выбранный период</td></tr>
                <?php else: ?>
                    <?php foreach($work_data as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['employee_id']); ?></td>
                            <td><?php echo htmlspecialchars($row['employee_name']); ?></td>
                            <td><?php echo number_format($row['current_rate'], 2, '.', ' '); ?> руб.</td>
                            <td><?php echo $row['days_detail']; ?></td>
                            <td><?php echo $row['total_hours_text']; ?></td>
                            <td style="font-weight:bold; color:#28a745;"><?php echo number_format($row['total_salary'], 2, '.', ' '); ?> руб.</td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

</body>
</html>