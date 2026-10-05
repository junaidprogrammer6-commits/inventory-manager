<?php
require __DIR__ . '/config.php';
session_start();
$_SESSION['csrf'] ??= bin2hex(random_bytes(16));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        db()->prepare('DELETE FROM items WHERE id = ?')->execute([(int)$_POST['id']]);
    }
    header('Location: index.php?deleted=1');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT name FROM items WHERE id = ?');
$stmt->execute([$id]);
$name = $stmt->fetchColumn();
if ($name === false) { header('Location: index.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Delete Item</title>
<link rel="stylesheet" href="style.css">
<style>
.modal-bg{position:fixed;inset:0;background:rgba(0,0,0,.6);display:flex;align-items:center;justify-content:center;padding:20px}
.modal{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:26px;max-width:360px;text-align:center}
.modal p{color:var(--mut);margin:10px 0 20px}
.modal .row-btns{display:flex;gap:10px}
.modal .row-btns .btn{flex:1}
</style>
</head>
<body>
<div class="modal-bg">
<div class="modal">
  <h3>Delete this item?</h3>
  <p><strong><?= h($name) ?></strong> will be permanently removed. This cannot be undone.</p>
  <form method="post" class="row-btns">
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
    <a class="btn" href="index.php">Cancel</a>
    <button class="btn danger" type="submit">Delete</button>
  </form>
</div>
</div>
</body>
</html>
