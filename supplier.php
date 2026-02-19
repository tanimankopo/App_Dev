<?php
require 'db.php';

// Dynamically get the current filename to prevent "404 Not Found" errors
$current_file = basename($_SERVER['PHP_SELF']);

// --- CREATE & UPDATE LOGIC ---
if (isset($_POST['save_supplier'])) {
    $s_name = $_POST['s_name'];
    $contact = $_POST['contact'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    // Use empty check to distinguish between a new entry and an ID of 0
    $id = !empty($_POST['supplier_id']) ? $_POST['supplier_id'] : null;

    if ($id) {
        // Update existing
        $sql = "UPDATE suppliers SET name=?, contact_name=?, email=?, phone=?, address=? WHERE supplier_id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$s_name, $contact, $email, $phone, $address, $id]);
    } else {
        // Insert new
        $sql = "INSERT INTO suppliers (name, contact_name, email, phone, address) VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$s_name, $contact, $email, $phone, $address]);
    }
    // Redirect to the detected current file
    header("Location: $current_file?success=1");
    exit();
}

// --- DELETE LOGIC ---
if (isset($_GET['delete_supplier'])) {
    $stmt = $pdo->prepare("DELETE FROM suppliers WHERE supplier_id = ?");
    $stmt->execute([$_GET['delete_supplier']]);
    header("Location: $current_file?deleted=1");
    exit();
}

// --- READ LOGIC ---
$suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY supplier_id DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Warehouse Pro | Suppliers</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Ensuring modal is centered if not in your CSS */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); display: flex; 
            justify-content: center; align-items: center; z-index: 1000;
        }
        .modal-content { background: white; padding: 20px; border-radius: 8px; width: 400px; }
        .modal-content input { width: 100%; padding: 8px; margin: 10px 0; display: block; }
    </style>
</head>
<body>
    <aside class="sidebar">
        <h2>Warehouse Inventory</h2>
        <div class="sidebar-tabs" style="margin-bottom: 20px; display: flex; flex-direction: column; gap: 8px;">
            <a href="index.php" style="color: white; text-decoration: none; padding: 8px 0; border-bottom: 1px solid #34495e;">Inventory Overview</a>
            <a href="<?= $current_file ?>" style="color: white; text-decoration: none; padding: 8px 0; border-bottom: 1px solid #34495e;">Suppliers</a>
            <a href="transactions.php" style="color: white; text-decoration: none; padding: 8px 0; border-bottom: 1px solid #34495e;">Transactions</a>
            <a href="warehouses.php" style="color: white; text-decoration: none; padding: 8px 0; border-bottom: 1px solid #34495e;">Warehouses</a>
        </div>
    </aside>

    <main class="main-content">
        <h1>Suppliers List</h1>

        <div class="filter-group">
            <button onclick="openModal()">+ Add New Supplier</button>
        </div>

        <section class="card">
            <table>
                <thead>
                    <tr>
                        <th>Company</th>
                        <th>Contact</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Address</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($suppliers as $row): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                        <td><span class="badge badge-cat"><?= htmlspecialchars($row['contact_name']) ?></span></td>
                        <td><?= htmlspecialchars($row['phone']) ?></td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                        <td style="font-size: 0.85rem; color: #666;"><?= htmlspecialchars($row['address']) ?></td>
                        <td style="text-align: right;">
                            <a href="javascript:void(0)" onclick='editSupplier(<?= json_encode($row) ?>)' style="color: var(--accent); margin-right: 10px; text-decoration: none; font-weight: 600;">EDIT</a>
                            <a href="?delete_supplier=<?= $row['supplier_id'] ?>" onclick="return confirm('Delete?')" style="color: var(--danger); text-decoration: none; font-weight: 600;">DEL</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section> 
    </main>

    <div id="supplierModal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <h2 id="modalTitle" style="margin-top:0; color: var(--primary);">Add Supplier</h2>
            <form method="POST">
                <input type="hidden" name="supplier_id" id="form_id">
                
                <label>Company Name</label>
                <input type="text" name="s_name" id="form_name" required>
                
                <label>Contact Person</label>
                <input type="text" name="contact" id="form_contact">
                
                <label>Email</label>
                <input type="email" name="email" id="form_email" required>
                
                <label>Phone</label>
                <input type="text" name="phone" id="form_phone">

                <label>Address</label>
                <textarea name="address" id="form_address" rows="2" style="width: 100%; border-radius: 4px; border: 1px solid #ddd; padding: 10px; margin-bottom: 15px;"></textarea>
                
                <div style="display: flex; gap: 10px;">
                    <button type="submit" name="save_supplier" style="background: var(--success); color: white; border: none; padding: 10px; flex: 1; cursor: pointer;">Save Changes</button>
                    <button type="button" onclick="closeModal()" style="background: #95a5a6; color: white; border: none; padding: 10px; flex: 1; cursor: pointer;">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('modalTitle').innerText = "Add New Supplier";
            document.getElementById('form_id').value = ""; // Clear for Add
            document.getElementById('form_name').value = "";
            document.getElementById('form_contact').value = "";
            document.getElementById('form_email').value = "";
            document.getElementById('form_phone').value = "";
            document.getElementById('form_address').value = "";
            document.getElementById('supplierModal').style.display = 'flex';
        }

        function editSupplier(data) {
            document.getElementById('modalTitle').innerText = "Edit Supplier";
            document.getElementById('form_id').value = data.supplier_id; // Fill for Edit
            document.getElementById('form_name').value = data.name;
            document.getElementById('form_contact').value = data.contact_name;
            document.getElementById('form_email').value = data.email;
            document.getElementById('form_phone').value = data.phone;
            document.getElementById('form_address').value = data.address;
            document.getElementById('supplierModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('supplierModal').style.display = 'none';
        }
    </script>
</body>
</html>