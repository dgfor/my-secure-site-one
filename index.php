<?php
session_start();
$correct_pin = "7777"; // ВАШ СЕКРЕТНЫЙ КОД

// Проверка: нажата ли кнопка входа
if (isset($_POST['pin'])) {
    if ($_POST['pin'] === $correct_pin) {
        $_SESSION['authorized'] = true;
    } else {
        $error = "Неверный PIN-код!";
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
    <title>Мой защищенный сайт</title>
    <style>
        body { font-family: sans-serif; background: #121212; color: white; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .box { background: #1e1e1e; padding: 30px; border-radius: 15px; text-align: center; box-shadow: 0 5px 20px rgba(0,0,0,0.5); }
        input { padding: 10px; font-size: 20px; width: 100px; text-align: center; border-radius: 5px; border: none; }
        button { padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px; }
        .error { color: #ff4d4d; margin-top: 10px; }
    </style>
</head>
<body>

<?php if (!isset($_SESSION['authorized'])): ?>
    <!-- ЭКРАН ВХОДА -->
    <div class="box">
        <h2>Введите доступ</h2>
        <form method="POST">
            <input type="password" name="pin" maxlength="4" placeholder="****" required>
            <button type="submit">Войти</button>
        </form>
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
    </div>
<?php else: ?>
    <!-- КОНТЕНТ САЙТА (виден только после ввода PIN) -->
    <div class="box">
        <h1>Секретный раздел сайта</h1>
        <p>Этот текст виден только вам.</p>
        <a href="?logout=1" style="color: #aaa;">Выйти</a>
    </div>
    
<?php endif; ?>

</body>
</html>
