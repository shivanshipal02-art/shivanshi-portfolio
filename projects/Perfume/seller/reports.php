<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['seller_logged_in']) || $_SESSION['seller_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

include '../include/connection.php';
$seller_id = $_SESSION['id'];

// Platform Fee Config (e.g., Admin takes 10%)
$platform_fee_percent = 10;

// 1. Fetch Seller Business Name (FIXED: Fetching from 'sellers' table instead of 'users')
$business_name = "Seller";
$u_stmt = $conn->prepare("SELECT business_name, owner_name FROM sellers WHERE id = ?");
$u_stmt->bind_param("i", $seller_id);
$u_stmt->execute();
$u_res = $u_stmt->get_result();
if ($u_row = $u_res->fetch_assoc()) {
    $business_name = !empty($u_row['business_name']) ? $u_row['business_name'] : $u_row['owner_name'];
}
$u_stmt->close();

// 2. Fetch Overall Sales Summary (Only completed/delivered orders)
$total_revenue = 0;
$total_orders = 0;
$sql = "SELECT SUM(o.price) as gross_revenue, COUNT(o.id) as order_count 
        FROM orders o 
        JOIN product p ON o.product_id = p.id 
        WHERE p.seller_id = ? AND (o.status = 'Delivered' OR o.status = 'Shipped')";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$stmt->bind_result($total_revenue, $total_orders);
$stmt->fetch();
$stmt->close();

$total_revenue = $total_revenue ?? 0;
$platform_fee = ($total_revenue * $platform_fee_percent) / 100;
$net_profit = $total_revenue - $platform_fee;

// 3. Fetch Product Performance Data
$product_sales = [];
$p_sql = "SELECT p.name, p.price, COUNT(o.id) as qty_sold, SUM(o.price) as total_generated 
          FROM product p 
          LEFT JOIN orders o ON p.id = o.product_id AND (o.status = 'Delivered' OR o.status = 'Shipped')
          WHERE p.seller_id = ? 
          GROUP BY p.id 
          ORDER BY total_generated DESC";

$p_stmt = $conn->prepare($p_sql);
$p_stmt->bind_param("i", $seller_id);
$p_stmt->execute();
$p_res = $p_stmt->get_result();
while ($row = $p_res->fetch_assoc()) {
    $product_sales[] = $row;
}
$p_stmt->close();

include 'include/header.php';
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<main style="flex: 1; padding: 40px; background-color: #fff; overflow-y: auto;">

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <div>
            <h1 style="font-family: 'Playfair Display', serif; color: #5E5343; margin: 0; font-size: 26px;">Sales & Profit Report</h1>
            <p style="color: #777; font-size: 14px; margin-top: 5px;">Analyze your actual net profit and product performance.</p>
        </div>
        <button onclick="downloadPDF()" class="btn-download" id="downloadBtn">
            <i class="fas fa-file-pdf"></i> Download PDF
        </button>
    </div>

    <div id="pdf-content" class="web-view">

        <div class="pdf-only report-header">
            <div class="brand-title">Perfume Nest</div>
            <div style="text-align: right;">
                <h2 style="margin: 0; color: #5E5343; font-size: 22px;"><?= htmlspecialchars($business_name); ?></h2>
                <p style="margin: 5px 0 0; color: #666; font-size: 13px;">Official Financial Report</p>
                <p style="margin: 5px 0 0; color: #999; font-size: 12px;">Generated on: <?= date('d M Y, h:i A'); ?></p>
            </div>
        </div>

        <h3 class="section-title">Financial Summary</h3>

        <div class="summary-flex">
            <div class="summary-box">
                <div class="icon"><i class="fas fa-box"></i></div>
                <div class="title">Orders Fulfilled</div>
                <div class="value"><?= $total_orders; ?></div>
            </div>
            <div class="summary-box">
                <div class="icon"><i class="fas fa-rupee-sign"></i></div>
                <div class="title">Gross Revenue</div>
                <div class="value">₹<?= number_format($total_revenue, 2); ?></div>
            </div>
            <div class="summary-box fee-box">
                <div class="icon"><i class="fas fa-hand-holding-usd"></i></div>
                <div class="title">Platform Fee (<?= $platform_fee_percent; ?>%)</div>
                <div class="value">- ₹<?= number_format($platform_fee, 2); ?></div>
            </div>
            <div class="summary-box profit-box">
                <div class="icon"><i class="fas fa-wallet"></i></div>
                <div class="title">Net Profit Earned</div>
                <div class="value">₹<?= number_format($net_profit, 2); ?></div>
            </div>
        </div>

        <h3 class="section-title" style="margin-top: 40px;">Product Sales Breakdown</h3>

        <div class="table-card">
            <table class="report-table">
                <thead>
                    <tr>
                        <th style="width: 5%;">#</th>
                        <th style="width: 45%;">Product Name</th>
                        <th style="width: 15%; text-align: center;">Unit Price</th>
                        <th style="width: 15%; text-align: center;">Qty Sold</th>
                        <th style="width: 20%; text-align: right;">Total Generated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($product_sales) > 0): ?>
                        <?php $sr = 1;
                        foreach ($product_sales as $p): ?>
                            <tr>
                                <td><?= $sr++; ?></td>
                                <td><strong><?= htmlspecialchars($p['name']); ?></strong></td>
                                <td style="text-align: center;">₹<?= number_format($p['price']); ?></td>
                                <td style="text-align: center;">
                                    <span class="qty-badge"><?= $p['qty_sold']; ?></span>
                                </td>
                                <td style="text-align: right; font-weight: 700; color: #5E5343;">
                                    ₹<?= number_format($p['total_generated'] ?? 0, 2); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 30px; color: #999;">No completed sales data available yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

