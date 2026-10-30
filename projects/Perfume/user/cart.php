<?php
session_start();
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
  header('Location: ../login.php');
  exit;
}

include '../include/connection.php';

// Get logged in user ID
$username = $_SESSION['username'] ?? '';
$userId = 0;
$userSql = "SELECT id FROM users WHERE username=?";
$userStmt = $conn->prepare($userSql);
$userStmt->bind_param('s', $username);
$userStmt->execute();
$userStmt->bind_result($userId);
$userStmt->fetch();
$userStmt->close();

/* ---------------- CART ACTIONS ---------------- */
if (isset($_GET['action'], $_GET['id'])) {
  $cartId = intval($_GET['id']);
  if ($_GET['action'] === 'add') {
    $conn->query("UPDATE cart SET quantity = quantity + 1 WHERE id = $cartId");
  } elseif ($_GET['action'] === 'subtract') {
    $conn->query("UPDATE cart SET quantity = quantity - 1 WHERE id = $cartId AND quantity > 1");
  } elseif ($_GET['action'] === 'remove') {
    $conn->query("DELETE FROM cart WHERE id = $cartId");
  }
  header('Location: cart.php');
  exit;
}

/* ---------------- FETCH CART ITEMS ---------------- */
$cartItems = [];
$cartSql = "SELECT c.id, p.name, p.price, p.image, c.quantity 
            FROM cart c 
            JOIN product p ON c.product_id = p.id 
            WHERE c.user_id=?";
$cartStmt = $conn->prepare($cartSql);
$cartStmt->bind_param('i', $userId);
$cartStmt->execute();
$result = $cartStmt->get_result();
while ($row = $result->fetch_assoc()) {
  $cartItems[] = $row;
}
$cartStmt->close();
$conn->close();

include 'include/header.php'; 
?>

<main style="flex: 1; padding: 40px; background-color: #fff; overflow-y: auto;">
    
    <h1 style="font-family: 'Playfair Display', serif; color: #5E5343; margin-bottom: 30px;">
        Shopping Bag
    </h1>

    <div class="cart-container">
      <?php if (count($cartItems) > 0): ?>
        <?php $subtotal = 0; $taxRate = 0.08; ?>
        
        <div class="table-responsive">
            <table>
              <thead>
                <tr>
                  <th style="width: 50px;"><input type="checkbox" id="selectAll" checked onchange="toggleAll(this)"></th>
                  <th>Product</th>
                  <th>Price</th>
                  <th>Quantity</th>
                  <th>Total</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($cartItems as $item):
                  $itemTotal = $item['price'] * $item['quantity'];
                  $subtotal += $itemTotal;
                ?>
                  <tr>
                    <td>
                      <input type="checkbox" class="item-checkbox" value="<?= $item['id']; ?>"
                        data-price="<?= $item['price']; ?>"
                        data-quantity="<?= $item['quantity']; ?>"
                        checked onchange="updateTotals()">
                    </td>
                    <td>
                        <div class="product-cell">
                            <?php if ($item['image']): ?>
                                <img src="../<?= htmlentities($item['image']); ?>" alt="Product">
                            <?php else: ?>
                                <div class="no-img">No Image</div>
                            <?php endif; ?>
                            <span class="p-name"><?= htmlentities($item['name']); ?></span>
                        </div>
                    </td>
                    <td style="color: #A1937A; font-weight: 600;">&#8377;<?= htmlentities($item['price']); ?></td>
                    <td>
                      <div class="qty-control">
                          <a href="cart.php?action=subtract&id=<?= $item['id']; ?>"><i class="fas fa-minus"></i></a>
                          <span><?= htmlentities($item['quantity']); ?></span>
                          <a href="cart.php?action=add&id=<?= $item['id']; ?>"><i class="fas fa-plus"></i></a>
                      </div>
                    </td>
                    <td class="row-total">&#8377;<?= $itemTotal; ?></td>
                    <td>
                        <a href="cart.php?action=remove&id=<?= $item['id']; ?>" class="btn-remove" title="Remove Item">
                            <i class="fas fa-trash-alt"></i>
                        </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
        </div>

        <div class="cart-summary">
            <div class="totals-row">
                <span>Subtotal:</span> <strong id="displaySubtotal">&#8377;<?= number_format($subtotal, 2); ?></strong>
            </div>
            <div class="totals-row">
                <span>Tax (8%):</span> <strong id="displayTax">&#8377;<?= number_format($subtotal * $taxRate, 2); ?></strong>
            </div>
            <div class="totals-row grand-total">
                <span>Total Amount:</span> <strong id="displayTotal">&#8377;<?= number_format($subtotal * (1 + $taxRate), 2); ?></strong>
            </div>
            
            <button type="button" class="btn-checkout" onclick="proceedToCheckout()">
                Proceed to Checkout <i class="fas fa-arrow-right"></i>
            </button>
        </div>

      <?php else: ?>
        <div class="empty-cart">
            <i class="fas fa-shopping-basket"></i>
            <p>Your shopping bag is empty.</p>
            <a href="product.php" class="btn-shop">Start Shopping</a>
        </div>
      <?php endif; ?>
    </div>

</main>

