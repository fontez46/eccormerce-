<?php 
require_once '../admin/config.php';
// Check for flash messages
if (isset($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (isset($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}
// Initialize product-specific variables
$products = [];
$product_details = []; // Initialize as empty array
$categories = [];
$error = '';
$success = '';

// Pagination & Search Settings
$perPage = 10; // Products per page
$currentPage = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset = ($currentPage - 1) * $perPage;
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch categories
$cat_stmt = $conn->prepare("SELECT DISTINCT category FROM products");
$cat_stmt->execute();
$cat_result = $cat_stmt->get_result();
$categories = $cat_result->fetch_all(MYSQLI_ASSOC);
$cat_stmt->close();

// Handle product form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Security token validation failed";
    } else {
        $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $price = (float)$_POST['price'];
        $offer_price = (float)$_POST['offer_price'];
        $on_offer = isset($_POST['on_offer']) ? 1 : 0;
        $stock = (int)$_POST['stock'];
        $category = trim($_POST['category']);
        $image_url = trim($_POST['image_url']);
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $is_new_arrival = isset($_POST['is_new_arrival']) ? 1 : 0;
        $rating = isset($_POST['ratings']) ? (float)$_POST['ratings'] : 0.0;

        // Validate rating range
        if ($rating < 0 || $rating > 5) {
            $rating = 0.0;
        }

        if (empty($name) || empty($description) || $price <= 0) {
            $error = "Name, description, and price are required and price must be positive";
        } else {
           if ($product_id > 0) {
    $stmt = $conn->prepare("UPDATE products SET name=?, description=?, price=?, offer_price=?, on_offer=?, stock=?, category=?, image_url=?, is_featured=?, is_new_arrival=?, ratings=?, updated_at=NOW() WHERE product_id=?");
    $stmt->bind_param("ssddiissiidi", $name, $description, $price, $offer_price, $on_offer, $stock, $category, $image_url, $is_featured, $is_new_arrival, $rating, $product_id);
} else {
    $stmt = $conn->prepare("INSERT INTO products (name, description, price, offer_price, on_offer, stock, category, image_url, is_featured, is_new_arrival, ratings, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
    $stmt->bind_param("ssddiissiid", $name, $description, $price, $offer_price, $on_offer, $stock, $category, $image_url, $is_featured, $is_new_arrival, $rating);
}

            // Execute the query
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = "Product saved successfully!";
            } else {
                $_SESSION['flash_error'] = "Error saving product: " . $stmt->error;
            }
            $stmt->close();
            header("Location: admin.php?page=products");
            exit();
        }
    }
}

// Handle product deletion
if (isset($_GET['delete_product'])) {
    $product_id = (int)$_GET['delete_product'];
    if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Security token validation failed";
    } else {
        $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
        $stmt->bind_param("i", $product_id);
        if ($stmt->execute()) {
            $_SESSION['flash_success'] = "Product deleted successfully!";
        } else {
            $_SESSION['flash_error'] = "Error deleting product: " . $stmt->error;
        }
        $stmt->close();
        header("Location: admin.php?page=products");
        exit();
    }
}

// If viewing/editing a specific product
if (isset($_GET['view_product'])) {
    $product_id = (int)$_GET['view_product'];
    $stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product_details = $result->fetch_assoc() ?? []; // Initialize as empty array if no product found
    $stmt->close();
}

// Build product query with search and pagination
$product_query = "SELECT * FROM products ";
$count_query = "SELECT COUNT(*) AS total FROM products ";

if (!empty($searchTerm)) {
    $product_query .= " WHERE name LIKE ? OR description LIKE ? OR category LIKE ? ";
    $count_query .= " WHERE name LIKE ? OR description LIKE ? OR category LIKE ? ";
}

$product_query .= " ORDER BY created_at DESC LIMIT ?, ?";

