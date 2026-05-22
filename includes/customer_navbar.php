<?php
if (!function_exists('render_customer_navbar')) {
    function render_customer_navbar($active_page = 'order') {
        $is_profile = $active_page === 'profile';
        $is_order = $active_page === 'order';
        $is_history = $active_page === 'history';
        ?>
        <nav class="navbar navbar-expand-lg bg-primary navbar-dark shadow-sm customer-topbar">
            <div class="container d-flex flex-column flex-lg-row align-items-lg-center">
                <a href="dashboard.php" class="navbar-brand fw-bold">ISRAPHIL</a>
                <div class="ms-lg-auto d-flex flex-wrap align-items-center justify-content-center justify-content-lg-end gap-2 gap-sm-3 text-white customer-topbar-actions">
                    <div class="dropdown customer-account-menu">
                        <button class="btn btn-light btn-sm customer-menu-button" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Open customer menu" title="Menu">
                            <span class="customer-menu-icon" aria-hidden="true">&#9776;</span>
                            <span class="customer-menu-text">Menu</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end customer-account-dropdown">
                            <li><h2 class="dropdown-header">Account</h2></li>
                            <li><a class="dropdown-item<?php echo $is_profile ? ' active' : ''; ?>" href="profile.php"<?php echo $is_profile ? ' aria-current="page"' : ''; ?>>My Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item<?php echo $is_order ? ' active' : ''; ?>" href="dashboard.php"<?php echo $is_order ? ' aria-current="page"' : ''; ?>>Place Order</a></li>
                            <li><a class="dropdown-item<?php echo $is_history ? ' active' : ''; ?>" href="history.php"<?php echo $is_history ? ' aria-current="page"' : ''; ?>>Order History</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="../../logout.php">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>
        <?php
    }
}
