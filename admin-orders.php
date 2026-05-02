<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
$user = require_admin();

$view = $_GET['view'] ?? 'active';
$statusFilter = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 8;

$messageMap = [
    'updated' => 'Order status updated.',
];
$message = $messageMap[$_GET['notice'] ?? ''] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $status = $_POST['status'] ?? 'processing';
    $allowed = ['paid', 'processing', 'shipped', 'delivered', 'cancelled'];

    if (in_array($status, $allowed, true)) {
        $stmt = db()->prepare('UPDATE orders SET status = ? WHERE id = ?');
        $stmt->execute([$status, $orderId]);
    }

    $redirectParams = [
        'view' => $_GET['view'] ?? 'active',
        'status' => $_GET['status'] ?? '',
        'q' => $_GET['q'] ?? '',
        'page' => $_GET['page'] ?? 1,
        'notice' => 'updated',
    ];
    header('Location: admin-orders.php?' . http_build_query($redirectParams));
    exit;
}

$allowedViews = ['active', 'history', 'all'];
if (!in_array($view, $allowedViews, true)) {
    $view = 'active';
}

$allowedStatuses = ['paid', 'processing', 'shipped', 'delivered', 'cancelled'];
if ($statusFilter !== '' && !in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

$where = [];
$params = [];

if ($view === 'active') {
    $where[] = 'o.status IN ("paid", "processing", "shipped")';
}
if ($view === 'history') {
    $where[] = 'o.status IN ("delivered", "cancelled")';
}
if ($statusFilter !== '') {
    $where[] = 'o.status = ?';
    $params[] = $statusFilter;
}
if ($search !== '') {
    if (ctype_digit($search)) {
        $where[] = '(o.id = ? OR u.name LIKE ? OR u.email LIKE ? OR o.delivery_address LIKE ?)';
        $params[] = (int) $search;
    } else {
        $where[] = '(u.name LIKE ? OR u.email LIKE ? OR o.delivery_address LIKE ?)';
    }
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$totalRows = (int) (fetch_one(
    'SELECT COUNT(*) AS total
     FROM orders o
     JOIN users u ON u.id = o.user_id
     ' . $whereSql,
    $params
)['total'] ?? 0);

$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$orders = fetch_all(
    'SELECT o.id, o.total, o.status, o.delivery_address, o.payment_method, o.created_at, u.name, u.email
     FROM orders o
     JOIN users u ON u.id = o.user_id
     ' . $whereSql . '
     ORDER BY
        CASE
            WHEN o.status = "processing" THEN 1
            WHEN o.status = "paid" THEN 2
            WHEN o.status = "shipped" THEN 3
            WHEN o.status = "delivered" THEN 4
            WHEN o.status = "cancelled" THEN 5
            ELSE 6
        END,
        o.created_at DESC
     LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset,
    $params
);

$orderItemsByOrder = [];
if ($orders) {
    $orderIds = array_map(static fn(array $order): int => (int) $order['id'], $orders);
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $items = fetch_all(
        'SELECT oi.order_id, oi.quantity, oi.unit_price, b.title
         FROM order_items oi
         JOIN books b ON b.id = oi.book_id
         WHERE oi.order_id IN (' . $placeholders . ')
         ORDER BY oi.order_id, oi.id',
        $orderIds
    );

    foreach ($items as $item) {
        $orderItemsByOrder[(int) $item['order_id']][] = $item;
    }
}

$pageTitle = 'Manage Orders';
include __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container">
        <div class="section-head">
            <div>
                <span class="eyebrow">Admin Orders</span>
                <h1>Track purchases and run fulfilment like a proper storefront.</h1>
            </div>
            <a class="button ghost" href="admin-dashboard.php">Back to Admin</a>
        </div>
        <?php if ($message): ?><p class="alert success"><?= htmlspecialchars($message) ?></p><?php endif; ?>

        <form class="panel order-filter-form" method="get">
            <label>Search
                <input name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Order ID, customer name, email, or address">
            </label>
            <label>View
                <select name="view">
                    <option value="active" <?= $view === 'active' ? 'selected' : '' ?>>Active Queue</option>
                    <option value="history" <?= $view === 'history' ? 'selected' : '' ?>>History</option>
                    <option value="all" <?= $view === 'all' ? 'selected' : '' ?>>All Orders</option>
                </select>
            </label>
            <label>Status
                <select name="status">
                    <option value="">All statuses</option>
                    <?php foreach ($allowedStatuses as $statusOption): ?>
                        <option value="<?= htmlspecialchars($statusOption) ?>" <?= $statusFilter === $statusOption ? 'selected' : '' ?>>
                            <?= htmlspecialchars(ucfirst($statusOption)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="form-actions">
                <button type="submit">Apply</button>
                <a class="button ghost" href="admin-orders.php">Reset</a>
            </div>
        </form>

        <div class="section-head compact">
            <div>
                <span class="eyebrow"><?= htmlspecialchars(ucfirst($view)) ?></span>
                <h2><?= $totalRows ?> order<?= $totalRows === 1 ? '' : 's' ?> matched</h2>
            </div>
            <p class="muted">
                <?php if ($totalRows > 0): ?>
                    Showing <?= $offset + 1 ?>-<?= min($offset + $perPage, $totalRows) ?> of <?= $totalRows ?>
                <?php else: ?>
                    No orders match the current filters.
                <?php endif; ?>
            </p>
        </div>

        <div class="order-board">
            <?php foreach ($orders as $order): ?>
                <?php $items = $orderItemsByOrder[(int) $order['id']] ?? []; ?>
                <article class="order-card">
                    <div class="order-card-head">
                        <div>
                            <h3>Order #<?= (int) $order['id'] ?></h3>
                            <p class="muted"><?= htmlspecialchars($order['created_at']) ?></p>
                        </div>
                        <span class="status-pill status-<?= htmlspecialchars($order['status']) ?>"><?= htmlspecialchars(ucfirst($order['status'])) ?></span>
                    </div>

                    <div class="order-card-grid">
                        <div>
                            <strong>Customer</strong>
                            <p><?= htmlspecialchars($order['name']) ?><br><span class="muted"><?= htmlspecialchars($order['email']) ?></span></p>
                        </div>
                        <div>
                            <strong>Delivery Address</strong>
                            <p><?= nl2br(htmlspecialchars((string) ($order['delivery_address'] ?: 'Not provided'))) ?></p>
                        </div>
                        <div>
                            <strong>Payment</strong>
                            <p><?= htmlspecialchars((string) ($order['payment_method'] ?: 'Not provided')) ?></p>
                        </div>
                        <div>
                            <strong>Total</strong>
                            <p>RM <?= number_format((float) $order['total'], 2) ?></p>
                        </div>
                    </div>

                    <div class="order-items-panel">
                        <strong>Order Items</strong>
                        <?php if (!$items): ?>
                            <p class="muted">No order items found.</p>
                        <?php else: ?>
                            <div class="order-items-list">
                                <?php foreach ($items as $item): ?>
                                    <div class="order-item-row">
                                        <span><?= htmlspecialchars($item['title']) ?></span>
                                        <span class="muted">Qty <?= (int) $item['quantity'] ?></span>
                                        <span>RM <?= number_format((float) $item['unit_price'], 2) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <form method="post" class="inline-form order-action-row">
                        <?= csrf_field() ?>
                        <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                        <select name="status">
                            <option value="paid" <?= $order['status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                            <option value="processing" <?= $order['status'] === 'processing' ? 'selected' : '' ?>>Processing</option>
                            <option value="shipped" <?= $order['status'] === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                            <option value="delivered" <?= $order['status'] === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                            <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                        <button type="submit">Save Status</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="pagination-bar">
                <?php
                $baseParams = ['view' => $view, 'status' => $statusFilter, 'q' => $search];
                $prevParams = http_build_query(array_merge($baseParams, ['page' => max(1, $page - 1)]));
                $nextParams = http_build_query(array_merge($baseParams, ['page' => min($totalPages, $page + 1)]));
                ?>
                <a class="button ghost small <?= $page <= 1 ? 'disabled-link' : '' ?>" href="<?= $page <= 1 ? '#' : 'admin-orders.php?' . $prevParams ?>">Previous</a>
                <div class="pagination-pages">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a class="pagination-page <?= $i === $page ? 'active' : '' ?>" href="admin-orders.php?<?= http_build_query(array_merge($baseParams, ['page' => $i])) ?>"><?= $i ?></a>
                    <?php endfor; ?>
                </div>
                <a class="button ghost small <?= $page >= $totalPages ? 'disabled-link' : '' ?>" href="<?= $page >= $totalPages ? '#' : 'admin-orders.php?' . $nextParams ?>">Next</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
