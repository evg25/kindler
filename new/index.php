<?php

// Load centralized logger
require_once __DIR__ . '/../logger.php';

$ADMIN_EMAIL = "poetry@kindler.cz";
$FROM_EMAIL = "poetry@kindler.cz";
$LOG_FILE = __DIR__ . "/mail_errors.log";
$MAX_MESSAGE_LENGTH = 2000;

$success = false;
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Log: Form submission started
    $log_entry = sprintf(
        "[%s] Form submission started | IP: %s\n",
        date('c'),
        $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    );
    @file_put_contents($LOG_FILE, $log_entry, FILE_APPEND | LOCK_EX);
    
    // Honeypot spam check
    if (!empty($_POST["company"])) {
        exit;
    }

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $message = trim($_POST["message"] ?? "");

    // Validate required fields
    if ($name === "" || $email === "" || $message === "") {
        $error = "Все поля обязательны для заполнения.";
        log_error('new/index.php', 'VALIDATION_ERROR', 'Required fields missing', ['name' => !empty($name), 'email' => !empty($email), 'message' => !empty($message)]);
    }
    // Validate email format
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Пожалуйста, введите корректный email адрес.";
        log_error('new/index.php', 'VALIDATION_ERROR', 'Invalid email format', ['email' => $email]);
    }
    // Security: prevent header injection
    elseif (preg_match("/[\r\n]/", $email)) {
        $error = "Недопустимый формат email адреса.";
        log_error('new/index.php', 'SECURITY_ERROR', 'Email header injection attempt detected', ['email' => $email]);
    }
    // Limit message length
    elseif (mb_strlen($message) > $MAX_MESSAGE_LENGTH) {
        $error = "Сообщение слишком длинное. Максимум " . $MAX_MESSAGE_LENGTH . " символов.";
        log_error('new/index.php', 'VALIDATION_ERROR', 'Message too long', ['length' => mb_strlen($message), 'max' => $MAX_MESSAGE_LENGTH]);
    }
    else {
        $safe_email = filter_var($email, FILTER_SANITIZE_EMAIL);
        $subject = "Запрос доступа: " . $name;

        $body = "Имя: " . $name . "\n";
        $body .= "Email: " . $safe_email . "\n\n";
        $body .= "Сообщение:\n" . $message . "\n";

        // Compliant headers for SPF/DKIM
        $headers = "From: " . $FROM_EMAIL . "\r\n";
        $headers .= "Reply-To: " . $safe_email . "\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        // Log: Before sending email
        $log_entry = sprintf(
            "[%s] Attempting to send email | To: %s | From: %s | Reply-To: %s | Subject: %s\n",
            date('c'),
            $ADMIN_EMAIL,
            $FROM_EMAIL,
            $safe_email,
            $subject
        );
        @file_put_contents($LOG_FILE, $log_entry, FILE_APPEND | LOCK_EX);

        // Send email without error suppression
        $mail_sent = mail($ADMIN_EMAIL, $subject, $body, $headers);

        // Log: Immediately after mail() call
        $log_entry = sprintf(
            "[%s] mail() returned: %s\n",
            date('c'),
            $mail_sent ? 'TRUE (success)' : 'FALSE (failed)'
        );
        @file_put_contents($LOG_FILE, $log_entry, FILE_APPEND | LOCK_EX);

        if ($mail_sent) {
            $success = true;
        }
        else {
            // Capture error details
            $last_error = error_get_last();
            $error_msg = $last_error ? $last_error['message'] : 'No error details';
            $remote_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            
            // Log failure for debugging
            $log_entry = sprintf(
                "[%s] MAIL FAILED | IP: %s | Email: %s | Error: %s\n",
                date('c'),
                $remote_ip,
                $safe_email,
                $error_msg
            );
            @file_put_contents($LOG_FILE, $log_entry, FILE_APPEND | LOCK_EX);
            
            // Centralized error log
            log_error('new/index.php', 'MAIL_SEND_FAILED', 'mail() returned false', [
                'to' => $ADMIN_EMAIL,
                'from' => $FROM_EMAIL,
                'reply_to' => $safe_email,
                'name' => $name,
                'error' => $error_msg
            ]);
            
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
                <form method="POST" action="/new/">
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
