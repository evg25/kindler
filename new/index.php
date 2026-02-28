<?php

$ADMIN_EMAIL = "evgeny.neymer@gmail.com";


$success = false;
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!empty($_POST["company"])) {
        exit;
    }

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $message = trim($_POST["message"] ?? "");

    if ($name === "" || $email === "" || $message === "") {
        $error = "Все поля обязательны для заполнения.";
    }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Пожалуйста, введите корректный email адрес.";
    }
    else {
        $safe_email = filter_var($email, FILTER_SANITIZE_EMAIL);
        $subject = "Запрос доступа: " . $name;

        $body = "Имя: " . $name . "\n";
        $body .= "Email: " . $safe_email . "\n\n";
        $body .= "Сообщение:\n" . $message . "\n";

        $headers = "From: " . $safe_email . "\r\n";
        $headers .= "Reply-To: " . $safe_email . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        if (@mail($ADMIN_EMAIL, $subject, $body, $headers)) {
            $success = true;
        }
        else {
            $error = "Ошибка при отправке сообщения. Пожалуйста, попробуйте позже.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Запрос доступа — Наум Киндлер</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <style>
        .form-container {
            max-width: 400px;
            margin: 2rem auto;
            text-align: left;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-color);
        }
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid var(--border-color);
            border-radius: 3px;
            font-family: inherit;
            font-size: 1rem;
            box-sizing: border-box;
            background-color: #fff;
        }
        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }
        .button {
            cursor: pointer;
            width: 100%;
            font-size: 1rem;
            font-family: inherit;
            display: block;
            text-align: center;
        }
        .msg-success {
            color: #4a3424;
            font-style: italic;
            margin: 2rem 0;
            text-align: center;
        }
        .msg-error {
            color: #d9534f;
            margin-bottom: 1.5rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <main class="access-page">
        <h1>Приватный архив</h1>
        
        <?php if ($success): ?>
            <p class="msg-success">Спасибо. Ваш запрос отправлен.</p>
            <p><a href="/">Вернуться на главную</a></p>
        <?php
else: ?>
            <p>Литературный архив Наума Киндлера является частным.</p>
            <p>Для получения доступа к материалам, пожалуйста, заполните форму ниже.</p>
            
            <?php if ($error): ?>
                <p class="msg-error"><?php echo htmlspecialchars($error); ?></p>
            <?php
    endif; ?>

            <div class="form-container">
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                    <input type="text" name="company" style="display:none" tabindex="-1" autocomplete="off">
                    
                    <div class="form-group">
                        <label for="name">Имя (обязательно)</label>
                        <input type="text" id="name" name="name" required value="<?php echo isset($_POST['name']) && !$success ? htmlspecialchars($_POST['name']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email (обязательно)</label>
                        <input type="email" id="email" name="email" required value="<?php echo isset($_POST['email']) && !$success ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="message">Сообщение (обязательно)</label>
                        <textarea id="message" name="message" required><?php echo isset($_POST['message']) && !$success ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                    </div>
                    
                    <button type="submit" class="button">Отправить запрос</button>
                </form>
            </div>
        <?php
endif; ?>
    </main>
</body>
</html>