// Get total products count
$count_stmt = $conn->prepare($count_query);
if (!empty($searchTerm)) {
    $searchParam = "%$searchTerm%";
    $count_stmt->bind_param("sss", $searchParam, $searchParam, $searchParam);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$totalProducts = $count_result->fetch_assoc()['total'] ?? 0;
$count_stmt->close();

// Fetch products with pagination
$product_stmt = $conn->prepare($product_query);
if (!empty($searchTerm)) {
    $searchParam = "%$searchTerm%";
    $product_stmt->bind_param("sssii", $searchParam, $searchParam, $searchParam, $offset, $perPage);
} else {
    $product_stmt->bind_param("ii", $offset, $perPage);
}
$product_stmt->execute();
$product_result = $product_stmt->get_result();
$products = $product_result->fetch_all(MYSQLI_ASSOC);
$product_stmt->close();

// Calculate total pages
$totalPages = ceil($totalProducts / $perPage);
?>

<!-- Products Dashboard -->
<div style="background: white; border-radius: 12px; box-shadow: var(--card-shadow); padding: 2rem; margin-bottom: 2rem;">
    <h1 style="font-size: 2rem; margin-bottom: 1rem; color: var(--primary);">
        <i class="fas fa-box-open"></i> Product Management
    </h1>
    <p style="font-size: 1.2rem; color: var(--gray); margin-bottom: 2rem;">
        Manage your product catalog
    </p>
    
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
    
   
    <!-- Product Form -->
    <div class="product-form-container">
        <div class="product-header">
            <div class="product-id">
                <?= !empty($product_details) && isset($product_details['product_id']) ? 'Edit Product #' . htmlspecialchars($product_details['product_id']) : 'Add New Product' ?>
            </div>
            <div>
                <a href="?page=products" class="btn btn-outline">
                    <i class="fas fa-plus"></i> Add New
                </a>
            </div>
        </div>
        
        <div class="product-form-content">
            <form method="POST" id="productForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <?php if (!empty($product_details) && isset($product_details['product_id'])): ?>
                    <input type="hidden" name="product_id" value="<?= htmlspecialchars($product_details['product_id']) ?>">
                <?php endif; ?>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-tag"></i> Product Name *</label>
                        <input 
                            type="text" 
                            name="name" 
                            class="form-control" 
                            placeholder="Enter product name"
                            value="<?= isset($product_details['name']) ? htmlspecialchars($product_details['name']) : '' ?>"
                            required
                        >
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-align-left"></i> Category *</label>
                        <input 
                            type="text" 
                            name="category" 
                            class="form-control" 
                            list="categoryList"
                            placeholder="Enter or select category"
                            value="<?= isset($product_details['category']) ? htmlspecialchars($product_details['category']) : '' ?>"
                            required
                        >
                        <datalist id="categoryList">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat['category']) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    
                    <div class="form-group form-group-full">
                        <label class="form-label"><i class="fas fa-file-alt"></i> Description *</label>
                        <textarea 
                            name="description" 
                            class="form-control" 
                            placeholder="Enter product description"
                            rows="4"
                            required
                        ><?= isset($product_details['description']) ? htmlspecialchars($product_details['description']) : '' ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-money-bill-wave"></i> Price (Ksh) *</label>
                        <input 
                            type="number" 
                            name="price" 
                            class="form-control" 
                            placeholder="0.00"
                            step="0.01"
                            min="0"
                            value="<?= isset($product_details['price']) ? htmlspecialchars($product_details['price']) : '' ?>"
                            required
                        >
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-tags"></i> Offer Price (Ksh)</label>
                        <input 
                            type="number" 
                            name="offer_price" 
                            class="form-control" 
                            placeholder="0.00"
                            step="0.01"
                            min="0"
                            value="<?= isset($product_details['offer_price']) ? htmlspecialchars($product_details['offer_price']) : '' ?>"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-cubes"></i> Stock Quantity *</label>
                        <input 
                            type="number" 
                            name="stock" 
                            class="form-control" 
                            placeholder="Enter stock quantity"
                            min="0"
                            value="<?= isset($product_details['stock']) ? htmlspecialchars($product_details['stock']) : '' ?>"
                            required
                        >
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-star"></i> Ratings (0-5)</label>
                        <input 
                            type="number" 
                            name="ratings" 
                            class="form-control" 
                            placeholder="0.0"
                            step="0.1"
                            min="0"
                            max="5"
                            value="<?= isset($product_details['ratings']) ? htmlspecialchars($product_details['ratings']) : '0.0' ?>"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-image"></i> Image URL</label>
                        <input 
                            type="text" 
                            name="image_url" 
                            id="imageUrl"
                            class="form-control" 
                            placeholder="Enter image URL"
                            value="<?= isset($product_details['image_url']) ? htmlspecialchars($product_details['image_url']) : '' ?>"
                        >
                        <img 
                            src="<?= isset($product_details['image_url']) ? htmlspecialchars($product_details['image_url']) : '' ?>" 
                            alt="Product Preview" 
                            class="image-preview"
                            id="imagePreview"
                            onerror="this.style.display='none'"
                        >
                    </div>
                     
                    <div class="form-group">
                        <div class="checkbox-group">
                            <label>
                                <input 
                                    type="checkbox" 
                                    name="on_offer" 
                                    value="1"
                                    <?= isset($product_details['on_offer']) && $product_details['on_offer'] ? 'checked' : '' ?>
                                >
                                <i class="fas fa-percentage"></i> On Offer
                            </label>
                            
                            <label>
                                <input 
                                    type="checkbox" 
                                    name="is_featured" 
                                    value="1"
                                    <?= isset($product_details['is_featured']) && $product_details['is_featured'] ? 'checked' : '' ?>
                                >
                                <i class="fas fa-star"></i> Featured
                            </label>
                            
                            <label>
                                <input 
                                    type="checkbox" 
                                    name="is_new_arrival" 
                                    value="1"
                                    <?= isset($product_details['is_new_arrival']) && $product_details['is_new_arrival'] ? 'checked' : '' ?>
                                >
                                <i class="fas fa-certificate"></i> New Arrival
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="action-buttons" style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="save_product" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Product
                    </button>
                    <a href="?page=products" class="btn btn-outline">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <?php if (!empty($product_details) && isset($product_details['product_id'])): ?>
                        <a href="?page=products&delete_product=<?= htmlspecialchars($product_details['product_id']) ?>&csrf_token=<?= htmlspecialchars($_SESSION['csrf_token']) ?>" 
                            class="btn btn-danger"
                            onclick="return confirm('Are you sure you want to delete this product?')"
                        >
                            <i class="fas fa-trash"></i> Delete Product
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
     <!-- Search Form -->
    <div class="search-container" style="margin-bottom: 1.5rem; display: flex; gap: 10px;">
        <form method="GET" action="admin.php" style="display: flex; flex-grow: 1;">
            <input type="hidden" name="page" value="products">
            <input 
                type="text" 
                name="search" 
                class="form-control" 
                placeholder="Search products by name, description or category..."
                value="<?= htmlspecialchars($searchTerm) ?>"
                style="border-radius: 4px 0 0 4px;"
            >
            <button 
                type="submit" 
                class="btn btn-primary"
                style="border-radius: 0 4px 4px 0;"
            >
                <i class="fas fa-search"></i> Search
            </button>
        </form>
        <?php if (!empty($searchTerm)): ?>
            <a href="?page=products" class="btn btn-outline">
                <i class="fas fa-times"></i> Clear Search
            </a>
        <?php endif; ?>
    </div>
    
    
    <!-- Products List -->
    <div class="products-container">
        <h2 style="margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; gap: 10px;">
            <span>
                <i class="fas fa-list"></i> Product Catalog
            </span>
            <span style="font-size: 1rem; font-weight: normal; color: var(--gray);">
                <?= $totalProducts ?> products found
                <?php if (!empty($searchTerm)): ?>
                    (Search: "<?= htmlspecialchars($searchTerm) ?>")
                <?php endif; ?>
            </span>
        </h2>
        
        <?php if (!empty($products)): ?>
            <div style="overflow-x: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>ratings</th>
                            <th>Featured</th>
                            <th>New</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td>#<?= $product['product_id'] ?></td>
                                <td>
                                    <div style="font-weight: 500;"><?= htmlspecialchars($product['name']) ?></div>
                                    <div style="font-size: 0.9rem; color: var(--gray);">
                                        <?= strlen($product['description']) > 50 ? substr(htmlspecialchars($product['description']), 0, 50) . '...' : htmlspecialchars($product['description']) ?>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($product['category']) ?></td>
                                <td>
                                    <div>Ksh <?= number_format($product['price'], 2) ?></div>
                                    <?php if ($product['on_offer'] && $product['offer_price'] > 0): ?>
                                        <div style="color: var(--success); font-size: 0.9rem;">
                                            Offer: Ksh <?= number_format($product['offer_price'], 2) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?= $product['stock'] ?></td>
                                <td>
                                    <?php if ($product['ratings'] > 0): ?>
                                        <?= number_format($product['ratings'], 1) ?>
                                        <div style="color: gold; font-size: 0.9rem;">
                                            <?= str_repeat('★', floor($product['ratings'])) ?><?= ($product['ratings'] - floor($product['ratings']) >= 0.5 ? '½' : '' ) ?>
                                        </div>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($product['is_featured']): ?>
                                        <i class="fas fa-check-circle" style="color: var(--success);"></i>
                                    <?php else: ?>
                                        <i class="fas fa-times-circle" style="color: var(--danger);"></i>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($product['is_new_arrival']): ?>
                                        <i class="fas fa-check-circle" style="color: var(--success);"></i>
                                    <?php else: ?>
                                        <i class="fas fa-times-circle" style="color: var(--danger);"></i>
                                    <?php endif; ?>
                                </td>
                                <td class="action-buttons">
                                    <a 
                                        href="?page=products&view_product=<?= $product['product_id'] ?>" 
                                        class="btn btn-outline"
                                        style="padding: 0.5rem 1rem;"
                                    >
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination" style="margin-top: 1.5rem; display: flex; justify-content: center; gap: 5px;">
                    <?php if ($currentPage > 1): ?>
                        <a 
                            href="?page=products&p=<?= $currentPage-1 ?><?= !empty($searchTerm) ? '&search='.urlencode($searchTerm) : '' ?>" 
                            class="btn btn-outline"
                        >
                            <i class="fas fa-chevron-left"></i> Prev
                        </a>
                    <?php endif; ?>
                    
                    <?php 
                    $startPage = max(1, $currentPage - 2);
                    $endPage = min($totalPages, $startPage + 4);
                    if ($endPage - $startPage < 4) {
                        $startPage = max(1, $endPage - 4);
                    }
                    ?>
                    
                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <a 
                            href="?page=products&p=<?= $i ?><?= !empty($searchTerm) ? '&search='.urlencode($searchTerm) : '' ?>" 
                            class="btn <?= $i == $currentPage ? 'btn-primary' : 'btn-outline' ?>"
                        >
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($currentPage < $totalPages): ?>
                        <a 
                            href="?page=products&p=<?= $currentPage+1 ?><?= !empty($searchTerm) ? '&search='.urlencode($searchTerm) : '' ?>" 
                            class="btn btn-outline"
                        >
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div style="text-align: center; padding: 2rem;">
                <i class="fas fa-box-open" style="font-size: 3rem; color: #ccc; margin-bottom: 1rem;"></i>
                <h3>No Products Found</h3>
                <p>Add your first product to get started</p>
                <?php if (!empty($searchTerm)): ?>
                    <a href="?page=products" class="btn btn-primary" style="margin-top: 1rem;">
                        Clear Search
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Product image preview
    const imageUrl = document.getElementById('imageUrl');
    const imagePreview = document.getElementById('imagePreview');
    
    if (imageUrl && imagePreview) {
        imageUrl.addEventListener('input', function() {
            if (this.value) {
                imagePreview.src = this.value;
                imagePreview.style.display = 'block';
            } else {
                imagePreview.style.display = 'none';
            }
        });
        
        // Initialize preview if editing product
        if (imageUrl.value) {
            imagePreview.src = imageUrl.value;
            imagePreview.style.display = 'block';
        }
    }
</script>