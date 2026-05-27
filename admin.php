<?php
session_start();
require_once 'db.php';

// 1. Защита страницы: пускаем ТОЛЬКО Супер-администратора (уровень 2)
if (!isset($_SESSION['authorized']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] < 2) {
    header("Location: index.php");
    exit;
}

$message = isset($_GET['msg']) ? $_GET['msg'] : '';

// 2. ОБРАБОТКА ДЕЙСТВИЙ ЧЕРЕЗ POST (Одобрить, Заблокировать, Сделать админом)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_action'])) {
    $userId = (int)$POST['user_id'];
    $action = $_POST['action_type'];
    
    // Считываем выбранные из выпадающих списков объект и таблицу
    $object = isset($_POST['allowed_object']) ? $_POST['allowed_object'] : '1';
    $sub_table = isset($_POST['allowed_sub_table']) ? $_POST['allowed_sub_table'] : '1';

    if ($action === 'approve') {
        // Одобряем и привязываем к объекту/таблице
        $stmt = $pdo->prepare("UPDATE users SET is_approved = 1, allowed_object = ?, allowed_sub_table = ? WHERE id = ?");
        $stmt->execute([$object, $sub_table, $userId]);
        $message = "Пользователь успешно одобрен на Объект №{$object} (Таблица №{$sub_table})!";
        
    } elseif ($action === 'make_admin') {
        // Назначаем админом, одобряем и привязываем к объекту/таблице
        $stmt = $pdo->prepare("UPDATE users SET is_admin = 1, is_approved = 1, allowed_object = ?, allowed_sub_table = ? WHERE id = ?");
        $stmt->execute([$object, $sub_table, $userId]);
        $message = "Пользователю назначены права Администратора для Объекта №{$object} (Таблица №{$sub_table})!";
        
    } elseif ($action === 'block') {
        // Закрываем доступ пользователю
        $stmt = $pdo->prepare("UPDATE users SET is_approved = 0, is_admin = 0 WHERE id = ?");
        $stmt->execute([$userId]);
        $message = "Доступ пользователю успешно закрыт!";
    }
    
    // Перезагружаем страницу, чтобы применились изменения и вывелось сообщение
    header("Location: admin.php?msg=" . urlencode($message));
    exit;
}

// 3. ТОЧНЫЙ SQL-ЗАПРОС С ВЫБОРКОЙ КОЛОНОК ПРАВ ДОСТУПА
$stmt = $pdo->query("SELECT id, username, is_approved, is_admin, allowed_object, allowed_sub_table FROM users ORDER BY id DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Панель администратора</title>
    <style>
        body { font-family: sans-serif; background: #121212; color: white; padding: 40px; }
        .container { max-width: 950px; margin: 0 auto; background: #1e1e1e; padding: 30px; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.5); }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border: 1px solid #333; text-align: left; vertical-align: middle; }
        th { background: #2a2a2a; }
        .btn { padding: 6px 12px; border-radius: 5px; text-decoration: none; font-size: 14px; font-weight: bold; margin-right: 5px; display: inline-block; border: none; cursor: pointer; }
        .btn-approve { background: #28a745; color: white; }
        .btn-block { background: #dc3545; color: white; }
        .btn-admin { background: #ffc107; color: #121212; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .badge-success { background: #1e4620; color: #4caf50; }
        .badge-danger { background: #4a1c1c; color: #f44336; }
        .alert { background: #007bff; color: white; padding: 10px; border-radius: 5px; margin-bottom: 20px; }
        .select-rights { padding: 6px; font-size: 12px; background: #2a2a2a; color: white; border: 1px solid #444; border-radius: 4px; outline: none; }
    </style>
</head>
<body>

<div class="container">
    <h2>Управление пользователями сайта</h2>
    <a href="index.php" style="color: #aaa; text-decoration: none;">← Вернуться на главную страницу</a>
    
    <?php if (!empty($message)): ?>
        <div class="alert" style="margin-top: 15px;"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Логин</th>
                <th>Статус доступа</th>
                <th>Роль</th>
                <th>Настройка прав и действия</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo $user['id']; ?></td>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td>
                        <?php if ($user['is_approved'] == 1): ?>
                            <span class="badge badge-success">Доступ разрешен</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Ожидает проверки</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php 
                        if ($user['is_admin'] == 2) {
                            echo '👑 Супер-администратор';
                        } elseif ($user['is_admin'] == 1) {
                            echo '⚡ Администратор';
                        } else {
                            echo 'Пользователь';
                        }
                        ?>
                    </td>
                    <td>
                        <!-- Защита от изменения самого себя -->
                        <?php if ($user['username'] !== $_SESSION['username']): ?>
                            
                            <form method="POST" action="admin.php" style="display: inline-flex; align-items: center; gap: 8px; flex-wrap: wrap; margin: 0;">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <input type="hidden" name="action_type" value="">

                                <?php if ($user['is_approved'] == 0 || $user['is_admin'] == 0): ?>
                                    <!-- Блок настройки прав для новых админов -->
                                    <select name="allowed_object" class="select-rights">
                                        <?php for($o = 1; $o <= 4; $o++): ?>
                                            <option value="<?php echo $o; ?>">Объект №<?php echo $o; ?></option>
                                        <?php endfor; ?>
                                    </select>

                                    <select name="allowed_sub_table" class="select-rights">
                                        <option value="1">Таблица №1</option>
                                        <option value="2">Таблица №2</option>
                                    </select>
                                    
                                    <?php if ($user['is_approved'] == 0): ?>
                                        <button type="submit" name="user_action" value="1" onclick="this.form.action_type.value='approve'" class="btn btn-approve">Одобрить</button>
                                    <?php endif; ?>

                                    <?php if ($user['is_admin'] == 0): ?>
                                        <button type="submit" name="user_action" value="1" onclick="this.form.action_type.value='make_admin'" class="btn btn-admin">Сделать Админом</button>
                                    <?php endif; ?>

                                <?php else: ?>
                                    <!-- Отображение прав активных админов с защитой -->
                                    <span style="font-size: 13px; color: #ffc107; background: #2a2a2a; padding: 6px 12px; border-radius: 5px; font-weight: bold; border: 1px solid #444;">
                                        <?php 
                                        $obj = isset($user['allowed_object']) ? $user['allowed_object'] : '1';
                                        $tab = isset($user['allowed_sub_table']) ? $user['allowed_sub_table'] : '1';
                                        
                                        if ($obj === 'all' || $user['is_admin'] == 2): ?>
                                            Доступ: Все объекты
                                        <?php else: ?>
                                            Доступ: Об. <?php echo htmlspecialchars($obj); ?> — Таб. <?php echo htmlspecialchars($tab); ?>
                                        <?php endif; ?>
                                    </span>
                                    
                                    <button type="submit" name="user_action" value="1" onclick="this.form.action_type.value='block'" class="btn btn-block">Закрыть доступ</button>
                                <?php endif; ?>
                            </form>

                        <?php else: ?>
                            <span style="color: #666; font-style: italic;">Это ваш аккаунт</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>
