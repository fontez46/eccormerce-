<?php
session_start();
// Clear only authentication-related session data
unset($_SESSION['form_data']);
unset($_SESSION['errors']);
unset($_SESSION['show_signup_modal']);
exit();
?>
<!DOCTYPE html>
<html lang="en">
  <Head>
    <meta charset="UTF-8">
    <META HTTP="x-UA-Compatible" content="IE=edge">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title> JOEMAKEIT</title>
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" >
      <link rel="stylesheet" href="STYLE.CSS"> 
  </Head>
  <body>
    <section id="header">
      <a href="#"><img src="IMG/logo.JPG" class="logo"alt=""></a>
      <div>
        <ul id="navbar">
          <li ><a href="INDEX.php">Home</a></li>
          <li><a href="HOP.php">Shop</a></li>
                    <li><a class="active"href="Contact.html">Contact</a></li>
          <li id="bag"><a href="CART.php"><i class="fa fa-shopping-cart cart"></i>
           <span id="cart-count">0</span>
          </a></li>
          
          <a  id="clese" href="#"> <i class=" fa fa-times"></i></a>
        </ul>
      </div>
      <div id="mobile">
        <a href="cart.html"><i class="fa fa-shopping-cart cart"></i></a>
        <i id="bar" class="fas fa-outdent"> </i>

      </div>
      </section>
      
    <section  id="page-header" class="about-header">
       <h2>#KnowUs</h2>
    <p>We're committed to providing our customers with the best possible shopping experience</p>
    </section>
    <section id="contact-details" class="section-p1">
      <div class="details">
        <span>GET IN TOUCH</span>
        <H2> Visit one of Branch or contact us today</H2>
        <h3>Location</h1>
        <div>
          <li>
            <i  class="fa fa-map"></i>
            <p> Thika Road, Street 32, Kahawa West</p>
          </li>
        </div>
        <div>
          <li>
            <i  class="fa fa-envelope"></i>
            <p> contact@joemakeit@gmail.com</p>
          </li>
        </div>
        <div>
          <li>
            <i  class="fas fa-phone-alt"></i>
            <p> contact@joemakeit.com/+254104003130</p>
          </li>
        </div>
        <div>
          <li>
            <i  class="fa fa-clock"></i>
            <p> Monday To Saturday 8:00am to 5:00pm</p>
          </li>
        </div>
      </div>
      <div class="map">
        <iframe src="https://www.google.com/maps/embed?pb=!1m14!1m12!1m3!1d15955.872045914026!2d36.900044799999996!3d-1.1829247999999999!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!5e0!3m2!1sen!2ske!4v1734779619046!5m2!1sen!2ske"
         width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
      </div>

    </section>
    <section id="form-details">
      <form action="">
        <span> LEAVE A MESSAGE</span>
        <H2> We love to Hear from you</H2>
        <input type="text" placeholder="Your Name">
        <input type="text" placeholder="Email">
        <input type="text" placeholder="Subject">
        <textarea name="" id="" cols="30" rows="10" placeholder="Your Message"></textarea>
        <button type="submit" class="normal">Submit</button>

      </form>
      <div class="people">
        <div>
        <img src="IMG/people5.jpg">
        <p><span>James .N .Kamau</span> Senior Marketing Manager<br> Phone +2547 85 665 248<br> Email: jimmyNkamau907@gmail.com</p>
        </div>
        <div>
          <img src="IMG/people3.jpg">
          <p><span>Angeline Mwangi</span>  SEO Specialist: <br> Phone +2547 45 654 328<br> Email: Angelinemwangi@gmail.com</p>
          </div>
          <div>
            <img src="IMG/people4.jpg">
            <p><span>Esther Onyango</span> PPC Specialist<br> Phone +2547 78 456 832<br> Email: estheronyango@gmail.com</p>
            </div>
      </div>

    </section>
 
    <section id="newsletter" class="section-p1 section-ml">
      <div class="newstext">
        <h4>Sign up For News Letter</h4>
        <p> Get E-Mail updates about our latest shop and <span>special offer</span></p>
      </div>
      <div class="form">
        <form action="#" method="post">
        <input type="email" name="email" placeholder="Your email adress" required>
        <button type="submit" class="normal">Sign Up</button>
      </form>
      </div>

    </section>
    <footer class="section-p1">
      <div class="col">
        <img class="logo"src="IMG/logo.JPG" >
        <h4>Contact</h4>
        <p>Address: 00100 Thika Road, Street 32, Kahawa West</p>
      <p>Phone: +2541 04 003 130 /+91  94 096 686</p>
      <p>Hours: 24Hrs / 24/7</p>
      <div class="follow">
        <h4>Follow us</h4>
        <div class="icon">
          <a href="https://www.facebook.com/Jose Fontez" target="_blank" >
          <i class="fa-brands fa-facebook"></i> </a>
          <i class="fab fa-twitter"></i>
          <i class="fab fa-youtube"></i>
          <i class="fab fa-tiktok"></i>
          <i class="fab fa-instagram"></i>
          <i class="fab fa-pinterest"></i>
        </div>
      </div>
      </div>
      <div class="col">
        <h4>About</h4>
        <a href="#">About us</a>
        <a href="#">Delivery Information</a>
        <a href="#">Privacy Policy</a>
        <a href="#">Terms & Condition</a>
        <a href="#">Contact us</a>
      </div>
      <div class="col">
        <h4>My Account</h4>
        <a href="#">Sign In</a>
        <a href="#">View Cart</a>
        <a href="#">My Whishlist</a>
        <a href="#">Track My Orders</a>
        <a href="#">Help</a>
      </div>
<div class="col install">
<H4>Install App</H4>
<p>From App Store or Google Play</p>
<div class=" row">
  <img src="IMG/B9.JPG" alt="">
  <img src="IMG/b10.jpg" alt="">;
</div>
<p>Secured Payment Gateways</p>
<img src="IMG/B8.JPG" alt="">
</div>
<div class="copyright">
  <P> @ 2024 Fontez@ Joemakeit webpage</P>

</div>
       </footer>
       
    <script src="SCRIPT.JS"></script>
  </body>
  </html>