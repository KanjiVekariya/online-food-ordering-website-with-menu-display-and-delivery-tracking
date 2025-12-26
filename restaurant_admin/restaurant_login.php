<?php
session_start();
include 'config.php';

if (isset($_GET['action']) && $_GET['action'] === 'suggest') {
    header('Content-Type: application/json');

    $q = trim($_GET['q'] ?? '');
    if (!$q) {
        echo json_encode([]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT name FROM restaurants WHERE name LIKE ? ORDER BY name LIMIT 10");
    $stmt->execute(["%$q%"]);
    $names = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode($names);
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $restaurant_name = trim($_POST['restaurant_name'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($password !== '123') {
        $error = "Invalid password.";
    } else {
        $stmt = $pdo->prepare("SELECT restaurant_id, name FROM restaurants WHERE name = ?");
        $stmt->execute([$restaurant_name]);
        $restaurant = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($restaurant) {
            $_SESSION['restaurant_id'] = $restaurant['restaurant_id'];
            $_SESSION['restaurant_name'] = $restaurant['name'];
            header("Location: restaurant_orders.php");
            exit;
        } else {
            $error = "Restaurant not found.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Restaurant Login</title>
<style>
  body {
      font-family: Arial, sans-serif;
      background: #f3f4f6;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      margin: 0;
  }
  .login-box {
      background: white;
      padding: 30px;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
      width: 450px;
      box-sizing: border-box;
  }
  h2 {
      margin-top: 0;
      color: #f97316;
      text-align: center;
  }
  form {
      display: flex;
      flex-direction: column;
  }
  label {
      margin-bottom: 5px;
      font-weight: bold;
  }
  input[type="text"], input[type="password"] {
      width: 100%;
      padding: 12px 10px;
      font-size: 16px;
      border: 1px solid #ccc;
      box-sizing: border-box;
      display: block;
	  margin:0.75rem 0rem;
  }
  button {
      background: #f97316;
      color: white;
      border: none;
      cursor: pointer;
      padding: 15px;
      font-size: 16px;
      transition: background-color 0.3s ease;
      margin-top: 15px;
  }
  button:hover {
      background: #ea580c;
  }
  .error {
      color: red;
      text-align: center;
      margin-bottom: 10px;
  }
  /* Suggestions box */
  #suggestions {
      border: 1px solid #ccc;
      border-top: none;
      max-height: 150px;
      overflow-y: auto;
      background: white;
      position: absolute;
      width: calc(100% - 24px);
      box-sizing: border-box;
      display: none;
      z-index: 1000;
  }
  #suggestions div {
      padding: 8px 10px;
      cursor: pointer;
  }
  #suggestions div:hover,
  #suggestions div.selected {
      background-color: #f97316;
      color: white;
  }
  .input-wrapper {
    position: relative;
  }
  .login-box input[type="text"]:focus,
.login-box input[type="password"]:focus {
  outline: none;
  box-shadow: none;
}

</style>
</head>
<body>
<div class="login-box">
  <h2>Restaurant Login</h2>

  <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" autocomplete="off">
    <div class="input-wrapper">
      <label for="restaurant_name">Restaurant Name</label>
      <input type="text" name="restaurant_name" id="restaurant_name" placeholder="" required />
      <div id="suggestions"></div>
    </div>

    <label for="password">Password</label>
    <input type="password" name="password" id="password" required />

    <button type="submit">Login</button>
  </form>
</div>

<script>
  const input = document.getElementById('restaurant_name');
  const suggestions = document.getElementById('suggestions');
  let selectedIndex = -1;

  input.addEventListener('input', async () => {
    const query = input.value.trim();
    selectedIndex = -1; // reset selection

    if (query.length === 0) {
      suggestions.style.display = 'none';
      suggestions.innerHTML = '';
      return;
    }

    try {
      const response = await fetch('?action=suggest&q=' + encodeURIComponent(query));
      if (!response.ok) throw new Error('Network error');
      const data = await response.json();

      if (data.length === 0) {
        suggestions.style.display = 'none';
        suggestions.innerHTML = '';
        return;
      }

      suggestions.innerHTML = data.map(name => `<div>${name}</div>`).join('');
      suggestions.style.display = 'block';
    } catch (error) {
      console.error(error);
      suggestions.style.display = 'none';
      suggestions.innerHTML = '';
    }
  });

  suggestions.addEventListener('click', e => {
    if (e.target.tagName.toLowerCase() === 'div') {
      input.value = e.target.textContent;
      suggestions.style.display = 'none';
    }
  });

  input.addEventListener('keydown', e => {
    const items = suggestions.querySelectorAll('div');
    if (suggestions.style.display === 'none' || items.length === 0) return;

    if (e.key === 'ArrowDown') {
      e.preventDefault();
      selectedIndex = (selectedIndex + 1) % items.length;
      updateSelection();
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      selectedIndex = (selectedIndex - 1 + items.length) % items.length;
      updateSelection();
    } else if (e.key === 'Enter') {
      if (selectedIndex > -1) {
        e.preventDefault();
        input.value = items[selectedIndex].textContent;
        suggestions.style.display = 'none';
      }
    } else if (e.key === 'Escape') {
      suggestions.style.display = 'none';
    }
  });

  function updateSelection() {
    const items = suggestions.querySelectorAll('div');
    items.forEach((item, i) => {
      item.classList.toggle('selected', i === selectedIndex);
    });
  }

  document.addEventListener('click', e => {
    if (!suggestions.contains(e.target) && e.target !== input) {
      suggestions.style.display = 'none';
    }
  });
</script>
</body>
</html>