</main>

<style>
    /* === WEB VIEW VS PDF MODE LOGIC === */
    .pdf-only {
        display: none;
    }

    /* Hidden by default on the web page */

    /* When generating PDF, this class gets applied */
    .pdf-mode {
        padding: 40px;
        background: #fff;
        color: #000;
    }

    .pdf-mode .pdf-only {
        display: block;
        display: flex;
    }

    .pdf-mode .table-card {
        box-shadow: none;
        border: none;
        padding: 0;
    }

    .pdf-mode .report-table th {
        background-color: #5E5343 !important;
        color: #fff !important;
    }

    /* UI Buttons */
    .btn-download {
        background-color: #c0392b;
        color: #fff;
        border: none;
        padding: 12px 25px;
        border-radius: 50px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 4px 10px rgba(192, 57, 43, 0.2);
    }

    .btn-download:hover {
        background-color: #a93226;
        transform: translateY(-2px);
    }

    .btn-download:disabled {
        opacity: 0.7;
        cursor: not-allowed;
        transform: none;
    }

    /* Headers */
    .section-title {
        font-family: 'Playfair Display', serif;
        font-size: 20px;
        color: #5E5343;
        border-bottom: 2px solid #B2A48C;
        padding-bottom: 8px;
        margin-bottom: 20px;
    }

    /* Summary Stats (Flexbox for better PDF rendering) */
    .summary-flex {
        display: flex;
        gap: 20px;
        margin-bottom: 20px;
    }

    .summary-box {
        flex: 1;
        background: #F5F1E9;
        border: 1px solid #e0e0e0;
        padding: 20px;
        border-radius: 10px;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
    }

    .summary-box .icon {
        font-size: 24px;
        color: #B2A48C;
        margin-bottom: 10px;
    }

    .summary-box .title {
        font-size: 12px;
        text-transform: uppercase;
        color: #666;
        font-weight: 600;
        margin-bottom: 5px;
    }

    .summary-box .value {
        font-size: 22px;
        font-weight: 700;
        color: #5E5343;
    }

    .fee-box {
        background: #ffebee;
        border-color: #ffcdd2;
    }

    .fee-box .icon,
    .fee-box .title,
    .fee-box .value {
        color: #c0392b;
    }

    .profit-box {
        background: #e8f5e9;
        border-color: #c8e6c9;
    }

    .profit-box .icon,
    .profit-box .title,
    .profit-box .value {
        color: #27ae60;
    }

    /* Table Styles */
    .table-card {
        background: #fff;
        border-radius: 12px;
        border: 1px solid #e0e0e0;
        padding: 20px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
    }

    .report-table {
        width: 100%;
        border-collapse: collapse;
    }

    .report-table th {
        text-align: left;
        padding: 15px;
        color: #5E5343;
        font-size: 12px;
        text-transform: uppercase;
        border-bottom: 2px solid #B2A48C;
        font-weight: 700;
        background: #fdfdfd;
    }

    .report-table td {
        padding: 15px;
        border-bottom: 1px solid #eee;
        font-size: 14px;
        color: #555;
        vertical-align: middle;
    }

    .qty-badge {
        background: #F5F1E9;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        color: #5E5343;
        border: 1px solid #B2A48C;
    }

    /* PDF Specific Elements */
    .report-header {
        justify-content: space-between;
        align-items: center;
        border-bottom: 4px solid #5E5343;
        padding-bottom: 20px;
        margin-bottom: 30px;
    }

    .brand-title {
        font-family: 'Playfair Display', serif;
        font-size: 32px;
        font-weight: 700;
        color: #5E5343;
    }

    .report-footer {
        margin-top: 50px;
        padding-top: 20px;
        border-top: 2px dashed #ccc;
        display: block;
    }

    .report-footer h4 {
        margin: 0 0 10px 0;
        font-size: 14px;
        color: #5E5343;
    }

    .report-footer p {
        margin: 0 0 5px 0;
        font-size: 11px;
        color: #777;
        line-height: 1.5;
    }

    .brand-footer {
        text-align: center;
        margin-top: 30px;
        font-weight: bold;
        color: #A1937A;
        font-family: 'Playfair Display', serif;
        font-size: 16px;
    }
</style>

<script>
    function downloadPDF() {
        const btn = document.getElementById('downloadBtn');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
        btn.disabled = true;

        const element = document.getElementById('pdf-content');

        // 1. Add class to reveal PDF-only content and adjust styling for printing
        element.classList.add('pdf-mode');

        // 2. Configure html2pdf settings
        const opt = {
            margin: [0.5, 0.5, 0.5, 0.5], // top, left, bottom, right margins in inches
            filename: '<?= str_replace(" ", "_", $business_name); ?>_Sales_Report.pdf',
            image: {
                type: 'jpeg',
                quality: 1
            },
            html2canvas: {
                scale: 2,
                useCORS: true,
                backgroundColor: '#ffffff'
            },
            jsPDF: {
                unit: 'in',
                format: 'a4',
                orientation: 'portrait'
            }
        };

        // 3. Generate and save
        html2pdf().set(opt).from(element).save().then(() => {
            // 4. Revert back to Web View styling after PDF is downloaded
            element.classList.remove('pdf-mode');
            btn.innerHTML = '<i class="fas fa-file-pdf"></i> Download PDF';
            btn.disabled = false;
        });
    }
</script>

<?php include 'include/footer.php'; ?>