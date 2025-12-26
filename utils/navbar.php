<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'head.php';
?>

<style>
  nav {
    background-color: #AF3E3E;
	position:sticky;
	top:0;
	z-index:1000;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 30px;
    color: white;
  }

  nav .logo {
    font-size: 24px;
    
    cursor: pointer;
  }

  nav ul {
    list-style: none;
    display: flex;
    gap: 20px;
    margin: 0;
    padding: 0;
    align-items: center;
  }

  nav ul li a {
    color: white;
    text-decoration: none;
    font-size: 16px;
    transition: 0.3s;
  }

  nav ul li a:hover {
    //text-decoration: underline;
	color:lightgrey;
  }

  nav ul li span {
    font-weight: 600;
    font-size: 16px;
  }

  /* Keep the original button styles */
  .btn-signup {
    background-color: transparent;
    border: 1px solid lightgrey;
    color: white;
    padding: 6px 20px;
    border-radius: 15px;
    font-weight: bold;
    cursor: pointer;
    font-size: 16px;
    transition: 0.3s;
  }
  .btn-signup:hover {
    opacity: 0.8;
  }

  .btn-login {
    background-color: #000;
    color: white;
    border: none;
    padding: 8px 20px;
    border-radius: 15px;
    cursor: pointer;
    font-weight: bold;
    font-size: 16px;
    transition: 0.3s;
  }
  .btn-login:hover {
    opacity: 0.8;
  }
  .btn-logout {
    background-color: transparent;
    border: 1px solid lightgrey;
    color: white;
    padding: 6px 20px;
    border-radius: 15px;
    font-weight: bold;
    cursor: pointer;
    font-size: 16px;
    transition: 0.3s;
    text-decoration: none; /* In case it's an anchor */
    display: inline-block;
  }

  .btn-logout:hover {
    background-color:white;
	color:black;
  }
</style>

<nav>
  <div class="logo" onclick="window.location.href='index.php'">DailyBites 🍽️</div>
  <ul>
    <li><a href="index.php">Home</a></li>
    <li><a href="menu.php">Menu</a></li>
    <li><a href="view_cart.php">Cart</a></li>
    <li><a href="orders.php">Orders</a></li>
    <li><a href="about_us.php">About Us</a></li>

    <?php if (isset($_SESSION['user_id'])): ?>
      <li><span>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></span></li>
      <li><a href="logout.php" class="btn-logout">Logout</a></li>
    <?php else: ?>
      <li>
        <button class="btn-signup" onclick="window.location.href='signup.php'">Sign Up</button>
      </li>
      <li>
        <button class="btn-login" onclick="window.location.href='login.php'">Login</button>
      </li>
    <?php endif; ?>
  </ul>
</nav>
