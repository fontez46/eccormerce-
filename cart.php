<?php
session_start();
require 'includes/config.php';

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch shipping rates from the database
$shipping_rates = [];
$regions = [];
$sql = "SELECT region, town, rate FROM shipping_rates ORDER BY region, town";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $shipping_rates[$row['region']][$row['town']] = (float) $row['rate'];
        $regions[$row['region']][] = $row['town'];
    }
}

// Fetch saved addresses for logged-in user
$saved_addresses = [];
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT * FROM addresses WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $saved_addresses[] = $row;
    }
    $stmt->close();
}

// Initialize calculations
$subtotal = 0;
$shipping_fee = 0;
$total = 0;
$selected_region = $_SESSION['delivery_region'] ?? '';
$selected_town = $_SESSION['delivery_town'] ?? '';

// Calculate subtotal
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $subtotal += ($item['price'] ?? 0) * ($item['quantity'] ?? 0);
    }
}

// Calculate shipping
if (!empty($selected_region) && !empty($selected_town) && isset($shipping_rates[$selected_region][$selected_town])) {
    $shipping_fee = $shipping_rates[$selected_region][$selected_town];
}

// Calculate total
$total = $subtotal + $shipping_fee;

// Calculate initial cart quantity
$cart_quantity = 0;
if (!empty($_SESSION['cart'])) {
    $cart_quantity = array_sum(array_column($_SESSION['cart'], 'quantity'));
}

