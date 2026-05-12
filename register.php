<?php
// Подключаем наш рабочий мостик к базе данных
require_once 'db.php';

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (!empty($username) && !empty($password)) {
        // Хешируем (шифруем) пароль. В базу нельзя сохранять чистый текст!
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        try {
            // Подготавливаем безопасный SQL-запрос (защита от SQL-инъекций)
            $stmt = $pdo->prepare("INSERT INTO users (username, password, is_approved) VALUES (?, ?, 0)");
            $stmt->execute([$username, $hashed_password]);
            
            $message = "Заявка успешно отправлена! Ожидайте одобрения администратором.";
            $message_type = "success";
        } catch (\PDOException $e) {
            // Ошибка возникнет, если такой логин уже есть (из-за правила UNIQUE в базе)
            if ($e->getCode() == 23000) {
                $message = "Пользователь с таким логином уже существует.";
                $message_type = "error";
            } else {
                $message = "Ошибка базы данных: " . $e->getMessage();
                $message_type = "error";
            }
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
    <title>Регистрация на сайте</title>
    <style>
        body { font-family: sans-serif; background: #121212; color: white; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .box { background: #1e1e1e; padding: 40px; border-radius: 15px; text-align: center; box-shadow: 0 5px 20px rgba(0,0,0,0.5); width: 300px; }
        input { padding: 12px; font-size: 16px; width: 100%; box-sizing: border-box; border-radius: 8px; border: 1px solid #333; background: #2a2a2a; color: white; margin-bottom: 15px; outline: none; }
        input:focus { border-color: #007bff; }
        button { padding: 12px; width: 100%; background: #007bff; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: bold; }
        button:hover { background: #0056b3; }
        .msg { padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 14px; }
        .success { background: #1e4620; color: #4caf50; border: 1px solid #4caf50; }
        .error { background: #4a1c1c; color: #f44336; border: 1px solid #f44336; }
        .link { margin-top: 15px; display: block; color: #aaa; text-decoration: none; font-size: 14px; }
        .link:hover { color: #fff; }
    </style>
</head>
<body>

<div class="box">
    <h2>Подать заявку</h2>
    
    <?php if (!empty($message)): ?>
        <div class="msg <?php echo $message_type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="text" name="username" placeholder="Придумайте логин" required>
        <input type="password" name="password" placeholder="Придумайте пароль" required>
        <button type="submit">Зарегистрироваться</button>
    </form>
    
    <a href="index.php" class="link">Назад ко входу</a>
</div>

</body>
</html>
