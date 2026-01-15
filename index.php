<?php
session_start([
    'cookie_httponly' => 1,
    'cookie_secure' => isset($_SERVER['HTTPS'])
]);

// Include configuration file
require 'includes/config.php';

// Generate CSRF token if not already set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Display verification message if set
if (isset($_SESSION['verification_message'])) {
    echo "<script>showToast('" . htmlspecialchars($_SESSION['verification_message']) . "');</script>";
    unset($_SESSION['verification_message']);
}

// Fetch products using MySQLi
try {
    // Featured products
    $featured_stmt = $conn->prepare("SELECT product_id, name, price, ratings, image_url, description FROM products WHERE is_featured = 1");
    if (!$featured_stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $featured_stmt->execute();
    $featured_result = $featured_stmt->get_result();
    $featured_products = $featured_result->fetch_all(MYSQLI_ASSOC);
    $featured_stmt->close();

    // New arrivals
    $new_arrivals_stmt = $conn->prepare("SELECT product_id, name, price, ratings, image_url, description FROM products WHERE is_new_arrival = 1");
    if (!$new_arrivals_stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $new_arrivals_stmt->execute();
    $new_arrivals_result = $new_arrivals_stmt->get_result();
    $new_arrivals = $new_arrivals_result->fetch_all(MYSQLI_ASSOC);
    $new_arrivals_stmt->close();

} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    die("Internal server error. Please try again later.");
}
?>
    <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JOEMAKEIT</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="STYLE.CSS">

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
                <li><a class="active" href="index.PHP">Home</a></li>
                <li><a href="hop.PHP">Shop</a></li>
                <li><a href="Contact.html">Help Center</a></li>
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
    <section id="hero">
        <h4>Buy and Sell in offer</h4>
        <h2>Super value deals</h2>
        <h1>On all products</h1>
        <p>Save more with coupons & up to 80% off!</p>
        <a href="hop.php" class="shop-now-btn">Shop Now</a>
    </section>
        <!-- Feature boxes here -->
 <section id="feature" class="section-p1">
<div class="fe-box">
  <img src=" IMG/f1.JPG" alt="">
<h6>Free Shipping</h6>
</div>
<div class="fe-box">
  <img src=" IMG/f2.JPG" alt="">
<h6>Online Orders</h6>
</div><div class="fe-box">
  <img src=" IMG/f3.JPG" alt="">
<h6>Save  Money</h6>
</div><div class="fe-box">
  <img src=" IMG/f4.JPG" alt="">
<h6>Promotions</h6>
</div><div class="fe-box">
  <img src=" IMG/f5.JPG" alt="">
<h6>Happy Sell</h6>
</div><div class="fe-box">
  <img src=" IMG/DROP.JPG" alt="">
<h6>Drop Shipping</h6>
</div><div class="fe-box">
  <img src=" IMG/f8.JPG" alt="">
<h6>Delivery </h6>
</div><div class="fe-box">
  <img src=" IMG/ABOUT.JPG" alt="">
<h6> Contact Support</h6>
</div>
    </section>

    <!-- Featured Products -->
<section id="product2" class="section-p1">
    <h2>Featured Products</h2>
    <p class="blinking-text">Happy Holiday Collection New Modern Designs!</p>
    <section id="product1">
        <div class="pro-container">
            <?php if (!empty($featured_products)): ?>
                <?php foreach ($featured_products as $product): 
                    // Calculate star ratings
                    $rating = $product['ratings'];
                    $fullStars = floor($rating);
                    $halfStar = ($rating - $fullStars) >= 0.5 ? 1 : 0;
                    $emptyStars = 5 - $fullStars - $halfStar;
                    
                    // Build star HTML
                    $starHtml = '';
                    // Full stars
                    for ($i = 0; $i < $fullStars; $i++) {
                        $starHtml .= '<i class="fas fa-star"></i>';
                    }
                    // Half star
                    if ($halfStar) {
                        $starHtml .= '<i class="fas fa-star-half-alt"></i>';
                    }
                    // Empty stars
                    for ($i = 0; $i < $emptyStars; $i++) {
                        $starHtml .= '<i class="far fa-star"></i>';
                    }
                ?>
                <div class="pro" onclick="window.location.href='sproduct.php?id=<?= htmlspecialchars($product['product_id']) ?>'">
                    <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                    <div class="des">
                        <span><?= htmlspecialchars($product['name']) ?></span>
                        <h5><?= htmlspecialchars($product['description']) ?></h5>
                               <div class="star">
                            <?= $starHtml ?>
                            <h9>(<?= number_format($rating, 1) ?>)</h9>
                        </div>
                        <h4>Ksh <?= number_format($product['price'], ) ?></h4>
                    </div>
                    <a href="#"><i class="fa fa-shopping-cart cart"></i></a>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No featured products found.</p>
            <?php endif; ?>
        </div>
    </section>
</section>


    <!-- New Arrivals -->
<section id="product2" class="section-p1">
    <h2>New Arrivals</h2>
    <p class="blinking-text">Summer Collection With New Modern Design!</p>
    <section id="product1">
        <div class="pro-container">
            <?php if (!empty($new_arrivals)): ?>
                <?php foreach ($new_arrivals as $product): 
                    // Calculate star ratings
                    $rating = $product['ratings'];
                    $fullStars = floor($rating);
                    $halfStar = ($rating - $fullStars) >= 0.5 ? 1 : 0;
                    $emptyStars = 5 - $fullStars - $halfStar;
                    
                    // Build star HTML
                    $starHtml = '';
                    // Full stars
                    for ($i = 0; $i < $fullStars; $i++) {
                        $starHtml .= '<i class="fas fa-star"></i>';
                    }
                    // Half star
                    if ($halfStar) {
                        $starHtml .= '<i class="fas fa-star-half-alt"></i>';
                    }
                    // Empty stars
                    for ($i = 0; $i < $emptyStars; $i++) {
                        $starHtml .= '<i class="far fa-star"></i>';
                    }
                ?>
                <div class="pro" onclick="window.location.href='sproduct.php?id=<?= htmlspecialchars($product['product_id']) ?>'">
                    <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                    <div class="des">
                    <span><?= htmlspecialchars($product['name']) ?></span>
                    <h5><?= htmlspecialchars($product['description']) ?></h5>
                        <div class="star">
                            <?= $starHtml ?>
                            <h9>(<?= number_format($rating, 1) ?>)</h9>
                        </div>
                        <h4>Ksh <?= number_format($product['price'],) ?></h4>
                    </div>
                    <a href="#"><i class="fa fa-shopping-cart cart"></i></a>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No new arrivals found.</p>
            <?php endif; ?>
        </div>
    </section>
</section>
          <?php include 'footer.php'?>
          <script>
        function updateCartBadge() {
    fetch('cart-count.php')
        .then(response => response.json())
        .then(data => {
            console.log("Updating Cart Count:", data); // Debugging output
            const totalQty = data.cart_quantity || 0;

            document.querySelectorAll('#cart-count').forEach(badge => {
                badge.textContent = totalQty;
                badge.style.display = totalQty > 0 ? 'inline-block' : 'none';
            });
        })
        .catch(error => console.error("Error fetching cart count:", error));
}

document.addEventListener('DOMContentLoaded', updateCartBadge);

            </script>
                   
     <script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="SCRIPT.JS"></script>
    <script src="wiper.js"></script>

    <?php
    // Clear errors and form data after displaying
    unset($_SESSION['errors']);
    unset($_SESSION['form_data']);
    ?>
</body>
</html>












   