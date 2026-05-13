<?php
session_start();
require_once 'db.php';

// Защита страницы: если пользователь не админ, перенаправляем его на главную
if (!isset($_SESSION['authorized']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: index.php");
    exit;
}

$message = '';

// Обработка действий (Одобрить, Закрыть доступ, Назначить админом)
if (isset($_GET['action']) && isset($_GET['user_id'])) {
    $userId = (int)$_GET['user_id'];
    $action = $_GET['action'];

    if ($action === 'approve') {
        $stmt = $pdo->prepare("UPDATE users SET is_approved = 1 WHERE id = ?");
        $stmt->execute([$userId]);
        $message = "Пользователь успешно одобрен!";
    } elseif ($action === 'block') {
        $stmt = $pdo->prepare("UPDATE users SET is_approved = 0, is_admin = 0 WHERE id = ?");
        $stmt->execute([$userId]);
        $message = "Доступ пользователю успешно закрыт!";
    } elseif ($action === 'make_admin') {
        $stmt = $pdo->prepare("UPDATE users SET is_admin = 1, is_approved = 1 WHERE id = ?");
        $stmt->execute([$userId]);
        $message = "Пользователю назначены права Администратора!";
    }
    
    // Перезагружаем страницу, чтобы убрать параметры из адресной строки и показать сообщение
    header("Location: admin.php?msg=" . urlencode($message));
    exit;
}

// Получаем список всех зарегистрированных пользователей
$stmt = $pdo->query("SELECT id, username, is_approved, is_admin FROM users ORDER BY id DESC");
$users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Панель администратора</title>
    <style>
        body { font-family: sans-serif; background: #121212; color: white; padding: 40px; }
        .container { max-width: 900px; margin: 0 auto; background: #1e1e1e; padding: 30px; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.5); }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border: 1px solid #333; text-align: left; }
        th { background: #2a2a2a; }
        .btn { padding: 6px 12px; border-radius: 5px; text-decoration: none; font-size: 14px; font-weight: bold; margin-right: 5px; display: inline-block; }
        .btn-approve { background: #28a745; color: white; }
        .btn-block { background: #dc3545; color: white; }
        .btn-admin { background: #ffc107; color: #121212; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .badge-success { background: #1e4620; color: #4caf50; }
        .badge-danger { background: #4a1c1c; color: #f44336; }
        .alert { background: #007bff; color: white; padding: 10px; border-radius: 5px; margin-bottom: 20px; }
    </style>
</head>
<body>

<div class="container">
    <h2>Управление пользователями сайта</h2>
    <a href="index.php" style="color: #aaa; text-decoration: none;">← Вернуться на главную страницу</a>
    
    <?php if (isset($_GET['msg'])): ?>
        <div class="alert" style="margin-top: 15px;"><?php echo htmlspecialchars($_GET['msg']); ?></div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Логин</th>
                <th>Статус доступа</th>
                <th>Роль</th>
                <th>Действия</th>
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
                        <?php echo $user['is_admin'] == 1 ? '⚡ Администратор' : 'Пользователь'; ?>
                    </td>
                    <td>
                        <!-- Защита от случайного удаления самого себя из админов -->
                        <?php if ($user['username'] !== $_SESSION['username']): ?>
                            
                            <?php if ($user['is_approved'] == 0): ?>
                                <a href="?action=approve&user_id=<?php echo $user['id']; ?>" class="btn btn-approve">Одобрить</a>
                            <?php else: ?>
                                <a href="?action=block&user_id=<?php echo $user['id']; ?>" class="btn btn-block">Закрыть доступ</a>
                            <?php endif; ?>

                            <?php if ($user['is_admin'] == 0): ?>
                                <a href="?action=make_admin&user_id=<?php echo $user['id']; ?>" class="btn btn-admin">Сделать Админом</a>
                            <?php endif; ?>

                        <?php else: ?>
                            <span style="color: #666;">Это my аккаунт</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>
