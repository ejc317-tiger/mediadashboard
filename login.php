<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
if (loggedIn()) { header('Location: index.php'); exit; }

$error = '';
$needsDatabaseSetup = !hasDatabaseConfiguration();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'configure_database') {
    try {
        verifyCsrf($_POST['csrf'] ?? '');
        saveDatabaseConfiguration($_POST);
        header('Location: login.php');
        exit;
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$databaseReady = false;
$hasUsers = false;
if (!$needsDatabaseSetup) {
    try {
        $hasUsers = (int) database()->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
        $databaseReady = true;
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'authenticate' && $databaseReady) {
    try {
        verifyCsrf($_POST['csrf'] ?? '');
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $password = $_POST['password'] ?? '';
        if (!$email || strlen($password) < 10) throw new RuntimeException('Enter a valid email and a password of at least 10 characters.');
        if (!$hasUsers) {
            $statement = database()->prepare('INSERT INTO users(email,password_hash,display_name) VALUES(?,?,?)');
            $statement->execute([$email, password_hash($password, PASSWORD_DEFAULT), trim($_POST['name'] ?? 'Administrator')]);
            $id = (int) database()->lastInsertId();
        } else {
            $statement = database()->prepare('SELECT id,password_hash FROM users WHERE email=?');
            $statement->execute([$email]);
            $user = $statement->fetch();
            if (!$user || !password_verify($password, $user['password_hash'])) throw new RuntimeException('Email or password is incorrect.');
            $id = (int) $user['id'];
            database()->prepare('UPDATE users SET last_login_at=NOW() WHERE id=?')->execute([$id]);
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = $id;
        header('Location: index.php');
        exit;
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}
?>
<!doctype html><html><head><meta name="viewport" content="width=device-width"><title>Sign in · Northstar</title><link rel="stylesheet" href="styles.css?v=2"></head><body class="auth-page">
<form class="auth-card" method="post">
  <div class="brand"><span class="brand-mark"><i></i><i></i><i></i></span><span>northstar</span></div>
  <?php if ($needsDatabaseSetup): ?>
    <h1>Connect database</h1><p>Enter the database details supplied by your hosting provider. They are saved only in an untracked server configuration file.</p>
    <?php if ($error): ?><div class="auth-error"><?=htmlspecialchars($error)?></div><?php endif ?>
    <input type="hidden" name="csrf" value="<?=csrfToken()?>"><input type="hidden" name="action" value="configure_database">
    <div class="auth-fields-inline"><label>Host<input name="host" value="localhost" required autocomplete="off"></label><label>Port<input name="port" value="3306" inputmode="numeric" required autocomplete="off"></label></div>
    <label>Database name<input name="database" required autocomplete="off"></label><label>Database username<input name="username" required autocomplete="username"></label><label>Database password<input type="password" name="password" autocomplete="new-password"></label>
    <button class="primary">Test connection and continue</button>
  <?php else: ?>
    <h1><?=$hasUsers ? 'Welcome back' : 'Create administrator'?></h1><p><?=$hasUsers ? 'Sign in to your intelligence workspace.' : 'Secure the new workspace with its first account.'?></p>
    <?php if ($error): ?><div class="auth-error"><?=htmlspecialchars($error)?></div><?php endif ?>
    <input type="hidden" name="csrf" value="<?=csrfToken()?>"><input type="hidden" name="action" value="authenticate">
    <?php if (!$hasUsers): ?><label>Name<input name="name" required autocomplete="name"></label><?php endif ?>
    <label>Email<input type="email" name="email" required autocomplete="email"></label><label>Password<input type="password" name="password" minlength="10" required autocomplete="<?=$hasUsers ? 'current-password' : 'new-password'?>"></label>
    <button class="primary" <?=$databaseReady ? '' : 'disabled'?>><?=$databaseReady ? ($hasUsers ? 'Sign in' : 'Create account') : 'Database unavailable'?></button>
  <?php endif ?>
</form></body></html>
