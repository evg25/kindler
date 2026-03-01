<?php
/**
 * Home Page with Login Form
 * Shows welcome text and login form for non-authenticated users
 */

require_once __DIR__ . '/../auth.php';

$is_logged_in = auth_is_logged_in();
$username = $is_logged_in ? auth_get_username() : null;

// Get next URL from query parameter
$next_url = $_GET['next'] ?? '/home/';
// Validate it's a safe internal URL
if (!empty($next_url) && strpos($next_url, '/app/') === 0) {
    $redirect_after_login = $next_url;
} else {
    $redirect_after_login = '/home/';
}

// Handle login POST
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $input_username = $_POST['username'] ?? '';
    $input_password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    $next_from_form = $_POST['next'] ?? '/home/';
    
    // Verify CSRF token
    if (!auth_verify_csrf_token($csrf_token)) {
        $error = 'Неверный токен запроса';
    } else {
        // Check if account is locked out
        if (auth_is_locked_out($input_username)) {
            $lockout_time = auth_get_lockout_time($input_username);
            $minutes = ceil($lockout_time / 60);
            $error = "Слишком много неудачных попыток. Попробуйте через {$minutes} мин.";
        } else {
            // Verify credentials
            if (auth_verify_credentials($input_username, $input_password)) {
                // Clear failed attempts
                auth_clear_failed_attempts($input_username);
                
                // Login user
                auth_login($input_username);
                
                // Redirect to next URL or home
                header('Location: ' . $next_from_form);
                exit;
            } else {
                // Record failed attempt
                auth_record_failed_attempt($input_username);
                $error = 'Неверное имя пользователя или пароль';
            }
        }
    }
}

$csrf_token = auth_get_csrf_token();
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Наум Киндлер — Литературный архив</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <script defer src="/assets/js/app.js"></script>
    <style>
        .login-box {
            max-width: 400px;
            margin: 3rem auto;
            padding: 2rem;
            background: #f8f9fa;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .login-box h2 {
            margin-top: 0;
            margin-bottom: 1.5rem;
            text-align: center;
            color: #333;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }
        .form-group input:focus {
            outline: none;
            border-color: #7d695a;
        }
        .btn-login {
            width: 100%;
            padding: 0.75rem;
            background: #7d695a;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            font-weight: 500;
        }
        .btn-login:hover {
            background: #6a5a4d;
        }
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 0.75rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            border: 1px solid #f5c6cb;
        }
        .user-info {
            text-align: center;
            padding: 1rem;
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 4px;
            margin: 2rem auto;
            max-width: 600px;
        }
        .user-info strong {
            color: #155724;
        }
    </style>
</head>

<body>
    <?php if ($is_logged_in): ?>
    <header>
        <nav>
            <a href="/home/">Главная</a> |
            <a href="/poems/">Стихи</a> |
            <a href="/photos/">Фото</a> |
            <a href="/book/">Книга</a> |
            <a href="/bio/">Биография</a> |
            <a href="/logout.php">Выйти</a>
        </nav>
    </header>
    <?php endif; ?>
    <main>
        <h1>Наум Киндлер</h1>

        <div style="text-align: center; margin-bottom: 2rem;">
            <img src="/assets/images/author.jpg" alt="Фотография Наума Киндлера"
                style="max-width: 50%; height: auto; border-radius: 4px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
        </div>

        <div style="max-width: 800px; margin: 0 auto; text-align: left; line-height: 1.8;">
            <p><strong>Дорогой читатель!</strong></p>

            <p>Мы очень рады, что Вас заинтересовала эта страничка в интернете и
                стихи нашего дорогого папы Наума Киндлера.</p>

            <p>Мы с удовольствием предоставим Вам возможность прочесть книгу
                его избранной лирики «Сам для себя и для других, о себе и о них», которая
                вышла из печати в 2025 г. в Москве в издательстве ООО «Сам
                Полиграфист», также как и другие стихи, не вошедшие в эту книгу, и
                ответим на Ваши вопросы.</p>

            <p>Время летит стремительно, и, к сожалению, осталось не так много
                людей, с которыми Наум Киндлер вместе работал, дружил, которым он
                посвящал свои стихотворения.</p>

            <p>Возможно, Вы или Ваши близкие были знакомы с Наумом Иосифовичем,
                и мы будем признательны за любую информацию или комментарии к
                стихам.</p>

            <?php if (!$is_logged_in): ?>
            <p>Для того, чтобы получить возможность открыть книгу и другие
                странички этого сайта, войдите используя логин и пароль, который мы Вам предоставили.</p>
            <?php endif; ?>

            <p style="margin-top: 2rem;"><em>С уважением,<br>
                    Елена и Михаил, дочь и сын Н. И. Киндлера</em></p>
        </div>

        <?php if ($is_logged_in): ?>
            <!-- Logged in state -->
            <div class="user-info">
                <strong>Вы вошли в систему</strong>
                <p style="margin: 0.5rem 0;">Добро пожаловать, <?php echo htmlspecialchars($username); ?>!</p>
            </div>
            
            <div style="text-align: center; margin-top: 2rem;">
                <a href="/poems/" class="button">Стихи</a>
                <a href="/photos/" class="button" style="margin-left: 1rem;">Фото</a>
                <a href="/book/" class="button" style="margin-left: 1rem;">Книга</a>
                <a href="/bio/" class="button" style="margin-left: 1rem;">Биография</a>
            </div>
            
        <?php else: ?>
            <!-- Login form -->
            <div class="login-box">
                <h2>Вход в архив</h2>
                
                <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="next" value="<?php echo htmlspecialchars($redirect_after_login); ?>">
                    
                    <div class="form-group">
                        <label for="username">Имя пользователя</label>
                        <input type="text" id="username" name="username" required autofocus>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Пароль</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    
                    <button type="submit" name="login" class="btn-login">Войти</button>
                </form>
            </div>
            
            <div style="text-align: center; margin-top: 2rem;">
                <p>Нет доступа? <a href="/new/" class="button">Запросить код доступа</a></p>
            </div>
        <?php endif; ?>
    </main>
</body>

</html>
