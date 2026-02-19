<?php 
require 'db.php';

if (isset($_POST['add_product'])) {
    try {
        $pdo->beginTransaction();
        
        $sql = "INSERT INTO products (name, sku, category_id, supplier_id, price, description) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['name'], $_POST['sku'], $_POST['category_id'], 
            $_POST['supplier_id'], $_POST['price'], $_POST['description']
        ]);
                        
        $product_id = $pdo->lastInsertId();
        $qty = $_POST['initial_qty'] ?? 0;
        $warehouse_id = $_POST['warehouse_id'] ?? 1;
        
        $inv_sql = "INSERT INTO inventory (product_id, warehouse_id, quantity) VALUES (?, ?, ?)";
        $pdo->prepare($inv_sql)->execute([$product_id, $warehouse_id, $qty]);
        
        $pdo->commit();
        header("Location: index.php?success=product");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error adding product: " . $e->getMessage());
    }
}

if (isset($_POST['add_transaction'])) {
    try {
        $pdo->beginTransaction();

        $sqlTr = "INSERT INTO transactions (product_id, warehouse_id, quantity, transaction_type) VALUES (?, ?, ?, ?)";
        $pdo->prepare($sqlTr)->execute([
            $_POST['product_id'], $_POST['warehouse_id'], $_POST['qty'], $_POST['type']
        ]);

        $sqlInv = "INSERT INTO inventory (product_id, warehouse_id, quantity) 
                   VALUES (?, ?, ?) 
                   ON DUPLICATE KEY UPDATE quantity = 
                   IF(? = 'IN', quantity + VALUES(quantity), quantity - VALUES(quantity))";
        
        $pdo->prepare($sqlInv)->execute([
            $_POST['product_id'], $_POST['warehouse_id'], $_POST['qty'], $_POST['type']
        ]);

        $pdo->commit();
        header("Location: index.php?success=transaction");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Critical Error: " . $e->getMessage());
    }
}   


if (isset($_POST['add_supplier'])) {
    $sql = "INSERT INTO suppliers (name, contact_person, email, phone) VALUES (?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $_POST['s_name'], $_POST['contact'], $_POST['email'], $_POST['phone']
    ]);
    header("Location: suppliers.php?success=supplier");
    exit();
}


if (isset($_GET['delete_supplier'])) {
     $stmt = $pdo->prepare("DELETE FROM suppliers WHERE supplier_id = ?");
    $stmt->execute([$_GET['delete_supplier']]);
    header("Location: suppliers.php?deleted=1");
    exit();
}

$cat_filter = $_GET['category'] ?? '';
$wh_filter = $_GET['warehouse'] ?? '';

$query = "SELECT p.product_id, p.name AS product_name, p.sku, p.price, c.name AS category_name, 
                 s.name AS supplier_name, w.name AS warehouse_name, i.quantity
          FROM inventory i
          JOIN products p ON i.product_id = p.product_id
          JOIN categories c ON p.category_id = c.category_id
          JOIN suppliers s ON p.supplier_id = s.supplier_id
          JOIN warehouses w ON i.warehouse_id = w.warehouse_id
          WHERE 1=1";

$params = [];
if ($cat_filter) { $query .= " AND c.category_id = ?"; $params[] = $cat_filter; }
if ($wh_filter) { $query .= " AND w.warehouse_id = ?"; $params[] = $wh_filter; }

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$inventory_items = $stmt->fetchAll();

