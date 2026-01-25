# New Features Added

## 1. Active Players Page (RCON)
**File:** `active_players.php`
**Access:** ADMIN role only

### Features:
- Real-time display of online players from game server via RCON
- Shows player name, Steam ID, playtime, ping
- Database integration - identifies known vs guest players
- Punitive action indicators:
  - 🚫 Ban history count
  - 📝 Staff notes count
- Actions available:
  - **Kick** - Remove player from server temporarily
  - **Ban** - Ban player with options for whitelist type, duration, and server ban
  - **Notes** - View and add staff notes

### Navigation:
- Available from Dashboard (for ADMIN role users)
- Available from Admin Panel
- Available from Users page
- Available from Ban Management page

## 2. Ban Management Page
**File:** `ban_management.php`
**Access:** PANEL or ALL (Staff) role

### Features:
- Comprehensive view of all bans (active and expired)
- Search by player name or Steam ID
- Filter by status:
  - All Bans
  - Active Only
  - Expired Only
- Displays:
  - Player info with avatar
  - Ban type (S3, CAS, or BOTH)
  - Server action indicators (kick/ban)
  - Ban reason
  - Who issued the ban
  - Ban date and expiration
  - Current status
  - Staff notes count
- Unban functionality for active bans
- Pagination for large ban lists

### Navigation:
- Available from Dashboard (for PANEL/ALL role users)
- Available from Admin Panel
- Available from Users page
- Available from Active Players page

## 3. Staff Notes System
**Files:** 
- `staff_notes_manager.php` (class)
- `migrate_add_staff_notes.php` (migration)
- Database table: `staff_notes`

### Features:
- Add, view staff notes on any user
- Track punitive actions and important information
- Shows note author with avatar
- Timestamps for created and updated
- Integrated into:
  - Users page
  - Active Players page
  - Ban Management page (count display)
- Only visible to staff (ALL role)

### Migration:
Run `php migrate_add_staff_notes.php` to create the staff_notes table.

## 4. Punitive Action Indicators
Visual indicators showing user history across all user lists:

### Indicators:
- **🚫 Ban Count** - Shows number of previous bans (red badge)
- **📝 Note Count** - Shows number of staff notes (blue/orange badge)

### Locations:
- Users page - in user list
- Active Players page - in player list
- Ban Management page - in banned users list

### Tooltip:
Hover over badges to see the count

## Installation

1. Run the migration to create the staff_notes table:
   ```bash
   php migrate_add_staff_notes.php
   ```

2. Ensure RCON is configured in Admin Panel for Active Players functionality

3. Grant ADMIN role to users who should access Active Players page

4. Grant ALL role to staff who should manage bans and notes

## Database Changes

### New Table: staff_notes
```sql
CREATE TABLE `staff_notes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `note_text` TEXT NOT NULL,
    `created_by_user_id` INT NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_by_user_id` INT NULL,
    `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`updated_by_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Security

- Active Players page restricted to ADMIN role
- Ban Management accessible to PANEL or ALL roles
- Staff notes only visible/editable by ALL role
- All inputs sanitized and validated
- AJAX requests use CSRF protection via session validation
- SQL queries use prepared statements

## Dependencies

- Existing RCON functionality (`rcon_manager.php`)
- Existing ban system (`ban_manager.php`)
- PHP 7.4+
- MySQL/MariaDB with existing schema
