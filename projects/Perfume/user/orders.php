<?php
include '../include/connection.php';

session_start();
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
  header('Location: ../login.php');
  exit;
}

$username = $_SESSION['username'] ?? '';
$userId = 0;

// Get User ID
$userSql = "SELECT id FROM users WHERE username=?";
$userStmt = $conn->prepare($userSql);
$userStmt->bind_param('s', $username);
$userStmt->execute();
$userStmt->bind_result($userId);
$userStmt->fetch();
$userStmt->close();

// --- HANDLE CANCEL ENTIRE TRANSACTION ACTION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
  $cancel_txn = $conn->real_escape_string($_POST['txn_id']);
  
  $cancel_reason = trim($_POST['cancel_reason'] ?? '');
  if ($cancel_reason === 'Other') {
      $cancel_reason = trim($_POST['other_reason'] ?? 'No reason provided');
  }
  
  // 1. Fetch all items under this transaction
  $fetchStmt = $conn->prepare("SELECT o.id, o.product_id, o.price AS order_price, p.price AS unit_price FROM orders o JOIN payment pay ON o.id = pay.order_id JOIN product p ON o.product_id = p.id WHERE pay.transaction_id = ? AND o.user_id = ? AND o.status = 'Pending'");
  $fetchStmt->bind_param('si', $cancel_txn, $userId);
  $fetchStmt->execute();
  $fetchRes = $fetchStmt->get_result();

  while ($orderData = $fetchRes->fetch_assoc()) {
      $o_id = $orderData['id'];
      $pid = $orderData['product_id'];
      
      $qty = round($orderData['order_price'] / ($orderData['unit_price'] * 1.08));
      if ($qty < 1) $qty = 1;

      // 2. Cancel Order
      $conn->query("UPDATE orders SET status = 'Cancelled', cancel_reason = '$cancel_reason', cancelled_at = NOW() WHERE id = $o_id");

      // 3. Restore Stock
      $conn->query("UPDATE product SET stock = stock + $qty WHERE id = $pid");
  }
  $fetchStmt->close();

  header('Location: orders.php');
  exit;
}

// --- FETCH GROUPED ORDERS BY TRANSACTION ---
$orders = [];
// FIX: Calculate exact quantity sum, and fetch estimated_delivery_date
$orderSql = "SELECT pay.transaction_id, 
             GROUP_CONCAT(p.name SEPARATOR ', ') AS product_names, 
             SUM(ROUND(o.price / (p.price * 1.08))) as total_items,
             SUM(o.price) AS total_price, 
             MAX(o.created_at) AS created_at, 
             MAX(o.status) AS status,
             MAX(o.estimated_delivery_date) AS estimated_delivery_date
             FROM orders o 
             JOIN product p ON o.product_id = p.id 
             JOIN payment pay ON o.id = pay.order_id 
             WHERE o.user_id=? 
             GROUP BY pay.transaction_id 
             ORDER BY created_at DESC";

$orderStmt = $conn->prepare($orderSql);
$orderStmt->bind_param('i', $userId);
$orderStmt->execute();
$result = $orderStmt->get_result();
while ($row = $result->fetch_assoc()) {
  $orders[] = $row;
}
$orderStmt->close();
$conn->close();

include 'include/header.php';
?>

<main style="flex: 1; padding: 40px; background-color: #fff; overflow-y: auto;">

  <h1 style="font-family: 'Playfair Display', serif; color: #5E5343; margin-bottom: 30px;">
    Order History
  </h1>

  <div class="orders-container">
    <?php if (count($orders) > 0): ?>
      <div class="table-responsive">
        <table>
          <thead>
            <tr>
              <th width="8%">Sr. No.</th>
              <th width="35%">Purchased Items</th>
              <th>Total Price</th>
              <th>Date & Time</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php $counter = 1;
            foreach ($orders as $order):
              $status = !empty($order['status']) ? $order['status'] : 'Pending';
            ?>
              <tr>
                <td><strong style="color: #5E5343; font-size: 14px;"><?= $counter++; ?></strong></td>
                <td>
                  <div class="p-name" style="line-height: 1.4;"><?= htmlentities($order['product_names']); ?></div>
                  <div style="font-size: 11px; color: #999; margin-top: 4px;">Txn ID: <span style="font-family: monospace; color:#555;"><?= htmlentities($order['transaction_id']); ?></span> (<?= $order['total_items']; ?> items)</div>
                </td>
                <td class="price">&#8377;<?= number_format($order['total_price'], 2); ?></td>
                <td>
                  <div style="font-size: 13px; color: #666; font-weight: 500;">
                    <?= date('d-m-Y, h:i A', strtotime($order['created_at'])); ?>
                  </div>
                </td>
                <td>
                  <span class="badge status-<?= $status; ?>"><?= $status; ?></span>
                  
                  <?php if (($status === 'Pending' || $status === 'Shipped') && !empty($order['estimated_delivery_date'])): ?>
                      <div style="font-size: 11px; color: #27ae60; font-weight: 600; margin-top: 6px;">
                          Exp. Delivery: <br>
                          <?= date('d M, Y', strtotime($order['estimated_delivery_date'])); ?>
                      </div>
                  <?php endif; ?>
                </td>
                <td>
                  <a href="orders_view.php?txn_id=<?= $order['transaction_id']; ?>" class="btn-view">
                    View <i class="fas fa-arrow-right"></i>
                  </a>

                  <?php if ($status === 'Pending'): ?>
                    <button type="button" class="btn-cancel" onclick="openCancelModal('<?= $order['transaction_id']; ?>')">
                      Cancel <i class="fas fa-times"></i>
                    </button>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="empty-state">
        <i class="fas fa-box-open"></i>
        <p>You haven't placed any orders yet.</p>
        <a href="product.php" class="btn-shop">Start Shopping</a>
      </div>
    <?php endif; ?>
  </div>

  <div id="cancelModal" class="modal-overlay" style="display: none;">
      <div class="modal-box">
          <h3 style="margin-top: 0; color: #5E5343; font-family: 'Playfair Display', serif; font-size: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px;">Cancel Order</h3>
          <p style="font-size: 13px; color: #666; margin-bottom: 15px;">Please let us know why you are cancelling this transaction. Your refund will be initiated automatically.</p>
          
          <form method="POST" action="orders.php">
              <input type="hidden" name="action" value="cancel">
              <input type="hidden" name="txn_id" id="modal_txn_id" value="">
              
              <select name="cancel_reason" id="cancel_reason" required onchange="toggleOtherReason()" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; font-family: 'Poppins', sans-serif; font-size: 13px; outline: none; background: #fafafa;">
                  <option value="" disabled selected>-- Select a reason --</option>
                  <option value="Ordered by mistake">Ordered by mistake</option>
                  <option value="Found a better price elsewhere">Found a better price elsewhere</option>
                  <option value="Delivery is taking too long">Delivery is taking too long</option>
                  <option value="Changed my mind">Changed my mind</option>
                  <option value="Payment / Discount issue">Payment / Discount issue</option>
                  <option value="Other">Other (Please specify)</option>
              </select>

              <textarea name="other_reason" id="other_reason" style="display: none; width: 100%; box-sizing: border-box; margin-top: 10px; padding: 10px; border: 1px solid #ccc; border-radius: 6px; font-family: 'Poppins', sans-serif; font-size: 13px; resize: vertical;" rows="3" placeholder="Write your specific reason here..."></textarea>

              <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                  <button type="button" onclick="closeCancelModal()" style="background: #eee; color: #555; border: none; padding: 8px 15px; border-radius: 4px; font-size: 12px; font-weight: 600; cursor: pointer;">Keep Order</button>
                  <button type="submit" style="background: #e74c3c; color: #fff; border: none; padding: 8px 15px; border-radius: 4px; font-size: 12px; font-weight: 600; cursor: pointer;">Confirm Cancel</button>
              </div>
          </form>
      </div>
  </div>

