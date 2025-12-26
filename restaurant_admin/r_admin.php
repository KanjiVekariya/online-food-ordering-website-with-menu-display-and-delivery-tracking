<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'food_app');
if ($conn->connect_error) die("DB error: " . $conn->connect_error);

// Fetch all restaurants for the dropdown
$restRes = $conn->query("SELECT restaurant_id, name FROM restaurants ORDER BY name");
$restaurants = $restRes->fetch_all(MYSQLI_ASSOC);

// If this is an AJAX request for orders filtered by restaurant_id
if (isset($_GET['restaurant_id'])) {
    $restaurant_id = intval($_GET['restaurant_id']);

    // Prepare SQL with optional restaurant filtering
    if ($restaurant_id > 0) {
    $sql = "
        SELECT 
            o.order_id, o.placed_at, o.order_status, o.total_price,
            u.user_id, u.name AS user_name, u.email AS user_email,
            r.name AS restaurant_name
        FROM orders o
        JOIN restaurants r ON o.restaurant_id = r.restaurant_id
        JOIN users u ON o.user_id = u.user_id
        WHERE r.restaurant_id = ?
        ORDER BY o.placed_at DESC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $restaurant_id);
} else {
    // Fetch all orders if no restaurant filter is given
    $sql = "
        SELECT 
            o.order_id, o.placed_at, o.order_status, o.total_price,
            u.user_id, u.name AS user_name, u.email AS user_email,
            r.name AS restaurant_name
        FROM orders o
        JOIN restaurants r ON o.restaurant_id = r.restaurant_id
        JOIN users u ON o.user_id = u.user_id
        ORDER BY o.placed_at DESC
    ";
    $stmt = $conn->prepare($sql);
}

    $stmt->execute();
    $res = $stmt->get_result();
    $orders = [];

    while ($order = $res->fetch_assoc()) {
        $user_id = $order['user_id'];

        // Fetch first address of the user if any
        $addrStmt = $conn->prepare("SELECT label, full_address, city, postal_code FROM addresses WHERE user_id = ? LIMIT 1");
        $addrStmt->bind_param("i", $user_id);
        $addrStmt->execute();
        $addrRes = $addrStmt->get_result();
        $address = $addrRes->fetch_assoc() ?: null;

        // Fetch order items
        $itemsStmt = $conn->prepare("
            SELECT m.name AS item_name, oi.quantity, oi.price
            FROM order_items oi
            JOIN menu_items m ON oi.item_id = m.item_id
            WHERE oi.order_id = ?
        ");
        $itemsStmt->bind_param("i", $order['order_id']);
        $itemsStmt->execute();
        $itemsRes = $itemsStmt->get_result();
        $items = $itemsRes->fetch_all(MYSQLI_ASSOC);

        $order['address'] = $address;
        $order['items'] = $items;

        $orders[] = $order;
    }

    header('Content-Type: application/json');
    echo json_encode($orders);
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Orders Dashboard</title>
<style>
  body { font-family: Arial, sans-serif; padding: 20px; background: #f3f4f6; }
  select { font-size: 16px; padding: 8px; margin-bottom: 20px; }
  table { width: 100%; border-collapse: collapse; background: #fff; margin-bottom: 30px; }
  th, td { border: 1px solid #ddd; padding: 12px; vertical-align: top; }
  th { background: #f4f4f4; }
  ul { margin: 0; padding-left: 20px; }
  #orders-container p { font-style: italic; }
</style>
</head>
<body>

<h1>Orders Dashboard</h1>

<label for="restaurant-select">Filter by Restaurant:</label>
<select id="restaurant-select">
  <option value="0">-- All Restaurants --</option>
  <?php foreach ($restaurants as $r): ?>
    <option value="<?= $r['restaurant_id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
  <?php endforeach; ?>
</select>

<div id="orders-container">
  <p>Loading orders...</p>
</div>

<script>
const ordersContainer = document.getElementById('orders-container');
const restaurantSelect = document.getElementById('restaurant-select');

function fetchOrders(restaurantId) {
  ordersContainer.innerHTML = '<p>Loading orders...</p>';

  fetch('<?= basename(__FILE__) ?>?restaurant_id=' + restaurantId)
    .then(res => res.json())
    .then(data => {
      if (!data.length) {
        ordersContainer.innerHTML = '<p>No orders found.</p>';
        return;
      }

      // Build a single table with all orders
      let html = `
        <table>
          <thead>
            <tr>
              <th>Order ID</th>
              <th>Placed At</th>
              <th>Status</th>
              <th>Restaurant</th>
              <th>User</th>
              <th>Delivery Address</th>
              <th>Items</th>
              <th>Total Price (₹)</th>
            </tr>
          </thead>
          <tbody>
      `;

      data.forEach(order => {
        html += `
          <tr>
            <td>${order.order_id}</td>
            <td>${order.placed_at}</td>
            <td>${order.order_status.charAt(0).toUpperCase() + order.order_status.slice(1).replace(/_/g, ' ')}</td>
            <td>${order.restaurant_name}</td>
            <td>${order.user_name}<br/><small>${order.user_email}</small></td>
            <td>
              ${order.address ? 
                (order.address.label ? order.address.label + '<br/>' : '') +
                (order.address.full_address ? order.address.full_address + '<br/>' : '') +
                (order.address.city ? order.address.city + ' - ' : '') +
                (order.address.postal_code ? order.address.postal_code : '') 
                : 'N/A'}
            </td>
            <td>
              <ul style="margin:0; padding-left: 18px;">
                ${order.items.map(i => `<li>${i.item_name} × ${i.quantity} = ₹${(i.price * i.quantity).toFixed(2)}</li>`).join('')}
              </ul>
            </td>
            <td>₹${parseFloat(order.total_price).toFixed(2)}</td>
          </tr>
        `;
      });

      html += `
          </tbody>
        </table>
      `;

      ordersContainer.innerHTML = html;
    })
    .catch(err => {
      ordersContainer.innerHTML = '<p style="color:red;">Failed to load orders. Please try again.</p>';
      console.error(err);
    });
}



// Initial load: all restaurants
fetchOrders(0);

restaurantSelect.addEventListener('change', () => {
  console.log("Selected restaurant ID:", restaurantSelect.value);
  fetchOrders(restaurantSelect.value);
});

</script>

</body>
</html>
