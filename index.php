<?php
require __DIR__ . '/config.php';

$errors = [];
$success = false;
$old = ['name' => '', 'category' => '', 'price' => '', 'quantity' => '', 'sku' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    foreach ($old as $k => $v) $old[$k] = trim($_POST[$k] ?? '');

    if ($old['name'] === '' || mb_strlen($old['name']) > 120) $errors['name'] = 'Enter an item name.';
    if ($old['category'] === '') $errors['category'] = 'Select a category.';
    if (!is_numeric($old['price']) || (float)$old['price'] < 0) $errors['price'] = 'Enter a valid price.';
    if (!ctype_digit($old['quantity']) && $old['quantity'] !== '0') $errors['quantity'] = 'Enter a valid quantity (0 or more).';

    if (!$errors) {
        try {
            db()->prepare('INSERT INTO items (name, category, price, quantity, sku) VALUES (?,?,?,?,?)')
                ->execute([$old['name'], $old['category'], $old['price'], $old['quantity'], $old['sku'] ?: null]);
            $success = true;
            $old = ['name' => '', 'category' => '', 'price' => '', 'quantity' => '', 'sku' => ''];
        } catch (PDOException $e) {
            $errors['general'] = $e->getCode() === '23000' ? 'That SKU is already used by another item.' : 'Something went wrong.';
        }
    }
}

if (isset($_GET['deleted'])) $success = 'deleted';