</main>

<style>
  .orders-container { background: #F5F1E9; border-radius: 12px; border: 1px solid #e0e0e0; padding: 30px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05); }
  .table-responsive { overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; min-width: 600px; }
  th { background: transparent; color: #5E5343; padding: 15px; text-align: left; font-weight: 700; text-transform: uppercase; font-size: 13px; border-bottom: 2px solid #B2A48C; }
  td { padding: 15px; border-bottom: 1px solid #ddd; color: #555; font-size: 14px; vertical-align: middle; }
  tr:last-child td { border-bottom: none; }
  tbody tr:hover { background-color: #fff; }
  .p-name { font-weight: 600; color: #5E5343; }
  .price { font-weight: 700; color: #A1937A; }
  .badge { padding: 6px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; display: inline-block; }
  .status-Pending { background: #fff8e1; color: #f39c12; border: 1px solid #ffeeba; }
  .status-Shipped { background: #e3f2fd; color: #3498db; border: 1px solid #bbdefb; }
  .status-Delivered { background: #e8f5e9; color: #27ae60; border: 1px solid #c8e6c9; }
  .status-Cancelled { background: #ffebee; color: #c0392b; border: 1px solid #ffcdd2; }
  
  .btn-view { color: #5E5343; text-decoration: none; font-weight: 600; font-size: 13px; padding: 5px 10px; border: 1px solid #5E5343; border-radius: 4px; transition: 0.3s; display: inline-flex; align-items: center; gap: 5px; }
  .btn-view:hover { background: #5E5343; color: #fff; }
  
  .btn-cancel { background: transparent; font-family: inherit; color: #c0392b; text-decoration: none; font-weight: 600; font-size: 13px; padding: 5px 10px; border: 1px solid #c0392b; border-radius: 4px; transition: 0.3s; display: inline-flex; align-items: center; gap: 5px; margin-left: 5px; cursor: pointer; }
  .btn-cancel:hover { background: #c0392b; color: #fff; }
  
  .empty-state { text-align: center; padding: 50px 20px; color: #999; }
  .empty-state i { font-size: 40px; margin-bottom: 15px; color: #B2A48C; }
  .btn-shop { display: inline-block; padding: 10px 25px; background: #1abc9c; color: #fff; text-decoration: none; border-radius: 50px; font-size: 14px; margin-top: 15px; transition: 0.3s; }
  .btn-shop:hover { background: #16a085; }

  .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(2px); }
  .modal-box { background: #fff; padding: 30px; border-radius: 12px; width: 90%; max-width: 400px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); animation: popIn 0.3s ease; }
  @keyframes popIn { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
</style>

<script>
    function openCancelModal(txnId) {
        document.getElementById('cancelModal').style.display = 'flex';
        document.getElementById('modal_txn_id').value = txnId;
    }

    function closeCancelModal() {
        document.getElementById('cancelModal').style.display = 'none';
        document.getElementById('cancel_reason').value = "";
        document.getElementById('other_reason').style.display = 'none';
    }

    function toggleOtherReason() {
        const select = document.getElementById('cancel_reason');
        const otherText = document.getElementById('other_reason');
        if (select.value === 'Other') {
            otherText.style.display = 'block';
            otherText.required = true;
        } else {
            otherText.style.display = 'none';
            otherText.required = false;
        }
    }
</script>

<?php include 'include/footer.php'; ?>