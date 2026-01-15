<?php
session_start();
include 'includes/config.php';

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get the product ID from the URL
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$product_id) {
    die("Invalid product ID.");
}

// Fetch product details
$stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $product = $result->fetch_assoc();
} else {
    die("Product not found.");
}
$stmt->close();

// Determine display price and discount
$display_price = ($product['offer_price'] > 0) ? $product['offer_price'] : $product['price'];
$discount = ($product['offer_price'] > 0 && $product['price'] > 0) ? round((($product['price'] - $product['offer_price']) / $product['price']) * 100) : 0;

// Fetch both size and color variations
$attributes_stmt = $conn->prepare("SELECT attribute_type, attribute_id, value, stock, price_modifier 
                                 FROM product_attributes 
                                 WHERE product_id = ? 
                                 AND attribute_type IN ('size', 'color')");
$attributes_stmt->bind_param("i", $product_id);
$attributes_stmt->execute();
$attributes_result = $attributes_stmt->get_result();

$available_sizes = [];
$available_colors = [];
$unavailable_sizes = [];
$unavailable_colors = [];

while ($attr = $attributes_result->fetch_assoc()) {
    if ($attr['stock'] > 0) {
        if ($attr['attribute_type'] === 'size') {
            $available_sizes[] = $attr;
        } else {
            $available_colors[] = $attr;
        }
    } else {
        if ($attr['attribute_type'] === 'size') {
            $unavailable_sizes[] = $attr['value'];
        } else {
            $unavailable_colors[] = $attr['value'];
        }
    }
}
$attributes_stmt->close();
// Determine stock status for the product
if (empty($available_sizes) && empty($available_colors)) {
    // Use product stock if no variations exist
    $total_stock = $product['stock'];
} else {
    // Use variation stock if variations exist (sum both sizes and colors)
    $size_stock = !empty($available_sizes) ? array_sum(array_column($available_sizes, 'stock')) : 0;
    $color_stock = !empty($available_colors) ? array_sum(array_column($available_colors, 'stock')) : 0;
    $total_stock = $size_stock + $color_stock;
}

// Unified stock status calculation (applies to both simple and variable products)
if ($total_stock == 0) {
    $stock_class = "out-of-stock";
    $stock_status = "Out of stock";
} elseif ($total_stock <= 5) {
    $stock_class = "low-stock";
    $stock_status = "Only $total_stock left!";
} elseif ($total_stock <= 10) {
    $stock_class = "in-stock";
    $stock_status = "$total_stock units left";
} else {
    $stock_class = "in-stock";
    $stock_status = "In stock";
}

// Fetch related products (same category, excluding current product)
$stmt = $conn->prepare("
    SELECT p.product_id, p.name, p.price, p.offer_price, p.description, p.ratings, pi.image_url 
    FROM products p 
    INNER JOIN product_images pi ON p.product_id = pi.product_id 
    WHERE p.category = ? AND p.product_id != ? 
    GROUP BY p.product_id 
    LIMIT 6
");
$stmt->bind_param("si", $product['category'], $product_id);
$stmt->execute();
$result = $stmt->get_result();
$related_products = $result->num_rows > 0 ? $result->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

/// Fetch sticky offers with product details dynamically
$sticky_offers_left = $conn->query("
    SELECT p.name, p.price, p.offer_price, so.image_url 
    FROM sticky_offers AS so 
    INNER JOIN products AS p ON p.product_id = so.product_id 
    WHERE so.position = 'left' AND so.active = 1 
    LIMIT 2
");

$sticky_offers_right = $conn->query("
    SELECT p.name, p.price, p.offer_price, so.image_url 
    FROM sticky_offers AS so 
    INNER JOIN products AS p ON p.product_id = so.product_id 
    WHERE so.position = 'right' AND so.active = 1 
    LIMIT 2
");
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($product['name']) ? htmlspecialchars($product['name']) . " - JOEMAKEIT" : "JOEMAKEIT - Product Details"; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/swiper/swiper-bundle.min.css">
    <link rel="stylesheet" href="STYLE.CSS">
    
</head>
<body>
    <div id="toast-container"></div>   
    <section id="header">
        <div class="logo">
            <div class="logo-icon">
                <i class="fas fa-box"></i>
            </div>
            <div class="logo-text">JOE<span>MAKEIT</span></div>
        </div>
        <div>
            <ul id="navbar">
                <li><a href="index.php">Home</a></li>
                <li><a class="active" href="hop.php">Shop</a></li>
                <li><a href="contact.html">Help Center</a></li>
                <?php include 'account-component.php'; ?>
                <li id="bag">
                    <a href="cart.php">
                        <i class="fa fa-shopping-cart cart"></i>
                        <span id="cart-count"><?php echo isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'quantity')) : 0; ?></span>
                    </a>
                </li>
                <a id="clese" href="#"><i class="fa fa-times"></i></a>
            </ul>
            <div id="mobile">
                <a href="cart.php">
                    <i class="fa fa-shopping-cart cart"></i>
                    <span id="cart-count-mobile"><?php echo isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'quantity')) : 0; ?></span>
                </a>
                <i id="bar" class="fas fa-outdent"></i>
            </div>
        </div>
    </section>

    <section id="pages-header" class="about-header">
        <div class="sticky-offer-group offer-right">
            <?php if ($sticky_offers_right->num_rows > 0): ?>
                <?php while ($sticky_offer = $sticky_offers_right->fetch_assoc()): ?>
                    <div class="sticky-offer">
                        <div class="offer-badge">
                            <span class="discount-tag">🔥Limited Offers!</span>
                            <img src="<?= htmlspecialchars($sticky_offer['image_url']) ?>" alt="<?= htmlspecialchars($sticky_offer['name']) ?>">
                            <div class="offer-details">
                                <h4><?= htmlspecialchars($sticky_offer['name']) ?></h4>
                                <div class="price-container">
                                    <span class="original-price">Ksh <?= number_format($sticky_offer['price'], 2) ?></span>
                                    <span class="offer-price">Ksh <?= number_format($sticky_offer['offer_price'], 2) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
        
        <div class="offers-carousel">
            <?php
            $offers = $conn->query("
                SELECT p.product_id, p.name, p.price, p.offer_price, pi.image_url 
                FROM products AS p
                INNER JOIN product_images AS pi ON p.product_id = pi.product_id 
                WHERE p.on_offer = 1 
                GROUP BY p.product_id 
                LIMIT 5
            ");
            if ($offers->num_rows > 0): ?>
            <div class="swiper-container">
                <div class="swiper-wrapper">
                    <?php while ($offer = $offers->fetch_assoc()): ?>
                    <div class="swiper-slide">
                        <div class="offer-badge">
                            <span class="discount-tag">🔥Limited Offers!</span>
                            <img src="<?= htmlspecialchars($offer['image_url']) ?>" alt="<?= htmlspecialchars($offer['name']) ?>">
                            <div class="offer-details">
                                <h4><?= htmlspecialchars($offer['name']) ?></h4>
                                <div class="price-container">
                                    <span class="original-price">Ksh <?= number_format($offer['price'], 2) ?></span>
                                    <span class="offer-price">Ksh <?= number_format($offer['offer_price'], 2) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
                <div class="swiper-button-next"></div>
                <div class="swiper-button-prev"></div>
                <div class="swiper-pagination"></div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="sticky-offer-group offer-left">
            <?php if ($sticky_offers_left->num_rows > 0): ?>
                <?php while ($sticky_offer = $sticky_offers_left->fetch_assoc()): ?>
                    <div class="sticky-offer">
                        <div class="offer-badge">
                            <span class="discount-tag">🔥Limited Offers!</span>
                            <img src="<?= htmlspecialchars($sticky_offer['image_url']) ?>" alt="<?= htmlspecialchars($sticky_offer['name']) ?>">
                            <div class="offer-details">
                                <h4><?= htmlspecialchars($sticky_offer['name']) ?></h4>
                                <div class="price-container">
                                    <span class="original-price">Ksh <?= number_format($sticky_offer['price'], 2) ?></span>
                                    <span class="offer-price">Ksh <?= number_format($sticky_offer['offer_price'], 2) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </section>

    <section id="prodetails" class="section-p1">
        <div class="single-pro-image">
            <img src="<?php echo htmlspecialchars($product['image_url']); ?>" width="100%" id="Mainimg" alt="">
            <div class="small-img-group">
                <?php
                $image_sql = "SELECT image_url FROM product_images WHERE product_id = ?";
                $stmt = $conn->prepare($image_sql);
                $stmt->bind_param("i", $product_id);
                $stmt->execute();
                $image_result = $stmt->get_result();
                while ($image_row = $image_result->fetch_assoc()) {
                    echo '<div class="small-group-col"><img src="' . htmlspecialchars($image_row['image_url']) . '" width="100%" class="small-img" alt=""></div>';
                }
                $stmt->close();
                ?>
            </div>
        </div>

        <div class="single-pro-details product-info">
            <h6>Home / <?php echo htmlspecialchars($product['category'] ?? ''); ?></h6>
            <h4><?php echo htmlspecialchars($product['name']); ?></h4>
            <div>
                <span class="price">KSh <?php echo number_format($display_price); ?></span>
                <?php if ($discount > 0): ?>
                    <span class="original-price">KSh <?php echo number_format($product['price']); ?></span>
                    <span class="discount"><?php echo $discount; ?>% off</span>
                <?php endif; ?>
            </div>
            <p class="stock <?php echo $stock_class; ?>"><?php echo $stock_status; ?></p>
            <div class="ratings">
                <?php
                $rating = $product['ratings'];
                $fullStars = floor($rating);
                $halfStar = ($rating - $fullStars) >= 0.5 ? 1 : 0;
                $emptyStars = 5 - $fullStars - $halfStar;
                echo str_repeat('<i class="fas fa-star"></i>', $fullStars) .
                     ($halfStar ? '<i class="fas fa-star-half-alt"></i>' : '') .
                     str_repeat('<i class="far fa-star"></i>', $emptyStars);
                ?>
                <span><?php echo number_format($product['ratings'], 1); ?> </span>
            </div>
            
            <!-- Size Variations -->
            <?php if (!empty($available_sizes)): ?>
                <h5>Size Variation</h5>
                <a href="#" class="size-guide" onclick="openSizeGuideModal()">Size Guide</a>
                <div class="size-buttons">
                    <?php foreach ($available_sizes as $size): ?>
                        <button data-type="size" data-value="<?php echo htmlspecialchars($size['value']); ?>" 
                                data-price="<?php echo $display_price + $size['price_modifier']; ?>" 
                                onclick="selectVariation('size', '<?php echo htmlspecialchars($size['value']); ?>')"
                                aria-label="Select size EU <?php echo htmlspecialchars($size['value']); ?>"
                                class="variation-btn">
                            EU <?php echo htmlspecialchars($size['value']); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- Color Variations -->
            <?php if (!empty($available_colors)): ?>
                <h5>Color Variation</h5>
                <div class="color-buttons">
                    <?php foreach ($available_colors as $color): ?>
                        <button data-type="color" data-value="<?php echo htmlspecialchars($color['value']); ?>" 
                                data-price="<?php echo $display_price + $color['price_modifier']; ?>" 
                                onclick="selectVariation('color', '<?php echo htmlspecialchars($color['value']); ?>')"
                                aria-label="Select color <?php echo htmlspecialchars($color['value']); ?>"
                                class="variation-btn color-btn">
                            <span class="color-swatch" style="background-color: <?php echo htmlspecialchars($color['value']); ?>;"></span>
                            <?php echo htmlspecialchars($color['value']); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (empty($available_sizes) && empty($available_colors)): ?>
              <form id="simple-product-form" method="POST" action="add_to_cart_simple.php">
                    <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <button type="submit" class="add-to-cart">Add to Cart</button>
                </form>
            <?php else: ?>
                <button class="add-to-cart" onclick="openVariationModal()">Add to Cart</button>
            <?php endif; ?>
            
            <h4>Product Details</h4>
            <span><?php echo htmlspecialchars($product['description']); ?></span>
        </div>
    </section>

    <!-- Variation Modal -->
    <div id="variation-modal" class="modal" role="dialog" aria-labelledby="variation-modal-title">
        <div class="modal-content">
            <h4 id="variation-modal-title">Please select quantities</h4>
            <form id="add-to-cart-form" action="add_to_cart.php" method="POST">
                <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                <input type="hidden" name="product_name" value="<?php echo htmlspecialchars($product['name']); ?>">
                <input type="hidden" name="product_image" value="<?php echo htmlspecialchars($product['image_url']); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <!-- Size Variations Section -->
                <?php if (!empty($available_sizes)): ?>
                    <div class="variation-section">
                        <h5>Sizes</h5>
                        <?php foreach ($available_sizes as $size): 
                            $size_discounted_price = $display_price + $size['price_modifier'];
                            $size_original_price = $product['price'] + $size['price_modifier'];
                            $size_discount = ($product['offer_price'] > 0 && $size_original_price > 0) ? round((($size_original_price - $size_discounted_price) / $size_original_price) * 100) : 0;
                            $size_stock_status = $size['stock'] <= 5 ? "Few units left" : "{$size['stock']} units left";
                        ?>
                            <div class="variation-row">
                                <div>
                                    <p class="size-label">EU <?php echo htmlspecialchars($size['value']); ?></p>
                                    <p>
                                        <span class="price">KSh <?php echo number_format($size_discounted_price); ?></span>
                                        <?php if ($size_discount > 0): ?>
                                            <span class="original-price">KSh <?php echo number_format($size_original_price); ?></span>
                                            <span class="discount"><?php echo $size_discount; ?>% off</span>
                                        <?php endif; ?>
                                    </p>
                                    <p class="stock"><?php echo $size_stock_status; ?></p>
                                </div>
                                <div class="quantity-selector">
                                    <button type="button" class="qty-btn" onclick="adjustQuantity('size', '<?php echo htmlspecialchars($size['value']); ?>', -1)">-</button>
                                    <input type="number" name="quantity[size][<?php echo htmlspecialchars($size['value']); ?>]" 
                                           id="quantity-size-<?php echo htmlspecialchars($size['value']); ?>" 
                                           value="0" min="0" max="<?php echo $size['stock']; ?>" 
                                           data-price="<?php echo $size_discounted_price; ?>" 
                                           onchange="updateStickyPrice()">
                                    <button type="button" class="qty-btn" onclick="adjustQuantity('size', '<?php echo htmlspecialchars($size['value']); ?>', 1, <?php echo $size['stock']; ?>)">+</button>
                                    <input type="hidden" name="price_modifier[size][<?php echo htmlspecialchars($size['value']); ?>]" value="<?php echo $size['price_modifier']; ?>">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Color Variations Section -->
                <?php if (!empty($available_colors)): ?>
                    <div class="variation-section">
                        <h5>Colors</h5>
                        <?php foreach ($available_colors as $color): 
                            $color_discounted_price = $display_price + $color['price_modifier'];
                            $color_original_price = $product['price'] + $color['price_modifier'];
                            $color_discount = ($product['offer_price'] > 0 && $color_original_price > 0) ? round((($color_original_price - $color_discounted_price) / $color_original_price) * 100) : 0;
                            $color_stock_status = $color['stock'] <= 5 ? "Few units left" : "{$color['stock']} units left";
                        ?>
                            <div class="variation-row">
                                <div>
                                    <p class="color-label">
                                        <span class="color-swatch" style="background-color: <?php echo htmlspecialchars($color['value']); ?>;"></span>
                                        <?php echo htmlspecialchars($color['value']); ?>
                                    </p>
                                    <p>
                                        <span class="price">KSh <?php echo number_format($color_discounted_price); ?></span>
                                        <?php if ($color_discount > 0): ?>
                                            <span class="original-price">KSh <?php echo number_format($color_original_price); ?></span>
                                            <span class="discount"><?php echo $color_discount; ?>% off</span>
                                        <?php endif; ?>
                                    </p>
                                    <p class="stock"><?php echo $color_stock_status; ?></p>
                                </div>
                                <div class="quantity-selector">
                                    <button type="button" class="qty-btn" onclick="adjustQuantity('color', '<?php echo htmlspecialchars($color['value']); ?>', -1)">-</button>
                                    <input type="number" name="quantity[color][<?php echo htmlspecialchars($color['value']); ?>]" 
                                           id="quantity-color-<?php echo htmlspecialchars($color['value']); ?>" 
                                           value="0" min="0" max="<?php echo $color['stock']; ?>" 
                                           data-price="<?php echo $color_discounted_price; ?>" 
                                           onchange="updateStickyPrice()">
                                    <button type="button" class="qty-btn" onclick="adjustQuantity('color', '<?php echo htmlspecialchars($color['value']); ?>', 1, <?php echo $color['stock']; ?>)">+</button>
                                    <input type="hidden" name="price_modifier[color][<?php echo htmlspecialchars($color['value']); ?>]" value="<?php echo $color['price_modifier']; ?>">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($unavailable_sizes) || !empty($unavailable_colors)): ?>
                    <p>Currently unavailable: 
                        <?php 
                        $unavailable = [];
                        if (!empty($unavailable_sizes)) $unavailable[] = 'EU ' . implode(', EU ', $unavailable_sizes);
                        if (!empty($unavailable_colors)) $unavailable[] = 'Colors: ' . implode(', ', $unavailable_colors);
                        echo htmlspecialchars(implode('; ', $unavailable)); 
                        ?>
                    </p>
                <?php endif; ?>
                
                <div class="modal-actions">
                    <button type="button" class="continue" onclick="closeVariationModal()">Continue Shopping</button>
                    <button type="submit" class="go-to-cart">Add to Cart</button>
                </div>
            </form>
        </div>
    </div>

        <!-- Size Guide Modal -->
    <div id="size-guide-modal" class="modal" role="dialog" aria-labelledby="size-guide-modal-title">
        <div class="modal-content">
            <h4 id="size-guide-modal-title">Women's Shoes Size Conversion</h4>
            <table class="size-guide-table">
                <thead>
                    <tr>
                        <th>Euro Size</th>
                        <th>US Type</th>
                        <th>US Size</th>
                        <th>UK Size</th>
                        <th>China Size</th>
                        <th>Foot Length (cm)</th>
                        <th>Foot Length Range (cm)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>35</td><td>Adult Women</td><td>4</td><td>2</td><td>34</td><td>20.8</td><td>20.6 - 21.0</td></tr>
                    <tr><td>35.5</td><td>Adult Women</td><td>4.5</td><td>2.5</td><td>35</td><td>21.3</td><td>21.1 - 21.5</td></tr>
                    <tr><td>36</td><td>Adult Women</td><td>5</td><td>3</td><td>35.5</td><td>21.6</td><td>21.4 - 21.8</td></tr>
                    <tr><td>36.5</td><td>Adult Women</td><td>5.5</td><td>3.5</td><td>36</td><td>22</td><td>21.8 - 22.2</td></tr>
                    <tr><td>37</td><td>Adult Women</td><td>6</td><td>4</td><td>37</td><td>22.5</td><td>22.3 - 22.7</td></tr>
                    <tr><td>37.5</td><td>Adult Women</td><td>6.5</td><td>4.5</td><td>37.5</td><td>23</td><td>22.8 - 23.2</td></tr>
                    <tr><td>38</td><td>Adult Women</td><td>7</td><td>5</td><td>38</td><td>23.5</td><td>23.3 - 23.7</td></tr>
                    <tr><td>38.5</td><td>Adult Women</td><td>7.5</td><td>5.5</td><td>38.5</td><td>23.8</td><td>23.6 - 24.0</td></tr>
                    <tr><td>39</td><td>Adult Women</td><td>8</td><td>6</td><td>39</td><td>24.1</td><td>23.9 - 24.3</td></tr>
                    <tr><td>39.5</td><td>Adult Women</td><td>8.5</td><td>6.5</td><td>39.5</td><td>24.6</td><td>24.4 - 24.8</td></tr>
                    <tr><td>40</td><td>Adult Women</td><td>9</td><td>7</td><td>40</td><td>25.1</td><td>24.9 - 25.3</td></tr>
                    <tr><td>40.5</td><td>Adult Women</td><td>9.5</td><td>7.5</td><td>40.5</td><td>25.4</td><td>25.2 - 25.6</td></tr>
                    <tr><td>41</td><td>Adult Women</td><td>10</td><td>8</td><td>41</td><td>25.9</td><td>25.7 - 26.1</td></tr>
                    <tr><td>41.5</td><td>Adult Women</td><td>10.5</td><td>8.5</td><td>41.5</td><td>26.2</td><td>26.0 - 26.4</td></tr>
                    <tr><td>42</td><td>Adult Women</td><td>11</td><td>9</td><td>42</td><td>26.7</td><td>26.5 - 26.9</td></tr>
                    <tr><td>42.5</td><td>Adult Women</td><td>11.5</td><td>9.5</td><td>42.5</td><td>27</td><td>26.8 - 27.2</td></tr>
                    <tr><td>43</td><td>Adult Women</td><td>12</td><td>10</td><td>43</td><td>27.6</td><td>27.4 - 27.8</td></tr>
                    <tr><td>43.5</td><td>Adult Women</td><td>12.5</td><td>10.5</td><td>43.5</td><td>27.9</td><td>27.7 - 28.1</td></tr>
                    <tr><td>44</td><td>Adult Women</td><td>13</td><td>11</td><td>44</td><td>28.3</td><td>28.1 - 28.5</td></tr>
                </tbody>
            </table>
            <div class="size-guide-instructions">
                <h5>HOW TO MEASURE</h5>
                <p>To measure your feet stand on a level floor with the back of your heels against a straight edge or wall</p>
                <ol>
                    <li><strong>FOOT LENGTH</strong><br>
                        Measure your foot length by placing a ruler flat on the floor straight alongside the inside of your foot from your heel to your toes.<br>
                        Place an object with a flat edge straight across your toes with the edge touching the tip of your longest toe. Take the measurement (in millimeters) from the ruler where the flat edge crosses. This is your foot length measurement.
                    </li>
                    <li><strong>SELECTING A SHOE SIZE</strong><br>
                        If your foot measurement is halfway between sizes, select the larger size.<br>
                        You may find one foot is longer than the other, this is quite normal, please use the larger size when making your shoe size selection.
                    </li>
                </ol>
                <p>Women's footwear conversion & measurement chart - how to choose the right size footwear.</p>
            </div>
            <button class="close-btn" onclick="closeSizeGuideModal()">Close</button>
        </div>
    </div>

    <section id="product2" class="section-p1">
        <h2>Related Products</h2>
        <p class="blinking-text">Explore Similar Items!</p>
        <div class="pro-container">
            <?php if (!empty($related_products)): ?>
                <?php foreach ($related_products as $related_product): 
                    $related_display_price = ($related_product['offer_price'] > 0) ? $related_product['offer_price'] : $related_product['price'];
                    $related_discount = ($related_product['offer_price'] > 0 && $related_product['price'] > 0) ? round((($related_product['price'] - $related_product['offer_price']) / $related_product['price']) * 100) : 0;
                    $rating = $related_product['ratings'];
                    $fullStars = floor($rating);
                    $halfStar = ($rating - $fullStars) >= 0.5 ? 1 : 0;
                    $emptyStars = 5 - $fullStars - $halfStar;
                    $starHtml = str_repeat('<i class="fas fa-star"></i>', $fullStars) .
                                ($halfStar ? '<i class="fas fa-star-half-alt"></i>' : '') .
                                str_repeat('<i class="far fa-star"></i>', $emptyStars);
                ?>
                <div class="pro" onclick="window.location.href='sproduct.php?id=<?php echo htmlspecialchars($related_product['product_id']); ?>'">
                    <img src="<?php echo htmlspecialchars($related_product['image_url']); ?>" alt="<?php echo htmlspecialchars($related_product['name']); ?>">
                    <div class="des">
                        <span><?php echo htmlspecialchars($related_product['name']); ?></span>
                        <h5><?php echo htmlspecialchars($related_product['description']); ?></h5>
                        <div class="star">
                            <?php echo $starHtml; ?>
                            <h9>(<?php echo number_format($related_product['ratings'], 1); ?>)</h9>
                        </div>
                        <h4>KSh <?php echo number_format($related_display_price, 2); ?></h4>
                        <?php if ($related_discount > 0): ?>
                            <span class="original-price">KSh <?php echo number_format($related_product['price'], 2); ?></span>
                            <span class="discount"><?php echo $related_discount; ?>% off</span>
                        <?php endif; ?>
                    </div>
                    <a href="#"><i class="fa fa-shopping-cart cart"></i></a>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No Related Products Found.</p>
            <?php endif; ?>
        </div>
    </section>

 <?php include 'footer.php'?>
    <div class="mobile-sticky-cart">
        <button onclick="openVariationModal()">
            <i class="fas fa-shopping-cart"></i>
            Add to Cart - KSh <span id="sticky-price" aria-live="polite"><?php echo number_format($display_price, 2); ?></span>
        </button>
    </div>
    <script>
 document.getElementById('simple-product-form')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const form = this;
    const formData = new FormData(form);
    const submitButton = form.querySelector('.add-to-cart');
    const originalText = submitButton.innerHTML;
    
    // Show loading state
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
    submitButton.disabled = true;
    
    fetch(form.action, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update cart count
            document.getElementById('cart-count').textContent = data.cart_count;
            document.getElementById('cart-count-mobile').textContent = data.cart_count;
            
            // Show success toast
            showToast('Item(s) added to cart successfully!');
            
            // Redirect to cart after delay
            setTimeout(() => {
                window.location.href = 'cart.php';
            }, 1500);
        } else {
            showToast('Error: ' + (data.message || 'Unknown error'), true);
        }
    })
    .catch(error => {
        showToast('An error occurred. Please try again.', true);
    })
    .finally(() => {
        // Restore button state
        submitButton.innerHTML = originalText;
        submitButton.disabled = false;
    });
});</script>
        
    <script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="SCRIPT.JS"></script>
    <script src="wiper.js"></script>
</body>
</html>
<?php $conn->close(); ?>