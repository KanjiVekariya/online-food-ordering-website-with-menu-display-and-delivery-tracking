<?php
session_start();
include 'config.php';

// Ensure the restaurant is logged in
if (!isset($_SESSION['restaurant_id'], $_SESSION['restaurant_name'])) {
    header("Location: restaurant_login.php");
    exit;
}

$restaurant_id = (int)$_SESSION['restaurant_id'];
$restaurant_name = $_SESSION['restaurant_name'];

$error = '';
$success = '';

// Handle status update or deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_order_id']) && $_POST['delete_order_id'] !== '') {
        $delete_order_id = (int)$_POST['delete_order_id'];

        $checkDeleteStmt = $pdo->prepare("
            SELECT 1 FROM order_items oi
            INNER JOIN menu_items m ON oi.item_id = m.item_id
            WHERE oi.order_id = ? AND m.restaurant_id = ?
            LIMIT 1
        ");
        $checkDeleteStmt->execute([$delete_order_id, $restaurant_id]);

        if ($checkDeleteStmt->fetch()) {
            $delItemsStmt = $pdo->prepare("DELETE FROM order_items WHERE order_id = ?");
            $delItemsStmt->execute([$delete_order_id]);

            $delOrderStmt = $pdo->prepare("DELETE FROM orders WHERE order_id = ?");
            $delOrderStmt->execute([$delete_order_id]);

            $success = "Order #$delete_order_id has been deleted successfully.";
        } else {
            $error = "Order not found or does not belong to your restaurant.";
        }
    }
    // Update status per order_item now
    else if (isset($_POST['order_item_id'], $_POST['item_status'])) {
        $order_item_id = (int)$_POST['order_item_id'];
        $new_status = $_POST['item_status'];
        $valid_statuses = ['pending', 'preparing', 'on_the_way','delivered' ,'cancelled'];

        if (in_array($new_status, $valid_statuses, true)) {
            // Verify this order_item belongs to this restaurant
            $checkStmt = $pdo->prepare("
                SELECT oi.status, oi.restaurant_canceled, o.order_status
                FROM order_items oi
                INNER JOIN menu_items m ON oi.item_id = m.item_id
                INNER JOIN orders o ON oi.order_id = o.order_id
                WHERE oi.order_item_id = ? AND m.restaurant_id = ?
                LIMIT 1
            ");
            $checkStmt->execute([$order_item_id, $restaurant_id]);
            $item = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($item) {
                if ($item['restaurant_canceled'] == 1 || $item['status'] === 'cancelled' || $item['order_status'] === 'cancelled') {
                    $error = "This item/order is cancelled and cannot be updated.";
                } else {
                    $updateStmt = $pdo->prepare("UPDATE order_items SET status = ? WHERE order_item_id = ?");
                    $updateStmt->execute([$new_status, $order_item_id]);
                    $success = "Order item status updated to " . ucfirst($new_status) . ".";
                }
            } else {
                $error = "Invalid order item or does not belong to your restaurant.";
            }
        } else {
            $error = "Invalid status value.";
        }
    }
}

