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