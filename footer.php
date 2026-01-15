<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JOEMAKEIT - Enhanced Footer</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
            position: relative;
        }      
                
        </style>
</head>
<body>
   
    <!-- Footer -->
    <div class="footer-wave">
        <svg data-name="Layer 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none">
            <path d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V0H0V27.35A600.21,600.21,0,0,0,321.39,56.44Z" class="shape-fill"></path>
        </svg>
    </div>
    
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-col">
                    <h3>JOEMAKEIT</h3>
                    <p>Your one-stop shop for quality products at affordable prices. We're committed to providing the best shopping experience.</p>
                    <div class="social-icons">
                        <a href="https://www.facebook.com/JoseFontez" target="_blank"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-pinterest"></i></a>
                        <a href="#"><i class="fab fa-youtube"></i></a>
                    </div>
                     </div>
                
                <div id="quick-links" class="footer-col">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> About Us</a></li>
                        <li><a href="HOP.php"><i class="fas fa-chevron-right"></i> Shop</a></li>
                        <li><a href="Contact.html"><i class="fas fa-chevron-right"></i> Contact Us</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Privacy Policy</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Terms & Conditions</a></li>
                    </ul>
                </div>
                
                <div id="customer-support" class="footer-col">
                    <h3>Customer Support</h3>
                    <ul>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> FAQ</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Shipping Policy</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Returns & Exchanges</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Order Tracking</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Contact Support</a></li>
                    </ul>
                </div>
                
                <div class="footer-col">
                    <h3>Contact Info</h3>
                    <p><i class="fas fa-map-marker-alt"></i> Thika Road, Street 32, Kahawa West</p>
                    <p><i class="fas fa-phone"></i> +254 104 003 130</p>
                    <p><i class="fas fa-envelope"></i> contact@joemakeit.com</p>
                    <h4>Payment Methods</h4>
                    <div class="payment-methods">
                        <i class="fab fa-cc-visa"></i>
                        <i class="fab fa-cc-mastercard"></i>
                        <i class="fab fa-cc-paypal"></i>
                        <i class="fab fa-cc-apple-pay"></i>
                    </div>
                </div>
            </div>
            <div class="copyright">
                <div class="back-to-top" onclick="window.scrollTo({top: 0, behavior: 'smooth'})">
                    <i class="fas fa-arrow-up"></i>
                </div>
<p>&copy; <?= date('Y') ?> JOEMAKEIT. All rights reserved.</p>            </div>
        </div>
    </footer>
    
    <script>
        // Animation for the back to top button
        document.addEventListener('DOMContentLoaded', function() {
            const backToTop = document.querySelector('.back-to-top');
            
            window.addEventListener('scroll', function() {
                if (window.scrollY > 300) {
                    backToTop.style.opacity = '1';
                    backToTop.style.visibility = 'visible';
                } else {
                    backToTop.style.opacity = '0';
                    backToTop.style.visibility = 'hidden';
                }
            });
            
            // Add animation to feature cards on scroll
            const featureCards = document.querySelectorAll('.feature-card');
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.transform = 'translateY(0)';
                        entry.target.style.opacity = '1';
                    }
                });
            }, {
                threshold: 0.1
            });
            
            featureCards.forEach(card => {
                card.style.transform = 'translateY(30px)';
                card.style.opacity = '0';
                card.style.transition = 'transform 0.6s ease, opacity 0.6s ease';
                observer.observe(card);
            });
        });
    </script>
</body>
</html>