// Fetch orders for this restaurant with items and user info
$stmt = $pdo->prepare("
    SELECT o.order_id, o.placed_at, o.order_status,
           u.user_id, u.name AS user_name, u.email AS user_email,
           oi.order_item_id, oi.quantity, oi.price, oi.status AS item_status, oi.user_canceled, oi.restaurant_canceled,
           m.name AS item_name
    FROM orders o
    INNER JOIN users u ON o.user_id = u.user_id
    INNER JOIN order_items oi ON o.order_id = oi.order_id
    INNER JOIN menu_items m ON oi.item_id = m.item_id
    WHERE m.restaurant_id = ?
    ORDER BY o.placed_at DESC, o.order_id, oi.order_item_id
");
$stmt->execute([$restaurant_id]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$results) {
    $orders = [];
} else {
    // Group data by order_id
    $orders = [];
    foreach ($results as $row) {
        $oid = $row['order_id'];
        if (!isset($orders[$oid])) {
            $orders[$oid] = [
                'order_id' => $oid,
                'placed_at' => $row['placed_at'],
                'order_status' => $row['order_status'],
                'user_id' => $row['user_id'],
                'user_name' => $row['user_name'],
                'user_email' => $row['user_email'],
                'items' => [],
            ];
        }
        $orders[$oid]['items'][] = [
            'order_item_id' => $row['order_item_id'],
            'item_name' => $row['item_name'],
            'quantity' => $row['quantity'],
            'price' => $row['price'],
            'status' => $row['item_status'],
            'user_canceled' => $row['user_canceled'],
            'restaurant_canceled' => $row['restaurant_canceled'],
        ];
    }
}

// Fetch addresses for all users in this list
$user_ids = array_unique(array_column($orders, 'user_id'));
$addresses = [];
if (!empty($user_ids)) {
    $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
    $addrStmt = $pdo->prepare("SELECT user_id, label, full_address, city, postal_code FROM addresses WHERE user_id IN ($placeholders)");
    $addrStmt->execute($user_ids);
    $addressesRaw = $addrStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($addressesRaw as $addr) {
        $addresses[$addr['user_id']] = $addr;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title><?= htmlspecialchars($restaurant_name) ?> - Orders</title>
<style>
    body { font-family: Arial, sans-serif; background: #f9f9f9; padding: 20px; }
    table { width: 100%; border-collapse: collapse; background: #fff; margin-bottom: 40px; }
    th, td { border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: middle; }
    th { background: #eee; }
    tr.order-header { background: #dfe6f0;  }
    .cancelled-label { color: #b22222; font-weight: bold; margin-left: 8px; }
    select { padding: 4px 6px; font-size: 14px; border-radius: 3px; border: 1px solid #999; }
    .btn-delete {
        background: #cc0000;
        color: #fff;
        border: none;
        padding: 6px 10px;
        cursor: pointer;
        border-radius: 3px;
        font-size: 14px;
    }
    .btn-delete:hover {
        background: #990000;
    }
    .message { padding: 10px; margin-bottom: 15px; border-radius: 5px; }
    .success { background: #d4edda; color: #155724; }
    .error { background: #f8d7da; color: #721c24; }
</style>
</head>
<body>

<h1>Orders for <?= htmlspecialchars($restaurant_name) ?></h1>

<?php if ($success): ?>
    <div class="message success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="message error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (empty($orders)): ?>
    <p>No orders found for this restaurant.</p>
<?php else: ?>
<form method="POST" id="statusForm">
    <?php foreach ($orders as $order): 
        $addr = $addresses[$order['user_id']] ?? null;
        ?>
        <table>
            <thead>
                <tr class="order-header">
                    <td colspan="9">
                        <b>Order </b><?= $order['order_id'] ?> <br><b>Placed at </b><?= date('d M Y, H:i', strtotime($order['placed_at'])) ?>
                        <button type="button" class="btn-delete" data-order-id="<?= $order['order_id'] ?>" style="float:right;">Delete Order</button>
                        <br>
                        <b>User:</b> <?= htmlspecialchars($order['user_name']) ?> (<?= htmlspecialchars($order['user_email']) ?>)<br>
                        <b>Delivery Address:</b> 
                        <?php if ($addr): ?>
                            <?= htmlspecialchars($addr['label']) ?>, <?= htmlspecialchars($addr['full_address']) ?>, <?= htmlspecialchars($addr['city']) ?> - <?= htmlspecialchars($addr['postal_code']) ?>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Item Name</th>
                    <th>Quantity</th>
                    <th>Price (₹)</th>
                    <th>Total (₹)</th>
                    <th>Status</th>
                    <th>Cancelled</th>
                    <th colspan="3">Update Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $orderTotal = 0;
                foreach ($order['items'] as $item):
                    $itemTotal = $item['price'] * $item['quantity'];
                    $orderTotal += $itemTotal;
                    $isCancelled = ($item['user_canceled'] == 1 || $item['restaurant_canceled'] == 1 || $item['status'] === 'cancelled');
                ?>
                <tr>
                    <td><?= htmlspecialchars($item['item_name']) ?></td>
                    <td><?= $item['quantity'] ?></td>
                    <td>₹<?= number_format($item['price'], 2) ?></td>
                    <td>₹<?= number_format($itemTotal, 2) ?></td>
                    <td>
                        <?= ucfirst(str_replace('_', ' ', $item['status'])) ?>
                    </td>
                    <td>
                        <?php if ($isCancelled): ?>
                            <span class="cancelled-label">Yes</span>
                        <?php else: ?>
                            No
                        <?php endif; ?>
                    </td>
                    <td colspan="3">
                        <?php if (!$isCancelled): ?>
                        <select name="item_status" data-order-item-id="<?= $item['order_item_id'] ?>">
                            <?php
                            $statuses = ['pending', 'preparing', 'on_the_way','delivered' ,'cancelled'];
                            foreach ($statuses as $statusOption):
                                $selected = ($item['status'] === $statusOption) ? "selected" : "";
                                echo "<option value=\"$statusOption\" $selected>" . ucfirst($statusOption) . "</option>";
                            endforeach;
                            ?>
                        </select>
                        <?php else: ?>
                            <em>Cannot update</em>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <tr>
                    <td colspan="3" style="text-align:right; font-weight:bold;">Order Total:</td>
                    <td colspan="6" style="font-weight:bold;">₹<?= number_format($orderTotal, 2) ?></td>
                </tr>
            </tbody>
        </table>
    <?php endforeach; ?>

    <input type="hidden" name="order_item_id" id="order_item_id_input" value="">
    <input type="hidden" name="item_status" id="item_status_input" value="">
    <input type="hidden" name="delete_order_id" id="delete_order_id_input" value="">
</form>

<script>
// Handle status change per order item
document.querySelectorAll('select[name="item_status"]').forEach(select => {
    select.addEventListener('change', function() {
        document.getElementById('order_item_id_input').value = this.getAttribute('data-order-item-id');
        document.getElementById('item_status_input').value = this.value;
        document.getElementById('delete_order_id_input').value = ''; // clear delete
        document.getElementById('statusForm').submit();
    });
});

// Handle delete order button
document.querySelectorAll('.btn-delete').forEach(button => {
    button.addEventListener('click', function() {
        const orderId = this.getAttribute('data-order-id');
        if (confirm(`Are you sure you want to DELETE order #${orderId}? This action cannot be undone.`)) {
            document.getElementById('delete_order_id_input').value = orderId;
            // Clear status update inputs
            document.getElementById('order_item_id_input').value = '';
            document.getElementById('item_status_input').value = '';
            document.getElementById('statusForm').submit();
        }
    });
});
</script>

<script>
window.addEventListener('DOMContentLoaded', function () {
    const successMessage = document.querySelector('.message.success');
    const errorMessage = document.querySelector('.message.error');

    if (successMessage) {
        setTimeout(function () {
            successMessage.style.display = 'none';
        }, 3000);
    }

    if (errorMessage) {
        setTimeout(function () {
            errorMessage.style.display = 'none';
        }, 3000);
    }
});
</script>

<?php endif; ?>

</body>
</html>
