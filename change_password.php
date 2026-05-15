<?php
session_start();
require_once 'db.php';

// Если пользователь не вошел на сайт, выгоняем его
if (!isset($_SESSION['authorized'])) {
    header("Location: index.php");
    exit;
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old_password = $_POST['old_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $username = $_SESSION['username'];

    if (!empty($old_password) && !empty($new_password) && !empty($confirm_password)) {
        if ($new_password === $confirm_password) {
            // 1. Запрашиваем текущий пароль пользователя из базы
            $stmt = $pdo->prepare("SELECT password FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            // 2. Проверяем, правильный ли старый пароль ввел пользователь
            if ($user && password_verify($old_password, $user['password'])) {
                
                // 3. Хешируем новый пароль и сохраняем его в базу
                $new_hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = ?");
                $update_stmt->execute([$new_hashed, $username]);

                $message = "Пароль успешно изменен!";
                $message_type = "success";
            } else {
                $message = "Неверный текущий пароль.";
                $message_type = "error";
            }
        } else {
            $message = "Новые пароли не совпадают.";
            $message_type = "error";
        }
    } else {
        $message = "Пожалуйста, заполните все поля.";
        $message_type = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Смена пароля</title>
    <style>
        body { font-family: sans-serif; background: #121212; color: white; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .box { background: #1e1e1e; padding: 40px; border-radius: 15px; text-align: center; box-shadow: 0 5px 20px rgba(0,0,0,0.5); width: 300px; }
        input { padding: 12px; font-size: 16px; width: 100%; box-sizing: border-box; border-radius: 8px; border: 1px solid #333; background: #2a2a2a; color: white; margin-bottom: 15px; outline: none; }
        input:focus { border-color: #007bff; }
        button { padding: 12px; width: 100%; background: #28a745; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: bold; }
        button:hover { background: #218838; }
        .msg { padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 14px; }
        .success { background: #1e4620; color: #4caf50; border: 1px solid #4caf50; }
        .error { background: #4a1c1c; color: #f44336; border: 1px solid #f44336; }
        .link { margin-top: 15px; display: block; color: #aaa; text-decoration: none; font-size: 14px; }
        .link:hover { color: #fff; }
    </style>
</head>
<body>

<div class="box">
    <h2>Смена пароля</h2>
    <p style="color: #aaa; font-size: 14px;">Для аккаунта: <b><?php echo htmlspecialchars($_SESSION['username']); ?></b></p>
    
    <?php if (!empty($message)): ?>
        <div class="msg <?php echo $message_type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="password" name="old_password" placeholder="Текущий пароль" required>
        <input type="password" name="new_password" placeholder="Новый пароль" required>
        <input type="password" name="confirm_password" placeholder="Повторите новый пароль" required>
        <button type="submit">Обновить пароль</button>
    </form>
    
    <a href="index.php" class="link">← На главную</a>
</div>

</body>
</html>
