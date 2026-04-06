<?php
// about.php
session_start();
include "config.php";

// Check login state
$is_logged_in = isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true;
$user_firstname = '';
$user_email = '';

if ($is_logged_in) {
    $user_id = $_SESSION['user_id'];
    $sql_user = "SELECT fname, email FROM users WHERE user_id = ?";
    if ($stmt_user = mysqli_prepare($conn, $sql_user)) {
        mysqli_stmt_bind_param($stmt_user, "i", $user_id);
        mysqli_stmt_execute($stmt_user);
        $result_user = mysqli_stmt_get_result($stmt_user);
        if ($user_row = mysqli_fetch_assoc($result_user)) {
            $user_firstname = $user_row['fname'];
            $user_email = $user_row['email'];
        }
        mysqli_stmt_close($stmt_user);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>About Us | Vastra</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="index.css"> 
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script src="refresh.js"></script>
  <style>
      /* Page background & Typography */
      body {
        background-color: #fdfdfd; /* Slightly brighter white */
        font-family: 'Poppins', Arial, sans-serif; /* Modern, clean font */
        margin: 0;
        padding: 0;
        color: #333;
        -webkit-font-smoothing: antialiased; /* Smoother text rendering */
        text-rendering: optimizeLegibility;
      }

      /* Page Header Banner */
      .page-header-about {
        background: linear-gradient(135deg, #ff3f6c 0%, #a61e4d 100%);
        padding: 100px 20px;
        text-align: center;
        color: white;
      }
      .page-header-about h1 {
        font-size: 3.5rem;
        margin-bottom: 15px;
        font-weight: 700;
        /* Adjusted text shadow for better look on flat color */
        text-shadow: 0 1px 4px rgba(0,0,0,0.2); 
      }
      .page-header-about p {
        font-size: 1.3rem;
        max-width: 750px;
        margin: 0 auto;
        opacity: 0.95;
        font-weight: 300;
      }

      /* General About Sections */
      .about-section {
        padding: 80px 20px; /* Increased vertical padding */
        text-align: center;
      }
      .about-section .container {
        max-width: 900px;
        margin: 0 auto;
      }
      .about-section h2 {
        font-size: 2.5rem; /* Slightly larger heading */
        color: #222;
        margin-bottom: 25px;
        position: relative;
        display: inline-block;
        padding-bottom: 15px;
        font-weight: 600;
      }
      .about-section h2::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 70px; /* Longer underline */
        height: 4px; /* Thicker underline */
        background-color: #ff3f6c; /* primary */
        border-radius: 2px;
      }
      .about-section p {
        font-size: 1.1rem;
        line-height: 1.8;
        color: #555;
        max-width: 800px;
        margin: 0 auto 30px auto;
        font-weight: 400; /* Regular weight for body text */
      }

      /* Feature Grid */
      .feature-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(270px, 1fr));
        gap: 30px;
        margin-top: 50px; /* More space above the grid */
      }
      .feature-card {
        background-color: #fff;
        padding: 40px 30px; /* More padding inside cards */
        border-radius: 12px; /* Softer corners */
        box-shadow: 0 5px 25px rgba(0, 0, 0, 0.07); /* Softer, more modern shadow */
        border: 1px solid #eee;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        border-top: 4px solid #ff3f6c; /* Brand color accent */
      }
      .feature-card:hover {
        transform: translateY(-10px); /* More pronounced hover effect */
        box-shadow: 0 12px 35px rgba(0, 0, 0, 0.1); /* Deeper shadow on hover */
      }
      .feature-card i {
        font-size: 3rem; /* Larger icons */
        color: #ff3f6c;
        margin-bottom: 20px;
      }
      .feature-card h3 {
        font-size: 1.5rem;
        color: #333;
        margin-bottom: 15px;
        font-weight: 600;
      }
      .feature-card p {
        font-size: 1rem;
        line-height: 1.6;
      }


      /* Call to Action Section */
      .cta-section {
        background-color: #282c3f; /* A richer, deeper dark blue/grey */
        color: white;
        border-radius: 20px; /* Rounded corners for the section */
        margin: 40px 20px; /* Margin to separate from footer/content */
        padding: 70px 20px;
      }
      .cta-section h2 {
        color: #fff;
      }
      .cta-section p {
        color: #ccc;
        margin-bottom: 40px;
      }
      .cta-buttons {
        display: flex;
        justify-content: center;
        gap: 20px;
        margin-top: 20px;
      }
      .btn {
        display: inline-block;
        padding: 14px 32px; /* Larger buttons */
        border-radius: 50px; /* Pill-shaped buttons */
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s ease;
        border: 2px solid transparent;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        font-size: 0.9rem;
      }
      .btn-primary {
        background: #ff3f6c;
        color: white;
      }
      .btn-primary:hover {
        background: #e6395a;
        transform: translateY(-3px);
        box-shadow: 0 4px 15px rgba(255, 63, 108, 0.4);
      }
      .btn-secondary {
        background: transparent;
        color: white;
        border: 2px solid white;
      }
      .btn-secondary:hover {
        background: white;
        color: #282c3f;
        transform: translateY(-3px);
      }

  </style>
