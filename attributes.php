<?php
// attributes.php
require_once '../admin/config.php';

// Initialize variables
$error = '';
$success = '';
$attribute = []; // Initialize attribute array

// Check if we're in edit mode and fetch attribute data
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM product_attributes WHERE attribute_id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $attribute = $result->fetch_assoc();
    } else {
        $error = "Attribute not found";
    }
    $stmt->close();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Security token validation failed";
    } else {
        $action = $_POST['action'] ?? '';
        $product_id = intval($_POST['product_id'] ?? 0);
        $attribute_type = $_POST['attribute_type'] ?? '';
        $value = trim($_POST['value'] ?? '');
        $stock = !empty($_POST['stock']) ? intval($_POST['stock']) : null;
        $price_modifier = floatval($_POST['price_modifier'] ?? 0.00);
        
        if ($action === 'create') {
            $stmt = $conn->prepare("INSERT INTO product_attributes (product_id, attribute_type, value, stock, price_modifier) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("isssd", $product_id, $attribute_type, $value, $stock, $price_modifier);
            
            if ($stmt->execute()) {
                $success = "Attribute created successfully!";
            } else {
                $error = "Error creating attribute: " . $stmt->error;
            }
            $stmt->close();
        } 
        elseif ($action === 'update') {
            $attribute_id = intval($_POST['attribute_id']);
            $stmt = $conn->prepare("UPDATE product_attributes SET product_id=?, attribute_type=?, value=?, stock=?, price_modifier=? WHERE attribute_id=?");
            $stmt->bind_param("isssdi", $product_id, $attribute_type, $value, $stock, $price_modifier, $attribute_id);
            
            if ($stmt->execute()) {
                $success = "Attribute updated successfully!";
                // Refresh attribute data after update
                $stmt = $conn->prepare("SELECT * FROM product_attributes WHERE attribute_id = ?");
                $stmt->bind_param("i", $attribute_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $attribute = $result->fetch_assoc();
            } else {
                $error = "Error updating attribute: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Handle delete requests
if (isset($_GET['delete'])) {
    $attribute_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM product_attributes WHERE attribute_id = ?");
    $stmt->bind_param("i", $attribute_id);
    
    if ($stmt->execute()) {
        $success = "Attribute deleted successfully!";
    } else {
        $error = "Error deleting attribute: " . $stmt->error;
    }
    $stmt->close();
}

// Fetch all attributes with product names
$attributes = $conn->query("
    SELECT a.*, p.name AS product_name 
    FROM product_attributes a
    JOIN products p ON a.product_id = p.product_id
    ORDER BY a.attribute_id DESC
");

// Fetch all products for dropdown in alphabetical order
$products = $conn->query("SELECT product_id, name AS product_name FROM products ORDER BY product_name ASC");
?>

<div class="attributes-container">
    <h2>Manage Product Attributes</h2>
    
    <?php if ($error): ?>
        <div class="message error-message">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="message success-message">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-header">
            <h3><?= isset($_GET['edit']) ? 'Edit Attribute' : 'Add New Attribute' ?></h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="<?= isset($_GET['edit']) ? 'update' : 'create' ?>">
                
                <?php if (isset($_GET['edit'])): ?>
                    <input type="hidden" name="attribute_id" value="<?= $attribute['attribute_id'] ?>">
                <?php endif; ?>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Product</label>
                        <select name="product_id" class="form-control" required>
                            <option value="">Select Product</option>
                            <?php 
                            $products->data_seek(0); // Reset pointer
                            while ($product = $products->fetch_assoc()): 
                                $selected = (isset($attribute['product_id']) && $attribute['product_id'] == $product['product_id']) ? 'selected' : '';
                            ?>
                                <option value="<?= $product['product_id'] ?>" <?= $selected ?>>
                                    <?= htmlspecialchars($product['product_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Attribute Type</label>
                        <select name="attribute_type" class="form-control" required>
                            <option value="">Select Type</option>
                            <option value="size" <?= (isset($attribute['attribute_type']) && $attribute['attribute_type'] === 'size') ? 'selected' : '' ?>>Size</option>
                            <option value="color" <?= (isset($attribute['attribute_type']) && $attribute['attribute_type'] === 'color') ? 'selected' : '' ?>>Color</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Value</label>
                        <input type="text" name="value" class="form-control" 
                               value="<?= isset($attribute['value']) ? htmlspecialchars($attribute['value']) : '' ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Stock</label>
                        <input type="number" name="stock" class="form-control" 
                               value="<?= isset($attribute['stock']) ? $attribute['stock'] : '' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Price Modifier</label>
                        <input type="number" step="0.01" name="price_modifier" class="form-control" 
                               value="<?= isset($attribute['price_modifier']) ? $attribute['price_modifier'] : '0.00' ?>">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> <?= isset($_GET['edit']) ? 'Update' : 'Create' ?> Attribute
                </button>
                
                <?php if (isset($_GET['edit'])): ?>
                    <a href="?page=attributes" class="btn btn-outline">Cancel</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h3>Existing Attributes</h3>
        </div>
        <div class="card-body">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Product</th>
                        <th>Type</th>
                        <th>Value</th>
                        <th>Stock</th>
                        <th>Price Modifier</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($attributes->num_rows > 0): ?>
                        <?php 
                        $attributes->data_seek(0); // Reset pointer
                        while ($attr = $attributes->fetch_assoc()): ?>
                            <tr>
                                <td><?= $attr['attribute_id'] ?></td>
                                <td><?= htmlspecialchars($attr['product_name']) ?></td>
                                <td><?= ucfirst($attr['attribute_type']) ?></td>
                                <td><?= htmlspecialchars($attr['value']) ?></td>
                                <td><?= $attr['stock'] ?? 'N/A' ?></td>
                                <td><?= number_format($attr['price_modifier'], 2) ?></td>
                                <td class="action-buttons">
                                    <a href="?page=attributes&edit=<?= $attr['attribute_id'] ?>" class="btn btn-warning btn-sm">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="?page=attributes&delete=<?= $attr['attribute_id'] ?>" class="btn btn-danger btn-sm" 
                                       onclick="return confirm('Are you sure you want to delete this attribute?')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">No attributes found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>