// Function to get product stock
function getProductStock($product_id, $attribute_type = null, $attribute_value = null) {
    global $conn;
    
    if ($attribute_type && $attribute_value) {
        $stmt = $conn->prepare("SELECT stock FROM product_attributes 
                               WHERE product_id = ? 
                               AND attribute_type = ? 
                               AND value = ?");
        $stmt->bind_param("iss", $product_id, $attribute_type, $attribute_value);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return $result->fetch_assoc()['stock'];
        }
    }
    
    $stmt = $conn->prepare("SELECT stock FROM products WHERE product_id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return ($result->num_rows > 0) ? $result->fetch_assoc()['stock'] : null;
}

// Fetch sticky offers with product details dynamically
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - JOEMAKEIT</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/swiper/swiper-bundle.min.css">
    <link rel="stylesheet" href="style.css"> 
    <style>
        .proceed-btn {
    background: #088178;
    color: white;
    border: none;
    border-radius: 25px;
    padding: 15px 30px;
    font-size: 16px;
    font-weight: 600;
    text-transform: uppercase;
    cursor: pointer;
    transition: all 0.3s ease;
    display: block;
    margin: 20px auto;
}

.proceed-btn:hover {
    background: #0a5f55;
    transform: scale(1.05);
}

@media (max-width: 477px) {
    .proceed-btn {
        padding: 12px 20px;
        font-size: 14px;
    }
}

@media (max-width: 320px) {
    .proceed-btn {
        padding: 10px 15px;
        font-size: 12px;
    }
}
      </style>
</head>
<body>
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
                <li><a href="hop.php">Shop</a></li>
                <li><a href="about.html">About Us</a></li>
                <li><a href="contact.html">Help Center</a></li>
                <?php include 'account-component.php'; ?>
                <li id="bag">
                    <a href="cart.php">
                        <i class="fa fa-shopping-cart cart"></i>
                        <span id="cart-count"><?= $cart_quantity ?></span>
                    </a>
                </li>
                <a id="clese" href="#"><i class="fa fa-times"></i></a>
            </ul>
            <div id="mobile">
                <a href="cart.php">
                    <i class="fa fa-shopping-cart cart"></i>
                    <span id="cart-count-mobile"><?= $cart_quantity ?></span>
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

    <section id="cart" class="section-p1">
        <?php if (empty($_SESSION['cart'])): ?>
            <div class="empty-cart">
                <h3>🛒 Your Cart is Empty</h3>
                <a href="hop.php" class="btn">Continue Shopping</a>
            </div>
        <?php else: ?>
             <div class="cart-table-container">
            <table class="cart-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <th>Subtotal</th>
                        <th>Remove</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($_SESSION['cart'] as $index => $item): 
                        $stock = getProductStock($item['product_id'], $item['attribute_type'] ?? null, $item['value'] ?? null);
                        $max_quantity = $stock ?? 100;
                    ?>
                    <tr id="cart-item-<?= $index ?>">
                        <td class="product-details">
                            <img src="<?= htmlspecialchars($item['image_url']) ?>" 
                                 alt="<?= htmlspecialchars($item['name']) ?>" 
                                 class="product-image">
                            <span class="product-name"><?= htmlspecialchars($item['name']) ?></span>
                            
                            <?php if (isset($item['attribute_type']) && $item['attribute_type'] === 'size'): ?>
                                <span class="product-size">Size: <?= htmlspecialchars($item['value']) ?></span>
                            <?php endif; ?>
                            
                            <?php if (isset($item['attribute_type']) && $item['attribute_type'] === 'color'): ?>
                                <div class="product-color">
                                    <span>Color: </span>
                                    <span class="color-swatch" 
                                          style="background-color: <?= htmlspecialchars($item['value']) ?>;"></span>
                                    <span><?= htmlspecialchars($item['value']) ?></span>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="item-price" data-price="<?= $item['price'] ?>">Ksh <?= number_format($item['price'], 0) ?></td>
                        <td class="quantity-column">
                            <div class="quantity-control">
                                <button class="quantity-btn" 
                                        onclick="updateQuantity(<?= $index ?>, -1)"
                                        <?= ($item['quantity'] <= 1) ? 'disabled' : '' ?>>➖</button>
                                <input type="number" 
                                       class="quantity-input" 
                                       id="quantity-<?= $index ?>" 
                                       value="<?= $item['quantity'] ?>" 
                                       min="1" 
                                       max="<?= $max_quantity ?>"
                                       data-max="<?= $max_quantity ?>"
                                       onchange="updateQuantity(<?= $index ?>, 0)">
                                <button class="quantity-btn" 
                                        onclick="updateQuantity(<?= $index ?>, 1)"
                                        <?= ($item['quantity'] >= $max_quantity) ? 'disabled' : '' ?>>➕</button>
                            </div>
                        </td>
                        <td class="item-subtotal" id="subtotal-<?= $index ?>">Ksh <?= number_format($item['price'] * $item['quantity'], 0) ?></td>
                        <td class="remove-column">
                            <button class="remove-btn" onclick="removeFromCart(<?= $index ?>)">❌</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
                            </div>
  <div class="cart-totals">
                <div class="address-section">
                    <h3>📍 Delivery Address</h3>
                    
                    <?php if (!empty($saved_addresses) && isset($_SESSION['user_id'])): ?>
                        <div class="saved-addresses">
                            <label for="savedAddress">Select Saved Address:</label>
                            <select id="savedAddress" onchange="fillAddressForm(this)">
                                <option value="">Use a new address</option>
                                <?php foreach ($saved_addresses as $address): ?>
                                    <option value="<?= $address['id'] ?>" 
                                        data-fullname="<?= htmlspecialchars($address['Full_Name']) ?>"
                                        data-phone="<?= htmlspecialchars($address['phone_number']) ?>"
                                        data-address1="<?= htmlspecialchars($address['address_line1']) ?>"
                                        data-address2="<?= htmlspecialchars($address['address_line2']) ?>"
                                        data-county="<?= htmlspecialchars($address['county']) ?>"
                                        data-town="<?= htmlspecialchars($address['Town']) ?>"
                                        data-postal="<?= htmlspecialchars($address['postal_code']) ?>">
                                        <?= htmlspecialchars($address['Full_Name'] . ' - ' . $address['address_line1'] . ', ' . $address['Town']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <form id="addressForm" class="address-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="saved_address_id" id="saved_address_id" value="">
                        
                        <input type="text" name="full_name" id="full_name" placeholder="Full Name" required>
                        
          <input type="tel" name="phone_number" id="checkout_phone_number" 
       placeholder="Phone Number (e.g., 0704003130, 0104003130, +254704003130)" 
       required
 pattern="\+254\d{8,9}|07\d{8}|01\d{8}"
        title="Enter a valid Kenyan phone number (e.g., 0704003130, 0104003130, or +254704003130)">
<div id="phone-error" class="error-message" style="display: none; color: #e74c3c;">
    Please enter a valid Kenyan phone number (e.g., 0704003130, 0104003130, or +254704003130)
</div>          
                        <input type="text" name="address_line1" id="address_line1" placeholder="Address Line 1" required>
                        <input type="text" name="address_line2" id="address_line2" placeholder="Address Line 2 (Optional)">
                        
                        <select name="county" id="county" required>
                            <option value="">Select County</option>
                            <?php foreach (array_keys($shipping_rates) as $region): ?>
                                <option value="<?= htmlspecialchars($region) ?>" <?= $selected_region === $region ? 'selected' : '' ?>>
                                    <?= ucwords(str_replace('_', ' ', $region)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select name="town" id="town" required>
                            <option value="">Select Town</option>
                            <?php if (!empty($selected_region) && isset($regions[$selected_region])): ?>
                                <?php foreach ($regions[$selected_region] as $town): ?>
                                    <option value="<?= htmlspecialchars($town) ?>" <?= $selected_town === $town ? 'selected' : '' ?>>
                                        <?= ucwords(str_replace('_', ' ', $town)) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        
                        <input type="text" 
       name="postal_code" 
       id="postal_code" 
       placeholder="Postal Code (e.g., 00100)" 
       required
       pattern="[0-9]{3,10}"
       title="Enter a valid postal code (3-10 digits)">
<div id="postal-error" class="error-message" style="display: none;">
    Postal code must be 3-10 digits
</div>
                    </form>
                </div>

                <div class="total-summary">
                    <h3>💰 Order Summary</h3>
                    <table>
                        <tr>
                            <td>Subtotal:</td>
                            <td id="subtotal-amount">Ksh <?= number_format($subtotal, 2) ?></td>
                        </tr>
                        <tr>
                            <td>Shipping:</td>
                            <td id="shipping-cost">Ksh <?= number_format($shipping_fee, 2) ?></td>
                        </tr>
                        <tr class="total-row">
                            <td><strong>Total:</strong></td>
                            <td id="total-amount"><strong>Ksh <?= number_format($total, 2) ?></strong></td>
                        </tr>
                    </table>
                    <button class="checkout-btn" onclick="proceedToCheckout()">Proceed to Checkout</button>
                </div>
            </div>
        <?php endif; ?>
    </section>
<script>// Simply call the unified function with modal=true parameter
document.getElementById('checkout-btn').addEventListener('click', function(e) {
    e.preventDefault();
    proceedToCheckout(true); // true for the modal approach
}); </script>
    
<?php include 'footer.php'?>
    <script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="script.js"></script>
    <script src="wiper.js"></script>
</body>
</html>
<?php $conn->close(); ?> 