</head>
<body>
  <!-- Navbar -->
  <nav class="navbar">
    <div class="container nav-container">
      <button class="mobile-menu-btn" id="mobile-menu-button"><i class="fas fa-bars"></i></button>
      <a href="index.php" class="logo">VASTRA</a>
      <?php include "nav_links.html"; ?>
      <div class="nav-icons">
        <a href="search.php"><i class="fas fa-search"></i></a>
        <a href="wishlist.php"><i class="far fa-heart"></i></a>
        <a href="cart.php"><i class="fas fa-shopping-bag"></i></a>
        <div class="profile-card-container">
          <a href="#" id="user-icon-btn"><i class="far fa-user"></i></a>
          <?php if ($is_logged_in): ?>
            <span class="user-greeting-text">Hello, <?php echo htmlspecialchars($user_firstname); ?></span>
            <div id="profile-card" class="profile-card">
              <div class="profile-header">
                <h4>Hello, <?php echo htmlspecialchars($user_firstname); ?></h4>
                <p><?php echo htmlspecialchars($user_email); ?></p>
              </div>
              <ul class="profile-card-links">
                <li><a href="dashboard.php"><i class="far fa-user-circle fa-fw"></i> My Profile</a></li>
                <li><a href="myorders.php"><i class="fas fa-box-open fa-fw"></i> Orders</a></li>
                <li><a href="wishlist.php"><i class="far fa-heart fa-fw"></i> Wishlist</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt fa-fw"></i> Logout</a></li>
              </ul>
            </div>
          <?php else: ?>
            <div id="profile-card" class="profile-card">
              <div class="profile-header">
                <h4>Welcome</h4>
                <p>To access account and manage orders</p>
              </div>
              <a href="login.php" class="btn-login-signup">LOGIN / SIGNUP</a>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </nav>

  <!-- Page Header -->
  <header class="page-header-about">
    <h1>About Vastra</h1>
    <p>Weaving together local artisans and fashion enthusiasts into one vibrant community.</p>
  </header>

  <!-- Our Story -->
  <section class="about-section">
    <div class="container">
      <h2>Our Story</h2>
      <p>
        Vastra was born from a simple idea: to create a bridge between the rich, diverse world of local textile shops and the modern online shopper. 
        Our platform empowers small businesses to thrive in the digital age, while offering customers a unique, curated selection of fashion and textiles.
      </p>
    </div>
  </section>

  <!-- Mission -->
  <section class="about-section" style="background-color: #fff;">
    <div class="container">
      <h2>Our Mission</h2>
      <p>
        To be the most trusted online community for authentic textiles and fashion, empowering local sellers and delighting customers with quality, variety, and a seamless shopping experience.
      </p>
      <div class="feature-grid">
        <div class="feature-card">
          <i class="fas fa-certificate"></i>
          <h3>Quality & Authenticity</h3>
          <p>Every seller is hand-picked to ensure high-quality and authentic products.</p>
        </div>
        <div class="feature-card">
          <i class="fas fa-users"></i>
          <h3>Community First</h3>
          <p>We bring together sellers and buyers who share a passion for fashion.</p>
        </div>
        <div class="feature-card">
          <i class="fas fa-store"></i>
          <h3>Empowering Local Business</h3>
          <p>Helping local shops grow their reach with the tools of e-commerce.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Call to Action -->
  <section class="about-section cta-section">
    <div class="container">
      <h2>Join Our Community</h2>
      <p>
        Whether you're looking for the perfect outfit, unique fabrics, or a platform to grow your business, Vastra is the right place. Explore collections or become a seller today!
      </p>
      <div class="cta-buttons">
        <a href="products.php" class="btn btn-primary">Shop Now</a>
        <a href="dashboard/become-seller.php" class="btn btn-secondary">Become a Seller</a>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <?php include 'footer.html'; ?>

  <!-- JS for Profile Dropdown -->
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const profileCardContainer = document.querySelector('.profile-card-container');
      const profileCard = document.getElementById('profile-card');
      if (profileCardContainer && profileCard) {
        profileCardContainer.addEventListener('click', (event) => {
          event.stopPropagation();
          // Updated this part to ensure any click on the container toggles the menu
          if (event.target.closest('.profile-card-container')) {
            profileCard.classList.toggle('active');
          }
        });
      }
      document.addEventListener('click', (event) => {
        if (profileCard && profileCard.classList.contains('active')) {
          if (!profileCardContainer.contains(event.target)) {
            profileCard.classList.remove('active');
          }
        }
      });
    });
  </script>
</body>
</html>