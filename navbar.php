<?php
/**
 * Navbar Template
 * 
 * Required variables:
 * - $currentPage: The current page identifier (e.g., 'dashboard', 'users', 'admin', etc.)
 * - $pageTitle: The title to display in the navbar
 * - $user: User data array with 'avatar_url' and 'steam_name'
 * - $isPanelAdmin: Boolean indicating if user is a panel admin (optional)
 */

// Ensure required variables are set
if (!isset($currentPage)) {
    $currentPage = '';
}
if (!isset($pageTitle)) {
    $pageTitle = '420th Delta';
}
if (!isset($user)) {
    $user = ['avatar_url' => '', 'steam_name' => 'Guest'];
}
if (!isset($isPanelAdmin)) {
    $isPanelAdmin = false;
}
?>
<nav class="navbar">
    <div class="navbar-brand">
        <img src="https://www.420thdelta.net/uploads/monthly_2025_11/banner.png.2aa9557dda39e6c5ba0e3c740df490ee.png" 
             alt="420th Delta" 
             class="navbar-logo"
             onerror="this.style.display='none';">
        <span class="navbar-title"><?php echo htmlspecialchars($pageTitle); ?></span>
    </div>
    <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">☰</button>
    <div class="navbar-links" id="navbarLinks">
        <a href="dashboard" <?php echo $currentPage === 'dashboard' ? 'class="active"' : ''; ?>>Dashboard</a>
        <?php if ($isPanelAdmin): ?>
            <a href="admin" <?php echo $currentPage === 'admin' ? 'class="active"' : ''; ?>>Admin Panel</a>
            <a href="users" <?php echo $currentPage === 'users' ? 'class="active"' : ''; ?>>Users</a>
        <?php endif; ?>
        <?php if ($isPanelAdmin || SteamAuth::hasRole('ALL')): ?>
            <a href="ban_management" <?php echo $currentPage === 'ban_management' ? 'class="active"' : ''; ?>>Bans</a>
        <?php endif; ?>
        <?php if (SteamAuth::hasRole('ADMIN')): ?>
            <a href="active_players" <?php echo $currentPage === 'active_players' ? 'class="active"' : ''; ?>>Active Players</a>
        <?php endif; ?>
        <a href="leaderboards" <?php echo $currentPage === 'leaderboards' ? 'class="active"' : ''; ?>>Leaderboards</a>
        <img src="<?php echo htmlspecialchars($user['avatar_url']); ?>" alt="Avatar" class="user-avatar">
        <span><?php echo htmlspecialchars($user['steam_name']); ?></span>
        <a href="logout" class="logout-btn">Logout</a>
    </div>
</nav>

<script>
    function toggleMobileMenu() {
        const navbarLinks = document.getElementById('navbarLinks');
        navbarLinks.classList.toggle('active');
    }
</script>
