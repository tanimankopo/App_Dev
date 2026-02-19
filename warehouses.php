<?php
require 'db.php';

if (isset($_POST['add_warehouse'])) {
    try {
        $sql = "INSERT INTO warehouses (name, location) VALUES (?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['wh_name'], $_POST['location']
        ]);
        header("Location: warehouses.php?success=warehouse");
        exit();
    } catch (Exception $e) {
        die("Error adding warehouse: " . $e->getMessage());
    }
}

if (isset($_GET['delete_warehouse'])) {
    try {
        $pdo->beginTransaction();
        
        $wh_id = $_GET['delete_warehouse'];
        
        $pdo->prepare("DELETE FROM transactions WHERE warehouse_id = ?")->execute([$wh_id]);
        $pdo->prepare("DELETE FROM inventory WHERE warehouse_id = ?")->execute([$wh_id]);
        $pdo->prepare("DELETE FROM warehouses WHERE warehouse_id = ?")->execute([$wh_id]);
        
        $pdo->commit();
        header("Location: warehouses.php?deleted=1");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error deleting warehouse: " . $e->getMessage());
    }
}

$query = "SELECT w.warehouse_id, w.name AS warehouse_name, w.location,
                 COUNT(i.inventory_id) AS total_items,
                 COALESCE(SUM(i.quantity), 0) AS total_quantity
          FROM warehouses w
          LEFT JOIN inventory i ON w.warehouse_id = i.warehouse_id
          GROUP BY w.warehouse_id, w.name, w.location
          ORDER BY w.name";

$stmt = $pdo->prepare($query);
$stmt->execute();
$warehouses = $stmt->fetchAll();

$inventory_query = "SELECT i.inventory_id, w.warehouse_id, w.name AS warehouse_name, p.name AS product_name, 
                           p.sku, p.price, i.quantity
                    FROM inventory i
                    JOIN warehouses w ON i.warehouse_id = w.warehouse_id
                    JOIN products p ON i.product_id = p.product_id
                    ORDER BY w.name, p.name";

$stmt = $pdo->prepare($inventory_query);
$stmt->execute();
$all_inventory = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warehouse Management | Warehouse Pro</title>
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
            <h1>Warehouse Management</h1>
        </header>

        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0;">All Warehouses Overview</h3>
                <button onclick="document.getElementById('whModal').style.display='flex'" style="width:auto; padding: 10px 20px;">+ New Warehouse</button>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Warehouse Name</th>
                        <th>Location</th>
                        <th>Total Items</th>
                        <th>Total Quantity</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($warehouses): foreach($warehouses as $wh): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($wh['warehouse_name']) ?></strong></td>
                        <td><?= htmlspecialchars($wh['location']) ?></td>
                        <td><span class="badge badge-cat"><?= $wh['total_items'] ?> Products</span></td>
                        <td>
                            <strong style="color: <?= $wh['total_quantity'] < 50 ? 'var(--warning)' : 'var(--success)' ?>;">
                                <?= $wh['total_quantity'] ?> Units
                            </strong>
                        </td>
                        <td>
                            <a href="javascript:void(0)" onclick="toggleInventory(<?= $wh['warehouse_id'] ?>)" class="text-link" id="arrow_<?= $wh['warehouse_id'] ?>">Details ▼</a>
                            <a href="?delete_warehouse=<?= $wh['warehouse_id'] ?>" class="text-link" onclick="return confirm('Delete this warehouse and all its inventory records?')" style="color: var(--danger); margin-left: 10px;">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="5" style="text-align:center; padding:50px;">No warehouses found. Create one to get started.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php foreach($warehouses as $wh): ?>
        <section class="card" id="wh_<?= $wh['warehouse_id'] ?>" style="margin-top: 30px; display: none;">
            <h3 style="cursor: pointer; user-select: none;" onclick="toggleInventory(<?= $wh['warehouse_id'] ?>)">
                📦 <?= htmlspecialchars($wh['warehouse_name']) ?> - Inventory Details
            </h3>
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <th>Total Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $wh_inventory = array_filter($all_inventory, function($item) use ($wh) {
                        return $item['warehouse_id'] == $wh['warehouse_id'];
                    });
                    
                    if($wh_inventory): 
                        foreach($wh_inventory as $item): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($item['product_name']) ?></strong></td>
                            <td><?= htmlspecialchars($item['sku']) ?></td>
                            <td>$<?= number_format($item['price'] ?? 0, 2) ?></td>
                            <td><span class="badge" style="background: #e8f4f8; color: var(--primary); font-weight: bold;"><?= $item['quantity'] ?> Units</span></td>
                            <td><strong>$<?= number_format(($item['price'] ?? 0) * $item['quantity'], 2) ?></strong></td>
                        </tr>
                        <?php endforeach;
                    else: ?>
                        <tr><td colspan="5" style="text-align:center; padding:30px;">No inventory in this warehouse yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
        <?php endforeach; ?>

    </main>

    <div id="whModal" class="modal-overlay" style="display:none;">
        <div class="modal-content">
            <h3>Add New Warehouse</h3>
            <form method="POST">
                <label style="color:#333">Warehouse Name</label>
                <input type="text" name="wh_name" required placeholder="e.g. Main Warehouse">
                <label style="color:#333">Location</label>
                <input type="text" name="location" required placeholder="e.g. New York, NY">
                <button type="submit" name="add_warehouse">Create Warehouse</button>
                <button type="button" onclick="this.parentElement.parentElement.parentElement.style.display='none'" style="background:#ccc; margin-top:5px; color:#333">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        function toggleInventory(warehouseId) {
            const section = document.getElementById('wh_' + warehouseId);
            const arrow = document.getElementById('arrow_' + warehouseId);
            
            if (section.style.display === 'none' || section.style.display === '') {
                section.style.display = 'block';
                arrow.textContent = 'Details ▲';
            } else {
                section.style.display = 'none';
                arrow.textContent = 'Details ▼';
            }
        }
    </script>

</body>
</html>
