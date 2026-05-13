<?php
session_start();
// Подключаем наш защищенный мостик к базе данных
require_once 'db.php';

$error = '';

// Проверяем, отправлена ли форма входа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_user'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (!empty($username) && !empty($password)) {
        // Ищем пользователя в базе данных по его логину
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // Если пользователь найден
        if ($user) {
            // Проверяем, совпадает ли введенный пароль с зашифрованным в базе
            if (password_verify($password, $user['password'])) {
                
                // ПРОВЕРКА ДОПУСКА: Одобрил ли администратор (is_approved == 1)?
                if ($user['is_approved'] == 1) {
                    $_SESSION['authorized'] = true;
                    $_SESSION['username'] = $user['username'];
                } else {
                    $error = "Ваша заявка еще на рассмотрении у администратора.";
                }
                
            } else {
                $error = "Неверный логин или пароль.";
            }
        } else {
            $error = "Неверный логин или пароль.";
        }
    } else {
        $error = "Пожалуйста, заполните все поля.";
    }
}

// Выход из системы
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
    <title>Авторизация</title>
    <style>
        /* Стили для экрана входа */
        body { font-family: sans-serif; background: #121212; color: white; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; flex-direction: column; }
        .box { background: #1e1e1e; padding: 40px; border-radius: 15px; text-align: center; box-shadow: 0 5px 20px rgba(0,0,0,0.5); width: 300px; }
        input { padding: 12px; font-size: 16px; width: 100%; box-sizing: border-box; border-radius: 8px; border: 1px solid #333; background: #2a2a2a; color: white; margin-bottom: 15px; outline: none; }
        input:focus { border-color: #007bff; }
        button { padding: 12px; width: 100%; background: #007bff; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: bold; }
        button:hover { background: #0056b3; }
        .error { background: #4a1c1c; color: #f44336; border: 1px solid #f44336; padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 14px; }
        .link { margin-top: 15px; display: block; color: #aaa; text-decoration: none; font-size: 14px; }
        .link:hover { color: #fff; }
        
        /* Стили для контента (вставляемой таблицы) после авторизации */
        .secure-content { background: #1e1e1e; padding: 30px; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.5); text-align: left; width: auto; max-width: 90%; }
    </style>
</head>
<body>

<?php if (!isset($_SESSION['authorized'])): ?>
    <!-- ЭКРАН ВХОДА -->
    <div class="box">
        <h2>Войти на сайт</h2>
        
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="text" name="username" placeholder="Ваш логин" required>
            <input type="password" name="password" placeholder="Ваш пароль" required>
            <button type="submit" name="login_user">Войти</button>
        </form>
        
        <a href="register.php" class="link">Подать заявку на доступ</a>
    </div>
<?php else: ?>
    <!-- СЕКРЕТНЫЙ РАЗДЕЛ (виден только после одобрения админом) -->
    <div class="secure-content">
        <h2>Добро пожаловать, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
        
        <!-- ======================================================= -->
        <!-- ВЕРСТКА (ТАБЛИЦУ С ОТОБРАЖЕНИЕМ СТАТУСОВ) СЮДА -->
        <p>Здесь будет отображаться интерактивная таблица Excel.</p>
        <!-- ======================================================= -->

        <br><br>
        <a href="?logout=1" style="color: #ff4d4d; text-decoration: none; font-weight: bold;">Выйти из системы</a>
    </div>
<?php endif; ?>

</body>
</html>
