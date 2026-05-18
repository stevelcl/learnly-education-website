<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-shell.php';
require_once __DIR__ . '/includes/csrf.php';

$user = require_admin();
$view = $_GET['view'] ?? 'active';
$statusFilter = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;

$noticeMap = [
    'updated' => 'Order updated successfully.',
    'cancelled' => 'Order cancelled.',
    'archived' => 'Order archived.',
    'deleted' => 'Order removed from the active queue.',
];
$message = $noticeMap[$_GET['notice'] ?? ''] ?? '';

$allowedStatuses = ['pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded', 'archived'];
$allowedViews = ['active', 'history', 'all'];
if (!in_array($view, $allowedViews, true)) {
    $view = 'active';
}
if ($statusFilter !== '' && !in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

$buildRedirect = static function (string $notice) use ($view, $statusFilter, $search, $page): string {
    return app_url_with_query(app_url('admin/orders'), [
        'view' => $view,
        'status' => $statusFilter,
        'q' => $search,
        'page' => $page,
        'notice' => $notice,
    ]);
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $action = $_POST['action'] ?? 'save_status';
    $order = fetch_one('SELECT * FROM orders WHERE id = ?', [$orderId]);

    if ($order) {
        if ($action === 'save_status') {
            $status = $_POST['status'] ?? 'pending';
            if (in_array($status, $allowedStatuses, true)) {
                db()->prepare(
                    'UPDATE orders
                     SET status = ?, deleted_at = NULL
                     WHERE id = ?'
                )->execute([$status, $orderId]);
            }
            header('Location: ' . $buildRedirect('updated'));
            exit;
        }

        if ($action === 'cancel') {
            db()->prepare('UPDATE orders SET status = "cancelled" WHERE id = ?')->execute([$orderId]);
            header('Location: ' . $buildRedirect('cancelled'));
            exit;
        }

        if ($action === 'archive') {
            db()->prepare('UPDATE orders SET status = "archived" WHERE id = ?')->execute([$orderId]);
            header('Location: ' . $buildRedirect('archived'));
            exit;
        }

        if ($action === 'delete') {
            db()->prepare(
                'UPDATE orders
                 SET status = "archived", deleted_at = NOW()
                 WHERE id = ?'
            )->execute([$orderId]);
            header('Location: ' . $buildRedirect('deleted'));
            exit;
        }
    }

    header('Location: ' . $buildRedirect('updated'));
    exit;
}

$where = ['o.deleted_at IS NULL'];
$params = [];

if ($view === 'active') {
    $where[] = 'o.status IN ("pending", "paid", "processing", "shipped")';
}
if ($view === 'history') {
    $where[] = 'o.status IN ("delivered", "cancelled", "refunded", "archived")';
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

$whereSql = 'WHERE ' . implode(' AND ', $where);
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
     ORDER BY o.created_at DESC
     LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset,
    $params
);

$orderItemsByOrder = [];
if ($orders) {
    $orderIds = array_map(static fn(array $order): int => (int) $order['id'], $orders);
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $items = fetch_all(
        'SELECT oi.order_id, oi.quantity, oi.unit_price, COALESCE(oi.book_title, b.title, "Removed book") AS title
         FROM order_items oi
         LEFT JOIN books b ON b.id = oi.book_id
         WHERE oi.order_id IN (' . $placeholders . ')
         ORDER BY oi.order_id, oi.id',
        $orderIds
    );

    foreach ($items as $item) {
        $orderItemsByOrder[(int) $item['order_id']][] = $item;
    }
}

admin_render_start([
    'title' => 'Orders',
    'page_title' => 'Orders',
    'page_subtitle' => 'Manage fulfilment, cancellations, archives, and customer order history from one compact queue.',
    'active_nav' => 'orders',
    'breadcrumbs' => [
        ['label' => 'Dashboard', 'href' => app_url('admin')],
        ['label' => 'Orders'],
    ],
    'notice' => $message,
    'user' => $user,
]);
?>

<section class="panel">
    <form class="admin-filter-bar" method="get">
        <label>Search
            <input name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Order ID, customer, email, or address">
        </label>
        <label>Queue
            <select name="view">
                <option value="active" <?= $view === 'active' ? 'selected' : '' ?>>Active queue</option>
                <option value="history" <?= $view === 'history' ? 'selected' : '' ?>>History</option>
                <option value="all" <?= $view === 'all' ? 'selected' : '' ?>>All orders</option>
            </select>
        </label>
        <label>Status
            <select name="status">
                <option value="">All statuses</option>
                <?php foreach ($allowedStatuses as $statusOption): ?>
                    <option value="<?= htmlspecialchars($statusOption) ?>" <?= $statusFilter === $statusOption ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($statusOption)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="form-actions">
            <button type="submit">Apply</button>
            <a class="button ghost" href="<?= htmlspecialchars(app_url('admin/orders')) ?>">Reset</a>
        </div>
    </form>
</section>

<section class="panel admin-data-table">
    <?php if (!$orders): ?>
        <div class="admin-empty-state"><strong>No orders found</strong><span>Try a broader filter or wait for new checkout activity.</span></div>
    <?php else: ?>
        <table class="admin-compact-table admin-orders-table">
            <colgroup>
                <col class="col-order">
                <col class="col-customer">
                <col class="col-total">
                <col class="col-status">
                <col class="col-date">
                <col class="col-actions">
            </colgroup>
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                    <?php
                    $items = $orderItemsByOrder[(int) $order['id']] ?? [];
                    $orderPayload = [
                        'id' => (int) $order['id'],
                        'customer' => (string) $order['name'],
                        'email' => (string) $order['email'],
                        'status' => ucfirst((string) $order['status']),
                        'total' => 'RM ' . number_format((float) $order['total'], 2),
                        'created_at' => (string) $order['created_at'],
                        'delivery_address' => (string) ($order['delivery_address'] ?: 'Not provided'),
                        'payment_method' => (string) ($order['payment_method'] ?: 'Not provided'),
                        'items' => array_map(static fn(array $item): array => [
                            'title' => (string) $item['title'],
                            'quantity' => (int) $item['quantity'],
                            'price' => 'RM ' . number_format((float) $item['unit_price'], 2),
                        ], $items),
                    ];
                    ?>
                    <tr>
                        <td data-label="Order"><strong>#<?= (int) $order['id'] ?></strong></td>
                        <td data-label="Customer">
                            <div class="admin-cell-stack">
                                <strong><?= htmlspecialchars($order['name']) ?></strong>
                                <span class="muted"><?= htmlspecialchars($order['email']) ?></span>
                            </div>
                        </td>
                        <td data-label="Total">RM <?= number_format((float) $order['total'], 2) ?></td>
                        <td data-label="Status"><span class="status-pill status-<?= htmlspecialchars($order['status']) ?>"><?= htmlspecialchars(ucfirst($order['status'])) ?></span></td>
                        <td data-label="Date"><?= htmlspecialchars((string) $order['created_at']) ?></td>
                        <td data-label="Actions">
                            <div class="admin-order-actions">
                                <form method="post" id="ord-s-<?= (int) $order['id'] ?>" class="admin-order-status-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                    <input type="hidden" name="action" value="save_status">
                                    <select name="status">
                                        <?php foreach ($allowedStatuses as $statusOption): ?>
                                            <option value="<?= htmlspecialchars($statusOption) ?>" <?= $order['status'] === $statusOption ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($statusOption)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                                <div class="admin-order-btn-row">
                                    <button type="button" class="button ghost small" data-order-open='<?= htmlspecialchars(json_encode($orderPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES) ?>'>View Details</button>
                                    <button type="submit" form="ord-s-<?= (int) $order['id'] ?>" class="button ghost small">Save Status</button>
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                        <input type="hidden" name="action" value="cancel">
                                        <button type="submit" class="button ghost small" data-confirm="Cancel this order?">Cancel</button>
                                    </form>
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                        <input type="hidden" name="action" value="archive">
                                        <button type="submit" class="button ghost small" data-confirm="Archive this order from the active queue?">Archive</button>
                                    </form>
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="button danger small" data-confirm="Hide this order from active management views? Order history will remain intact.">Delete</button>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<div class="admin-order-modal" data-order-modal hidden>
    <div class="admin-order-dialog">
        <div class="admin-order-dialog-head">
            <div>
                <p class="eyebrow">Order Details</p>
                <h2 data-order-modal-title>Order</h2>
                <p class="muted" data-order-modal-meta>Customer details</p>
            </div>
            <button type="button" class="button ghost small" data-order-close>Close</button>
        </div>
        <div class="admin-detail-grid admin-order-detail-grid">
            <section class="analytics-detail-section">
                <h3>Customer</h3>
                <p><strong data-order-customer>Customer</strong></p>
                <p class="muted" data-order-email>Email</p>
            </section>
            <section class="analytics-detail-section">
                <h3>Order Summary</h3>
                <p><strong>Status:</strong> <span data-order-status>Status</span></p>
                <p><strong>Total:</strong> <span data-order-total>Total</span></p>
                <p><strong>Date:</strong> <span data-order-date>Date</span></p>
            </section>
            <section class="analytics-detail-section admin-form-span-2">
                <h3>Delivery Address</h3>
                <p data-order-address>Address</p>
            </section>
            <section class="analytics-detail-section admin-form-span-2">
                <h3>Payment Method</h3>
                <p data-order-payment>Payment method</p>
            </section>
            <section class="analytics-detail-section admin-form-span-2">
                <h3>Order Items</h3>
                <div class="admin-order-items-table-shell">
                    <table class="admin-order-items-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Quantity</th>
                                <th>Price</th>
                            </tr>
                        </thead>
                        <tbody data-order-items-body>
                            <tr><td colspan="3" class="muted">No items found for this order.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</div>

<?php if ($totalPages > 1): ?>
    <div class="pagination-bar">
        <?php $baseParams = ['view' => $view, 'status' => $statusFilter, 'q' => $search]; ?>
        <a class="button ghost small <?= $page <= 1 ? 'disabled-link' : '' ?>" href="<?= $page <= 1 ? '#' : htmlspecialchars(app_url('admin/orders') . '?' . http_build_query(array_merge($baseParams, ['page' => $page - 1]))) ?>">Previous</a>
        <div class="pagination-pages">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a class="pagination-page <?= $i === $page ? 'active' : '' ?>" href="<?= htmlspecialchars(app_url('admin/orders') . '?' . http_build_query(array_merge($baseParams, ['page' => $i]))) ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <a class="button ghost small <?= $page >= $totalPages ? 'disabled-link' : '' ?>" href="<?= $page >= $totalPages ? '#' : htmlspecialchars(app_url('admin/orders') . '?' . http_build_query(array_merge($baseParams, ['page' => $page + 1]))) ?>">Next</a>
    </div>
<?php endif; ?>

<?php admin_render_end(); ?>
