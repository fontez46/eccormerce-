<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
// Include the database connection
require 'includes/config.php';

// Fetch all products randomly
$sql = "SELECT product_id, name, price, offer_price, on_offer, image_url, ratings, description FROM products ORDER BY RAND()";
$result = $conn->query($sql);?>
<?php
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
    <title>Shop - JOEMAKEIT</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
     <link rel="stylesheet" href="https://unpkg.com/swiper/swiper-bundle.min.css">
    <link rel="stylesheet" href="style.css">
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
            <!-- Search Bar -->
            <div class="search-bar">
    <form id="search-form" action="javascript:void(0);">
        <input type="text" id="search-input" name="query" placeholder="Search for products..." required>
        <button type="submit"> <i class="fa-solid fa-magnifying-glass search-icon"></i></button> 
    </form>
    <!-- Autocomplete Suggestions -->
    <div id="suggestions" class="suggestions"></div>
    <!-- Search Results -->
    <div id="search-results" class="search-results"></div>
</div>
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

<section id="product1" class="section-p1">
    <div class="pro-container">
        <?php
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                // Ensure only the offer price is shown when available
               $price = ($row['on_offer'] == 1 && $row['offer_price'] !== null) ? $row['offer_price'] : $row['price'];
               
                // Get rating from database
                $rating = $row['ratings'];
                $fullStars = floor($rating);
                $halfStar = ($rating - $fullStars) >= 0.5 ? 1 : 0;
                $emptyStars = 5 - $fullStars - $halfStar;

                // Build star rating HTML
                $starHtml = str_repeat('<i class="fas fa-star"></i>', $fullStars);
                if ($halfStar) $starHtml .= '<i class="fas fa-star-half-alt"></i>';
                $starHtml .= str_repeat('<i class="far fa-star"></i>', $emptyStars);

                echo '
                <div class="pro" onclick="window.location.href=\'sproduct.php?id=' . $row['product_id'] . '\'">
                    <div class="image-container">
                        <img src="' . htmlspecialchars($row['image_url']) . '" alt="' . htmlspecialchars($row['name']) . '">
                    </div>
                    <div class="des">
                        <span>' . htmlspecialchars($row['name']) . '</span>
                        <h5>' . htmlspecialchars($row['description']) . '</h5>
                        <div class="star">' . $starHtml . '<h9>(' . number_format($rating, 1) . ')</h9></div>
                        <h4>
                            <span style="color: #088178; font-weight: bold;">
                                Ksh ' . number_format($price) . '
                            </span>
                      </h4>
                    </div>
                    <a href="#"><i class="fa fa-shopping-cart cart"></i></a>
                </div>';
            }
        } else {
            echo "<p>No products found.</p>";
        }
        ?>
    </div>
</section>

    <section id="pagination" class="section-pi">
        <a href="#">1</a>
        <a href="#">2</a>
        <a href="#"><i class="fa fa-long-arrow-alt-right"></i></a>
    </section>
    <?php include 'footer.php'?>
     <script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="SCRIPT.JS"></script>
    <script src="wiper.js"></script>
    
</body>
</html>

<?php
// Close the database connection
$conn->close();
?>


