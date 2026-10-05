<?php
require __DIR__ . '/config.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM items WHERE id = ?');
$stmt->execute([$id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$item) { header('Location: index.php'); exit; }

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $quantity = trim($_POST['quantity'] ?? '');
    $sku = trim($_POST['sku'] ?? '');

    if ($name === '' || mb_strlen($name) > 120) $errors['name'] = 'Enter an item name.';
    if ($category === '') $errors['category'] = 'Select a category.';
    if (!is_numeric($price) || (float)$price < 0) $errors['price'] = 'Enter a valid price.';
    if (!ctype_digit($quantity) && $quantity !== '0') $errors['quantity'] = 'Enter a valid quantity.';

    if (!$errors) {
        try {
            db()->prepare('UPDATE items SET name=?, category=?, price=?, quantity=?, sku=? WHERE id=?')
                ->execute([$name, $category, $price, $quantity, $sku ?: null, $id]);
            $success = true;
            $stmt->execute([$id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $errors['general'] = $e->getCode() === '23000' ? 'That SKU is already used by another item.' : 'Something went wrong.';
        }
    } else {
        $item = ['id' => $id, 'name' => $name, 'category' => $category, 'price' => $price, 'quantity' => $quantity, 'sku' => $sku];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Edit Item</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<header class="header"><div class="header-row">
  <div class="brand"><span class="dot"></span>Inventory Manager</div>
</div></header>

<div class="w">
  <p style="margin-bottom:16px"><a href="index.php" style="color:var(--ac)">← Back to items</a></p>
  <h1>Edit Item</h1>
  <p class="sub">Update this item's details.</p>

  <?php if ($success): ?><p class="msg ok">Item updated successfully.</p><?php endif; ?>
  <?php if (!empty($errors['general'])): ?><p class="msg err"><?= h($errors['general']) ?></p><?php endif; ?>

  <div class="card">
    <form method="post" novalidate>
      <div class="field">
        <label>Item Name</label>
        <input name="name" value="<?= h($item['name']) ?>">
        <?php if (!empty($errors['name'])): ?><small style="color:var(--err)"><?= h($errors['name']) ?></small><?php endif; ?>
      </div>
      <div class="row3">
        <div class="field">
          <label>Category</label>
          <select name="category">
            <?php foreach (CATEGORIES as $c): ?><option <?= $item['category'] === $c ? 'selected' : '' ?>><?= h($c) ?></option><?php endforeach; ?>
          </select>
          <?php if (!empty($errors['category'])): ?><small style="color:var(--err)"><?= h($errors['category']) ?></small><?php endif; ?>
        </div>
        <div class="field">
          <label>Price</label>
          <input name="price" type="text" inputmode="decimal" value="<?= h($item['price']) ?>">
          <?php if (!empty($errors['price'])): ?><small style="color:var(--err)"><?= h($errors['price']) ?></small><?php endif; ?>
        </div>
        <div class="field">
          <label>Quantity</label>
          <input name="quantity" type="text" inputmode="numeric" value="<?= h($item['quantity']) ?>">
          <?php if (!empty($errors['quantity'])): ?><small style="color:var(--err)"><?= h($errors['quantity']) ?></small><?php endif; ?>
        </div>
      </div>
      <div class="field">
        <label>SKU</label>
        <input name="sku" value="<?= h($item['sku']) ?>">
      </div>
      <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
      <button class="btn p" type="submit">Save Changes</button>
      <a class="btn" href="index.php" style="margin-top:10px">Cancel</a>
    </form>
  </div>
</div>
</body>
</html>
