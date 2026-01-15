<?php
// Include common headers
include 'includes/config.php';
?>

<section id="size-guide" class="section-p1">
    <div class="container">
        <a href="<?= isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'shop.php' ?>" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Product
        </a>
        
        <h1>Women's Shoes Size Guide</h1>
        
        <div class="size-chart">
            <table class="size-table">
                <thead>
                    <tr>
                        <th>Euro</th>
                        <th>US Type</th>
                        <th>US</th>
                        <th>UK</th>
                        <th>China</th>
                        <th>Foot Length (cm)</th>
                        <th>Range (cm)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>35</td><td>Adult Women</td><td>4</td><td>2</td><td>34</td><td>20.8</td><td>20.6 - 21.0</td></tr>
                    <tr><td>35.5</td><td>Adult Women</td><td>4.5</td><td>2.5</td><td>35</td><td>21.3</td><td>21.1 - 21.5</td></tr>
                    <!-- Add all other rows -->
                </tbody>
            </table>
        </div>

        <div class="measurement-instructions">
            <h2>How to Measure</h2>
            <div class="instruction-step">
                <h3>1. Foot Length</h3>
                <p>Stand against a wall with your heel touching it. Place a ruler flat on the floor and measure from the wall to the tip of your longest toe.</p>
            </div>
            
            <div class="instruction-step">
                <h3>2. Size Selection</h3>
                <p>If between sizes, choose the larger size. Always use measurements from your larger foot.</p>
            </div>
            
            <div class="visual-guide">
                <img src="images/measurement-diagram.jpg" alt="Foot measurement diagram">
            </div>
        </div>
    </div>
</section>