<style>
    /* === YOUR OLD DESIGN COLORS AND STRUCTURE === */
    .cart-container {
        background: #F5F1E9; /* Cream */
        border-radius: 12px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.03);
        border: 1px solid #e0e0e0;
        padding: 30px;
    }

    /* Table */
    table { width: 100%; border-collapse: collapse; min-width: 600px; }
    th { 
        text-align: left; padding: 15px; background: transparent; 
        color: #5E5343; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;
        border-bottom: 2px solid #B2A48C; /* Beige Border */
    }
    td { padding: 20px 15px; border-bottom: 1px solid #ddd; vertical-align: middle; color: #555; }
    
    .product-cell { display: flex; align-items: center; gap: 15px; }
    .product-cell img { width: 50px; height: 50px; object-fit: cover; border-radius: 8px; border: 1px solid #ccc; }
    .no-img { width: 50px; height: 50px; background: #fff; border-radius: 8px; font-size: 10px; display: flex; align-items: center; justify-content: center; }
    .p-name { font-weight: 600; font-size: 15px; color: #5E5343; }

    /* Quantity */
    .qty-control { display: flex; align-items: center; gap: 10px; background: #fff; border-radius: 20px; padding: 5px 10px; width: fit-content; border: 1px solid #ccc; }
    .qty-control a { color: #5E5343; font-size: 12px; transition: 0.3s; }
    .qty-control a:hover { color: #A1937A; }
    .qty-control span { font-weight: 600; font-size: 14px; min-width: 20px; text-align: center; color: #5E5343; }

    .row-total { font-weight: 700; color: #A1937A; } /* Gold/Brown */
    .btn-remove { color: #c0392b; font-size: 16px; transition: 0.3s; }
    .btn-remove:hover { transform: scale(1.1); }

    /* Summary */
    .cart-summary { margin-top: 40px; display: flex; flex-direction: column; align-items: flex-end; }
    .totals-row { display: flex; justify-content: space-between; width: 300px; margin-bottom: 10px; font-size: 14px; color: #5E5343; }
    .grand-total { font-size: 18px; margin-top: 15px; padding-top: 15px; border-top: 2px solid #B2A48C; color: #5E5343; }
    .grand-total strong { color: #A1937A; }

    .btn-checkout {
        margin-top: 25px; padding: 14px 40px; background: #5E5343; color: #fff;
        border: none; border-radius: 50px; font-weight: 600; font-size: 15px; cursor: pointer;
        display: flex; align-items: center; gap: 10px; transition: 0.3s;
    }
    .btn-checkout:hover { background: #A1937A; transform: translateY(-3px); }

    /* Empty State */
    .empty-cart { text-align: center; padding: 60px; color: #999; }
    .empty-cart i { font-size: 50px; margin-bottom: 20px; color: #B2A48C; }
    .btn-shop { display: inline-block; margin-top: 20px; text-decoration: none; color: #5E5343; font-weight: 600; border: 1px solid #5E5343; padding: 10px 25px; border-radius: 30px; transition: 0.3s; }
    .btn-shop:hover { background: #5E5343; color: #fff; }
</style>

<script>
    // Handles the Select All Checkbox
    function toggleAll(source) {
      const checkboxes = document.querySelectorAll('.item-checkbox');
      checkboxes.forEach(cb => cb.checked = source.checked);
      updateTotals();
    }

    // Calculates subtotal dynamically & SAVES Checkbox Status
    function updateTotals() {
      let subtotal = 0;
      const checkboxes = document.querySelectorAll('.item-checkbox:checked');

      checkboxes.forEach(cb => {
        const price = parseFloat(cb.getAttribute('data-price'));
        const quantity = parseInt(cb.getAttribute('data-quantity'));
        subtotal += price * quantity;
      });

      const taxRate = 0.08;
      const tax = subtotal * taxRate;
      const total = subtotal + tax;

      document.getElementById('displaySubtotal').innerHTML = '&#8377;' + subtotal.toFixed(2);
      document.getElementById('displayTax').innerHTML = '&#8377;' + tax.toFixed(2);
      document.getElementById('displayTotal').innerHTML = '&#8377;' + total.toFixed(2);

      // FIX: Save selection state to sessionStorage to prevent losing it on quantity update (page reload)
      const stateObj = {};
      document.querySelectorAll('.item-checkbox').forEach(cb => {
          stateObj[cb.value] = cb.checked;
      });
      sessionStorage.setItem('cartSelection', JSON.stringify(stateObj));

      // Auto-update Select All checkbox status based on individual items
      const allBoxes = document.querySelectorAll('.item-checkbox');
      if (allBoxes.length > 0) {
          const allChecked = Array.from(allBoxes).every(cb => cb.checked);
          document.getElementById('selectAll').checked = allChecked;
      }
    }

    // FIX: Automatically restore the checkbox selection when the page reloads
    document.addEventListener('DOMContentLoaded', function() {
        const savedState = sessionStorage.getItem('cartSelection');
        if (savedState) {
            const stateObj = JSON.parse(savedState);
            document.querySelectorAll('.item-checkbox').forEach(cb => {
                if (stateObj[cb.value] !== undefined) {
                    cb.checked = stateObj[cb.value];
                }
            });
        }
        updateTotals(); // Recalculate using the restored checks and newly updated database quantity
    });

    // Redirects to Checkout passing specifically selected item IDs
    function proceedToCheckout() {
      const checkboxes = document.querySelectorAll('.item-checkbox:checked');
      if (checkboxes.length === 0) {
        alert("Please select at least one item to proceed to checkout.");
        return;
      }

      // Collect IDs of checked items
      const selectedIds = Array.from(checkboxes).map(cb => cb.value).join(',');
      
      // Send the selected IDs to by.php via URL parameters
      window.location.href = 'by.php?type=cart&ids=' + selectedIds;
    }
</script>

<?php include 'include/footer.php'; ?>