$q = trim($_GET['q'] ?? '');
$catFilter = trim($_GET['cat'] ?? '');
$where = [];
$params = [];
if ($q !== '') { $where[] = '(name LIKE ? OR sku LIKE ?)'; $like = "%$q%"; $params[] = $like; $params[] = $like; }
if ($catFilter !== '') { $where[] = 'category = ?'; $params[] = $catFilter; }
$sql = 'SELECT * FROM items';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY id DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalItems = (int)db()->query('SELECT COUNT(*) FROM items')->fetchColumn();
$totalValue = (float)db()->query('SELECT COALESCE(SUM(price*quantity),0) FROM items')->fetchColumn();
$lowStock = (int)db()->query('SELECT COUNT(*) FROM items WHERE quantity <= ' . LOW_STOCK_THRESHOLD)->fetchColumn();
$totalCats = (int)db()->query('SELECT COUNT(DISTINCT category) FROM items')->fetchColumn();
$qs = http_build_query(['q' => $q, 'cat' => $catFilter]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Inventory Manager</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<header class="header"><div class="header-row">
  <div class="brand"><span class="dot"></span>Inventory Manager</div>
</div></header>

<div class="w">
  <?php if ($success === 'deleted'): ?><p class="msg ok">Item deleted successfully.</p><?php endif; ?>
  <?php if ($success === true): ?><p class="msg ok">Item added successfully.</p><?php endif; ?>
  <?php if (!empty($errors['general'])): ?><p class="msg err"><?= h($errors['general']) ?></p><?php endif; ?>

  <h2>Overview</h2>
  <div class="stats">
    <div class="stat"><b><?= $totalItems ?></b><span>Total Items</span></div>
    <div class="stat"><b>$<?= number_format($totalValue, 2) ?></b><span>Stock Value</span></div>
    <div class="stat"><b><?= $lowStock ?></b><span>Low Stock</span></div>
    <div class="stat"><b><?= $totalCats ?></b><span>Categories</span></div>
  </div>

  <h2>Add New Item</h2>
  <div class="card">
    <form method="post" novalidate>
      <input type="hidden" name="add" value="1">
      <div class="field">
        <label>Item Name</label>
        <input name="name" value="<?= h($old['name']) ?>">
        <?php if (!empty($errors['name'])): ?><small style="color:var(--err)"><?= h($errors['name']) ?></small><?php endif; ?>
      </div>
      <div class="row3">
        <div class="field">
          <label>Category</label>
          <select name="category">
            <option value="">Select category</option>
            <?php foreach (CATEGORIES as $c): ?><option <?= $old['category'] === $c ? 'selected' : '' ?>><?= h($c) ?></option><?php endforeach; ?>
          </select>
          <?php if (!empty($errors['category'])): ?><small style="color:var(--err)"><?= h($errors['category']) ?></small><?php endif; ?>
        </div>
        <div class="field">
          <label>Price</label>
          <input name="price" type="text" inputmode="decimal" placeholder="0.00" value="<?= h($old['price']) ?>">
          <?php if (!empty($errors['price'])): ?><small style="color:var(--err)"><?= h($errors['price']) ?></small><?php endif; ?>
        </div>
        <div class="field">
          <label>Quantity</label>
          <input name="quantity" type="text" inputmode="numeric" placeholder="0" value="<?= h($old['quantity']) ?>">
          <?php if (!empty($errors['quantity'])): ?><small style="color:var(--err)"><?= h($errors['quantity']) ?></small><?php endif; ?>
        </div>
      </div>
      <div class="field">
        <label>SKU (optional)</label>
        <input name="sku" value="<?= h($old['sku']) ?>" placeholder="e.g. ITM-001">
      </div>
      <button class="btn p" type="submit">Add Item</button>
    </form>
  </div>

  <h2>Items (<?= count($items) ?>)</h2>
  <form class="toolbar" method="get">
    <input type="text" name="q" placeholder="Search by name or SKU..." value="<?= h($q) ?>">
    <select name="cat">
      <option value="">All Categories</option>
      <?php foreach (CATEGORIES as $c): ?><option <?= $catFilter === $c ? 'selected' : '' ?>><?= h($c) ?></option><?php endforeach; ?>
    </select>
    <button class="btn" type="submit">Filter</button>
  </form>

  <?php if (!$items): ?>
    <p class="empty">No items found. Add your first item above.</p>
  <?php else: ?>

  <!-- Mobile: card list -->
  <div class="items">
    <?php foreach ($items as $it): $low = $it['quantity'] <= LOW_STOCK_THRESHOLD; ?>
    <div class="item-card">
      <div class="item-top">
        <div>
          <div class="item-name"><?= h($it['name']) ?></div>
          <?php if ($it['sku']): ?><div class="item-sku"><?= h($it['sku']) ?></div><?php endif; ?>
        </div>
        <div class="price">$<?= number_format($it['price'], 2) ?></div>
      </div>
      <div class="item-meta">
        <span class="badge"><?= h($it['category']) ?></span>
        <span class="qty <?= $low ? 'low' : '' ?>"><?= (int)$it['quantity'] ?> in stock<?= $low ? ' — low' : '' ?></span>
      </div>
      <div class="item-actions">
        <a class="btn" href="edit.php?id=<?= (int)$it['id'] ?>">Edit</a>
        <a class="btn danger" href="delete.php?id=<?= (int)$it['id'] ?>">Delete</a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Desktop: table -->
  <div class="desktop-table">
  <table>
    <tr><th>Name</th><th>SKU</th><th>Category</th><th>Price</th><th>Qty</th><th>Actions</th></tr>
    <?php foreach ($items as $it): $low = $it['quantity'] <= LOW_STOCK_THRESHOLD; ?>
    <tr>
      <td><?= h($it['name']) ?></td>
      <td style="color:var(--mut);font-family:ui-monospace,monospace"><?= h($it['sku']) ?></td>
      <td><span class="badge"><?= h($it['category']) ?></span></td>
      <td>$<?= number_format($it['price'], 2) ?></td>
      <td class="<?= $low ? 'qty low' : '' ?>"><?= (int)$it['quantity'] ?></td>
      <td class="table-actions">
        <a class="btn" href="edit.php?id=<?= (int)$it['id'] ?>">Edit</a>
        <a class="btn danger" href="delete.php?id=<?= (int)$it['id'] ?>">Delete</a>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>

  <?php endif; ?>

  <footer>© <?= date('Y') ?> Inventory Manager. Built with PHP &amp; MySQL.</footer>
</div>
</body>
</html>
