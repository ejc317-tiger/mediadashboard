<?php
declare(strict_types=1); require __DIR__.'/auth.php';
if (loggedIn()) { header('Location: index.php'); exit; }
$error=''; $hasUsers=(int)database()->query('SELECT COUNT(*) FROM users')->fetchColumn()>0;
if ($_SERVER['REQUEST_METHOD']==='POST') {
 try { verifyCsrf($_POST['csrf']??''); $email=filter_var(trim($_POST['email']??''),FILTER_VALIDATE_EMAIL); $password=$_POST['password']??'';
  if (!$email || strlen($password)<10) throw new RuntimeException('Enter a valid email and a password of at least 10 characters.');
  if (!$hasUsers) { $stmt=database()->prepare('INSERT INTO users(email,password_hash,display_name) VALUES(?,?,?)');$stmt->execute([$email,password_hash($password,PASSWORD_DEFAULT),trim($_POST['name']??'Administrator')]);$id=(int)database()->lastInsertId(); }
  else { $stmt=database()->prepare('SELECT id,password_hash FROM users WHERE email=?');$stmt->execute([$email]);$user=$stmt->fetch();if(!$user||!password_verify($password,$user['password_hash']))throw new RuntimeException('Email or password is incorrect.');$id=(int)$user['id'];database()->prepare('UPDATE users SET last_login_at=NOW() WHERE id=?')->execute([$id]); }
  session_regenerate_id(true);$_SESSION['user_id']=$id;header('Location: index.php');exit;
 } catch(Throwable $e){$error=$e->getMessage();}
}
?><!doctype html><html><head><meta name="viewport" content="width=device-width"><title>Sign in · Northstar</title><link rel="stylesheet" href="styles.css"></head><body class="auth-page"><form class="auth-card" method="post"><div class="brand"><span class="brand-mark"><i></i><i></i><i></i></span><span>northstar</span></div><h1><?= $hasUsers?'Welcome back':'Create administrator' ?></h1><p><?= $hasUsers?'Sign in to your intelligence workspace.':'Secure the new workspace with its first real account.' ?></p><?php if($error):?><div class="auth-error"><?=htmlspecialchars($error)?></div><?php endif?><input type="hidden" name="csrf" value="<?=csrfToken()?>"><?php if(!$hasUsers):?><label>Name<input name="name" required autocomplete="name"></label><?php endif?><label>Email<input type="email" name="email" required autocomplete="email"></label><label>Password<input type="password" name="password" minlength="10" required autocomplete="<?= $hasUsers?'current-password':'new-password' ?>"></label><button class="primary"><?= $hasUsers?'Sign in':'Create account' ?></button></form></body></html>
