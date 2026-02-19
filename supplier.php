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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warehouse Pro | Suppliers</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <aside class="sidebar">
        <h2>Warehouse Inventory</h2>
        <div class="sidebar-tabs" style="margin-bottom: 20px; display: flex; flex-direction: column; gap: 8px;">
            <a href="index.php" style="color: white; text-decoration: none; padding: 8px 0; border-bottom: 1px solid #34495e;">Inventory Overview</a>
            <a href="<?= $current_file ?>" style="color: white; text-decoration: none; padding: 8px 0; border-bottom: 1px solid #34495e;">Suppliers</a>
            <a href="warehouses.php" style="color: white; text-decoration: none; padding: 8px 0; border-bottom: 1px solid #34495e;">Warehouses</a>
        </div>
    </aside>

    <main class="main-content">
        <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <h1>Suppliers Management</h1>
        </header>

        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0;">All Suppliers</h3>
                <button onclick="openModal()" style="width:auto; padding: 10px 20px;">+ New Supplier</button>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Company Name</th>
                        <th>Contact Person</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Address</th>
                            <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($suppliers): foreach ($suppliers as $row): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                        <td><span class="badge badge-cat"><?= htmlspecialchars($row['contact_name']) ?></span></td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                        <td><?= htmlspecialchars($row['phone']) ?></td>
                        <td style="font-size: 0.85rem; color: #666;"><?= htmlspecialchars($row['address']) ?></td>
                        <td>
                            <a href="javascript:void(0)" onclick='editSupplier(<?= json_encode($row) ?>)' class="text-link">Edit</a>
                            <a href="?delete_supplier=<?= $row['supplier_id'] ?>" onclick="return confirm('Delete this supplier?')" class="text-link" style="color: var(--danger); margin-left: 10px;">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="6" style="text-align:center; padding:50px;">No suppliers found. Create one to get started.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <div id="supplierModal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <h3 id="modalTitle" style="margin-top:0;">Add Supplier</h3>
            <form method="POST">
                <input type="hidden" name="supplier_id" id="form_id">
                
                <label style="color:#333">Company Name</label>
                <input type="text" name="s_name" id="form_name" required placeholder="e.g. TechSource Inc.">
                
                <label style="color:#333">Contact Person</label>
                <input type="text" name="contact" id="form_contact" placeholder="e.g. John Doe">
                
                <label style="color:#333">Email</label>
                <input type="email" name="email" id="form_email" required placeholder="example@company.com">
                
                <label style="color:#333">Phone</label>
                <input type="text" name="phone" id="form_phone" placeholder="e.g. +1-234-567-8900">

                <label style="color:#333">Address</label>
                <textarea name="address" id="form_address" rows="3" placeholder="Full address"></textarea>
                
                <button type="submit" name="save_supplier">Save Supplier</button>
                <button type="button" onclick="closeModal()" style="background:#ccc; margin-top:5px; color:#333">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('modalTitle').innerText = "Add New Supplier";
            document.getElementById('form_id').value = "";
            document.getElementById('form_name').value = "";
            document.getElementById('form_contact').value = "";
            document.getElementById('form_email').value = "";
            document.getElementById('form_phone').value = "";
            document.getElementById('form_address').value = "";
            document.getElementById('supplierModal').style.display = 'flex';
        }

        function editSupplier(data) {
            document.getElementById('modalTitle').innerText = "Edit Supplier";
            document.getElementById('form_id').value = data.supplier_id;
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