$categories = $pdo->query("SELECT * FROM categories")->fetchAll();
$warehouses = $pdo->query("SELECT * FROM warehouses")->fetchAll();
$suppliers = $pdo->query("SELECT * FROM suppliers")->fetchAll();
$all_products = $pdo->query("SELECT product_id, name FROM products")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warehouse Pro | Inventory Manager</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>



    <aside class="sidebar">
        <h2>Warehouse Inventory</h2>

        <div class="sidebar-tabs" style="margin-bottom: 20px; display: flex; flex-direction: column; gap: 8px;">
            <a href="index.php" style="color: white; text-decoration: none; padding: 8px 0; border-bottom: 1px solid #34495e;">Inventory Overview</a>
            <a href="supplier.php" style="color: white; text-decoration: none; padding: 8px 0; border-bottom: 1px solid #34495e;">Suppliers</a>
            <a href="warehouses.php" style="color: white; text-decoration: none; padding: 8px 0; border-bottom: 1px solid #34495e;">Warehouses</a>
        </div>

    </aside>



    <main class="main-content">
        <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <h1>Inventory Overview</h1>



            <form method="GET">
        <div class="filter-group">
            <label>Category</label>
            <select name="category">
                <option value="">All Categories</option>
                <?php foreach($categories as $cat): ?>
                    <option value="<?= $cat['category_id'] ?>" <?= $cat_filter == $cat['category_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group">
            <label>Warehouse</label>
            <select name="warehouse">
                <option value="">All Locations</option>
                <?php foreach($warehouses as $wh): ?>
                    <option value="<?= $wh['warehouse_id'] ?>" <?= $wh_filter == $wh['warehouse_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($wh['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit">Apply Filters</button>
        <a href="index.php" style="display:block; text-align:center; color:#bdc3c7; margin-top:15px; text-decoration:none; font-size:0.8rem;">Reset Filters</a>
    </form>



            <div style="display: flex; gap: 10px;">





            
                <button onclick="document.getElementById('prodModal').style.display='flex'" style="width:auto; padding: 10px 20px;">+ New Product</button>
                <button onclick="document.getElementById('transModal').style.display='flex'" style="width:auto; padding: 10px 20px; background: var(--primary);">+ Stock Movement</button>
            </div>
        </header>

        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>Product Details</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Warehouse</th>
                        <th>Stock Level</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($inventory_items): foreach($inventory_items as $item): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($item['product_name']) ?></strong><br>
                            <small style="color: #888;">SKU: <?= htmlspecialchars($item['sku']) ?></small>
                        </td>
                        <td><span class="badge badge-cat"><?= htmlspecialchars($item['category_name']) ?></span></td>
                        <td><strong>$<?= number_format($item['price'] ?? 0, 2) ?></strong></td>
                        <td> <?= htmlspecialchars($item['warehouse_name']) ?></td>
                        <td>
                            <strong style="color: <?= $item['quantity'] < 10 ? 'var(--danger)' : 'var(--success)' ?>;">
                                <?= $item['quantity'] ?> Units
                            </strong>
                        </td>
                        <td>
                            <a href="javascript:void(0)" onclick="toggleHistory(<?= $item['product_id'] ?>)" class="text-link" id="history_arrow_<?= $item['product_id'] ?>">History ▼</a>
                            <a href="?delete_product=<?= $item['product_id'] ?>" class="text-link" onclick="return confirm('Delete this product and all its records?')" style="color: var(--danger); margin-left: 10px;">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="6" style="text-align:center; padding:50px;">No inventory found. Try changing filters or adding a transaction.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php 
        // Generate history sections for all products
        foreach($inventory_items as $item): 
            $p_id = $item['product_id'];
            $h_stmt = $pdo->prepare("SELECT t.*, w.name as w_name, p.name as p_name FROM transactions t JOIN warehouses w ON t.warehouse_id = w.warehouse_id JOIN products p ON t.product_id = p.product_id WHERE t.product_id = ? ORDER BY t.transaction_date DESC");
            $h_stmt->execute([$p_id]);
            $history = $h_stmt->fetchAll();
            if ($history): 
        ?>
            <section class="history-section" id="history_<?= $p_id ?>" style="display: none;">
                <h3>Movement Logs: <?= htmlspecialchars($item['product_name']) ?></h3>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Warehouse</th>
                            <th>Type</th>
                            <th>Qty Change</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($history as $tr): ?>
                        <tr>
                            <td><?= date('M d, Y | H:i', strtotime($tr['transaction_date'])) ?></td>
                            <td><?= htmlspecialchars($tr['w_name']) ?></td>
                            <td><span class="badge <?= $tr['transaction_type'] == 'IN' ? 'badge-in' : 'badge-out' ?>"><?= $tr['transaction_type'] ?></span></td>
                            <td><strong><?= $tr['quantity'] ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        <?php endif; endforeach; ?>
    </main>

    <div id="prodModal" class="modal-overlay" style="display:none;">
        <div class="modal-content">
            <h3>Add New Product</h3>
            <form method="POST">
                <label style="color:#333">Product Name</label>
                <input type="text" name="name" required placeholder="e.g. Wireless Mouse">
                <label style="color:#333">SKU Code</label>
                <input type="text" name="sku" required placeholder="e.g. WM-001">
                <label style="color:#333">Category</label>
                <select name="category_id" required>
                    <?php foreach($categories as $c) echo "<option value='{$c['category_id']}'>{$c['name']}</option>"; ?>
                </select>
                <label style="color:#333">Supplier</label>
                <select name="supplier_id" required>
                    <?php foreach($suppliers as $s) echo "<option value='{$s['supplier_id']}'>{$s['name']}</option>"; ?>
                </select>
                <label style="color:#333">Price ($)</label>
                <input type="number" step="0.01" name="price" placeholder="0.00">
                <label style="color:#333">Description</label>
                <textarea name="description" rows="3"></textarea>
                <label style="color:#333">Initial Warehouse</label>
                <select name="warehouse_id" required>
                    <?php foreach($warehouses as $w) echo "<option value='{$w['warehouse_id']}'>{$w['name']}</option>"; ?>
                </select>
                <label style="color:#333">Initial Quantity</label>
                <input type="number" name="initial_qty" value="0" min="0" placeholder="0">
                <button type="submit" name="add_product">Save to Database</button>
                <button type="button" onclick="this.parentElement.parentElement.parentElement.style.display='none'" style="background:#ccc; margin-top:5px; color:#333">Cancel</button>
            </form>
        </div>
    </div>

    <div id="transModal" class="modal-overlay" style="display:none;">
        <div class="modal-content">
            <h3>Log Stock Movement</h3>
            <form method="POST">
                <label style="color:#333">Select Product</label>
                <select name="product_id" required>
                    <?php foreach($all_products as $p) echo "<option value='{$p['product_id']}'>{$p['name']}</option>"; ?>
                </select>
                <label style="color:#333">Warehouse</label>
                <select name="warehouse_id" required>
                    <?php foreach($warehouses as $w) echo "<option value='{$w['warehouse_id']}'>{$w['name']}</option>"; ?>
                </select>
                <label style="color:#333">Movement Type</label>
                <select name="type" required>
                    <option value="IN">Stock IN (+)</option>
                    <option value="OUT">Stock OUT (-)</option>
                </select>
                <label style="color:#333">Quantity</label>
                <input type="number" name="qty" required min="1">
                <button type="submit" name="add_transaction" style="background: var(--primary);">Update Stock</button>
                <button type="button" onclick="this.parentElement.parentElement.parentElement.style.display='none'" style="background:#ccc; margin-top:5px; color:#333">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        function toggleHistory(productId) {
            const section = document.getElementById('history_' + productId);
            const arrow = document.getElementById('history_arrow_' + productId);
            
            if (section.style.display === 'none' || section.style.display === '') {
                section.style.display = 'block';
                arrow.textContent = 'History ▲';
            } else {
                section.style.display = 'none';
                arrow.textContent = 'History ▼';
            }
        }
    </script>

</body>
</html>