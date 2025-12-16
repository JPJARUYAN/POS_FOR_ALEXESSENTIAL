<?php
// Determine the rendering user based on the page context so admin and cashier
// pages show their appropriate menus even when both roles are logged in.
$roleContext = isset($GLOBALS['CURRENT_ROLE_CONTEXT']) ? $GLOBALS['CURRENT_ROLE_CONTEXT'] : null;

if ($roleContext === ROLE_ADMIN) {
  $currentUser = User::getAuthenticatedUser(ROLE_ADMIN);
} elseif ($roleContext === ROLE_CASHIER) {
  $currentUser = User::getAuthenticatedUser(ROLE_CASHIER);
} else {
  // fallback: prefer admin then cashier
  $currentUser = User::getAuthenticatedUser();
}
?>
<aside>
  <div class="nav-links">
    <?php if ($roleContext === ROLE_CASHIER): ?>

      <?php if ($currentUser): ?>
        <a href="index.php" class="nav-links-item">
          <svg xmlns="http://www.w3.org/2000/svg" class="icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
          </svg>
          <span>POS</span>
        </a>
        <a href="cashier_sales.php" class="nav-links-item">
          <svg xmlns="http://www.w3.org/2000/svg" class="icon" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 14.586 7H12z" clip-rule="evenodd" />
          </svg>
          <span>Sales History</span>
        </a>
        <a href="api/logout_controller.php?role=CASHIER" class="nav-links-item">
          <svg xmlns="http://www.w3.org/2000/svg" class="icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
          </svg>
          <span>Logout</span>
        </a>
      <?php endif; ?>

    <?php elseif ($roleContext === ROLE_ADMIN): ?>

        <?php if ($currentUser): ?>
        <a href="admin_dashboard.php" class="nav-links-item">
          <svg xmlns="http://www.w3.org/2000/svg" class="icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h7v7H3V3zm11 0h7v4h-7V3zM3 14h7v7H3v-7zm11 10h7v-10h-7v10z" />
          </svg>
          <span>Dashboard</span>
        </a>
        <a href="admin_home.php" class="nav-links-item">
          <svg xmlns="http://www.w3.org/2000/svg" class="icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
          </svg>
          <span>Inventory</span>
        </a>


        <a href="admin_suppliers.php" class="nav-links-item">
          <svg xmlns="http://www.w3.org/2000/svg" class="icon" viewBox="0 0 20 20" fill="currentColor">
            <path d="M10 2a6 6 0 100 12 6 6 0 000-12zM2 18a8 8 0 0116 0H2z" />
          </svg>
          <span>Suppliers</span>
        </a>

        <a href="admin_sales.php" class="nav-links-item">
          <svg xmlns="http://www.w3.org/2000/svg" class="icon" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 14.586 7H12z" clip-rule="evenodd" />
          </svg>
          <span>Sales</span>
        </a>

        <a href="admin_staff_performance.php" class="nav-links-item">
          <svg xmlns="http://www.w3.org/2000/svg" class="icon" viewBox="0 0 20 20" fill="currentColor">
            <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0z" />
            <path fill-rule="evenodd" d="M18 8a2 2 0 11-4 0 2 2 0 014 0z" clip-rule="evenodd" />
            <path fill-rule="evenodd" d="M14 15a4 4 0 00-8 0v2h8v-2z" clip-rule="evenodd" />
            <path d="M6 8a2 2 0 11-4 0 2 2 0 014 0z" />
          </svg>
          <span>Staff Performance</span>
        </a>

        <a href="admin_users.php" class="nav-links-item">
          <svg xmlns="http://www.w3.org/2000/svg" class="icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.856-1.487M15 10a3 3 0 11-6 0 3 3 0 016 0z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M16 20a3 3 0 01-6 0" />
          </svg>
          <span>Users</span>
        </a>

        <a href="api/logout_controller.php?role=ADMIN" class="nav-links-item">
          <svg xmlns="http://www.w3.org/2000/svg" class="icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
          </svg>
          <span>Logout</span>
        </a>
      <?php endif; ?>

    <?php else: ?>

      <!-- No role context: show nothing or a basic link -->

    <?php endif; ?>

    </div>
</aside>