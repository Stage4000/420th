# 420th Delta Dashboard

A PHP-based whitelist management panel with Steam OAuth authentication for the 420th Delta community.

## ✨ New Features

### 🎯 Auto-Whitelist Button
Users can click the "Whitelist Me!" button on their dashboard to automatically receive S3 and CAS roles. Once whitelisted, the button is replaced with a confirmation badge showing role aliases.

### 🏷️ Role Aliases
Admins can set custom display names (aliases) for any role in the admin panel using a single "Save All Aliases" button. When an alias is set, it will be shown to users instead of the default role name.

### 🚀 PHP Installer
First-run installation wizard that:
- Collects database credentials
- Sets up the database schema automatically
- Configures Steam API key
- Makes the first Steam login a PANEL administrator
- No manual SQL imports needed!

### 🔗 Automatic Role Linking
Smart role management that automatically maintains role relationships:
- Staff roles (ADMIN, MODERATOR, DEVELOPER) automatically receive the ALL role
- Removing ALL role also removes all staff roles
- Includes sync button to fix existing data

### 🌙 Dark Mode Theme
Professional dark theme with 420th Delta logo integration across all pages.

### 👥 Enhanced User Management
- Separate user management page with search functionality
- Pagination for large user lists (20 per page)
- Quick role assignment modal

### 🚫 Ban Management System
- Whitelist bans for S3, CAS, or both roles
- Temporary or permanent bans with expiration dates
- Ban history and audit trail
- **New: Server kicks and bans via RCON**

### 🎮 Arma 3 Server Integration (New!)
- BattlEye RCON support for server control
- Kick players from game server
- Ban players from game server (permanent)
- Combined whitelist and server bans
- Configurable RCON settings in admin panel
- Connection testing and status monitoring

### ⚡ Optimized Database
Restructured database using **boolean columns** for roles instead of junction table:
- Single query to fetch user roles (no JOINs needed)
- Faster role checks and assignments
- Migration script included for existing installations

## Features

- **Steam OAuth Login**: Secure authentication using Steam OpenID
- **Role-Based Access Control**: Support for 12 whitelist roles
- **User Dashboard**: View assigned whitelist roles with custom aliases
- **Admin Panel**: Manage user roles and aliases (requires PANEL role)
- **Ban Management**: Issue whitelist and server bans with RCON support
- **Arma 3 RCON**: Kick and ban players from game server via BattlEye
- **Optimized Database**: Boolean columns for fast role queries
- **Database-Driven**: MySQL/MariaDB backend for persistent storage

## Supported Roles

The system supports the following whitelist roles:

- **S3**: S3 personnel
- **CAS**: Close Air Support personnel
- **S1**: S1 personnel
- **OPFOR**: Opposing Force personnel
- **ALL**: Staff role (all staff should have this in addition to their actual staff role)
- **ADMIN**: Administrator with elevated privileges
- **MODERATOR**: Moderator with moderation privileges
- **TRUSTED**: Trusted community member
- **MEDIA**: Media team member
- **CURATOR**: Content curator
- **DEVELOPER**: Developer team member
- **PANEL**: Panel administrator with user management rights

## Database Structure

The new optimized schema stores roles as boolean columns in the users table:
- `role_s3`, `role_cas`, `role_s1`, `role_opfor`
- `role_all`, `role_admin`, `role_moderator`, `role_trusted`
- `role_media`, `role_curator`, `role_developer`, `role_panel`

The `roles` table is kept for managing aliases and display names.

## Installation

### Option 1: Automated Installation (Recommended)

1. Upload all files to your web server
2. Navigate to your installation URL (e.g., `https://yourdomain.com/`)
3. Follow the installation wizard:
   - Enter database credentials
   - Enter Steam API key
   - Click "Complete Installation"
4. Log in with Steam to create your admin account
5. Done! You're automatically granted the PANEL role

### Option 2: Manual Installation

### Prerequisites

- PHP 7.4 or higher
- MySQL 5.7+ or MariaDB 10.2+
- Composer (for installing dependencies)
- Web server (Apache, Nginx, etc.)
- Steam API Key ([Get one here](https://steamcommunity.com/dev/apikey))
- (Optional) Arma 3 server with BattlEye RCON enabled

### Setup Steps

#### Option 1: Automated Installation (Recommended)

1. **Clone the repository**
   ```bash
   git clone https://github.com/Stage4000/420th.git
   cd 420th
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Upload to your web server**
   - Upload all files to your web server's document root or subdirectory

4. **Run the installer**
   - Navigate to your installation URL (e.g., `http://yourdomain.com`)
   - The installer will automatically detect first run and guide you through setup
   - Provide database credentials and Steam API key
   - Log in with Steam to create your admin account
   - Done! The first user automatically gets PANEL administrator role

5. **(Optional) Configure RCON**
   - See the INSTALL.md file for detailed RCON setup instructions
   - Configure BattlEye RCON on your Arma 3 server
   - Add RCON credentials in the admin panel

#### Option 2: Manual Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/Stage4000/420th.git
   cd 420th
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Create the database**
   ```bash
   mysql -u root -p < database.sql
   ```
   
   Or import `database.sql` using phpMyAdmin or your preferred database management tool.

3. **Configure the application**
   
   Edit `config.php` and update the following:
   
   - **Database Configuration**:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_NAME', '420th_whitelist');
     define('DB_USER', 'your_db_user');
     define('DB_PASS', 'your_db_password');
     ```
   
   - **Steam API Configuration**:
     ```php
     define('STEAM_API_KEY', 'your_steam_api_key_here');
     define('STEAM_RETURN_URL', 'http://yourdomain.com/callback.php');
     ```

4. **Set up environment variables (optional)**
   
   Instead of editing `config.php`, you can use environment variables:
   ```bash
   export DB_HOST=localhost
   export DB_NAME=420th_whitelist
   export DB_USER=your_db_user
   export DB_PASS=your_db_password
   export STEAM_API_KEY=your_steam_api_key
   export STEAM_RETURN_URL=http://yourdomain.com/callback.php
   ```

5. **Configure your web server**
   
   Point your web server's document root to the repository directory.
   
   **Apache Example (.htaccess)**:
   ```apache
   <IfModule mod_rewrite.c>
       RewriteEngine On
       RewriteBase /
       
       # Redirect to HTTPS
       RewriteCond %{HTTPS} off
       RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   </IfModule>
   ```
   
   **Nginx Example**:
   ```nginx
   server {
       listen 80;
       server_name yourdomain.com;
       root /path/to/420th;
       index index.php;
       
       location / {
           try_files $uri $uri/ /index.php?$query_string;
       }
       
       location ~ \.php$ {
           fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
           fastcgi_index index.php;
           fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
           include fastcgi_params;
       }
   }
   ```

6. **Set proper permissions**
   ```bash
   chmod 644 *.php
   chmod 600 config.php  # Protect configuration file
   ```

7. **Create initial admin user**
   
   After first login, manually grant PANEL role to your Steam account:
   ```sql
   -- Find your user ID
   SELECT id FROM users WHERE steam_id = 'YOUR_STEAM_ID';
   
   -- Grant PANEL role using boolean column
   UPDATE users SET role_panel = 1 WHERE steam_id = 'YOUR_STEAM_ID';
   ```

## Migrating from Old Schema (Junction Table)

If you have an existing installation using the old `user_roles` junction table, use the migration script:

1. **Backup your database**
   ```bash
   mysqldump -u root -p 420th_whitelist > backup_$(date +%Y%m%d).sql
   ```

2. **Run the migration script**
   ```bash
   php migrate_to_boolean_roles.php
   ```
   
   The script will:
   - Add boolean role columns to the users table
   - Migrate all existing role assignments
   - Backup the old `user_roles` table with timestamp
   - Verify the migration was successful

3. **Test the migration**
   - Log in and verify all users still have their correct roles
   - Check the admin panel to ensure role management works
   - Verify automatic role linking (staff roles get ALL role)

4. **Clean up (optional)**
   ```sql
   -- After confirming migration success, you can drop the backup table
   DROP TABLE user_roles_backup_YYYYMMDD_HHMMSS;
   ```

## Usage

### For Users

1. Navigate to the dashboard URL (e.g., `https://yourdomain.com`)
2. Click "Sign in with Steam"
3. Authorize the application through Steam
4. View your assigned whitelist roles on the dashboard
5. **New:** Click "Whitelist Me!" to automatically get S3 and CAS roles if you don't have them yet

### For Admins

1. Log in with a PANEL role account
2. Click "Admin Panel" from the navigation bar
3. **Manage Role Aliases:**
   - Scroll to the "Role Aliases" section
   - Enter custom display names for any role
   - Click "💾 Save All Aliases" to save all changes at once
   - Users will see the alias instead of the default name everywhere
4. **Manage User Roles:**
   - Click "Manage Users" to see the user list with search and pagination
   - Or view recent users on the main admin panel page
   - Click "Manage Roles" for any user
   - Select/deselect roles using checkboxes
   - Click "Save Changes"
   - **Note:** Staff roles (ADMIN, MODERATOR, DEVELOPER) automatically get the ALL role
5. **Sync Staff Roles:**
   - Use the "🔄 Sync Staff Roles" button to fix any users missing the ALL role
   - This ensures all staff members have the ALL role assigned
6. **Configure RCON (Optional):**
   - Scroll to "Arma 3 Server RCON Configuration"
   - Enable RCON and enter your server details
   - Test the connection
   - Now you can kick/ban players from the game server via the user management page

## File Structure

```
420th/
├── composer.json       # PHP dependencies
├── vendor/             # Composer dependencies (RCON library)
├── config.php          # Configuration file
├── database.sql        # Database schema
├── db.php             # Database connection handler
├── steam_auth.php     # Steam OAuth authentication
├── ban_manager.php    # Ban management class
├── role_manager.php   # Role management class
├── rcon_manager.php   # RCON management class (New!)
├── index.php          # Login page
├── callback.php       # OAuth callback handler
├── dashboard.php      # User dashboard
├── admin.php          # Admin panel with RCON config
├── users.php          # User management with ban/kick
├── logout.php         # Logout handler
├── migrate_add_rcon_settings.php  # RCON migration script
└── README.md          # This file
```

## Security Considerations

- **HTTPS Required**: Always use HTTPS in production
- **Secure Configuration**: Keep `config.php` outside the web root or protect it with `.htaccess`
- **Database Security**: Use strong passwords and limit database user privileges
- **Session Security**: Sessions are configured with secure settings
- **Input Validation**: All user inputs are sanitized and validated
- **Prepared Statements**: All database queries use prepared statements to prevent SQL injection
- **RCON Security**: Keep RCON password secure and restrict port access via firewall
- **Composer Dependencies**: Keep dependencies updated with `composer update`

## Troubleshooting

### "Database connection failed"
- Check database credentials in `config.php`
- Ensure MySQL/MariaDB is running
- Verify the database exists

### "Steam authentication failed"
- Check Steam API key is valid
- Verify `STEAM_RETURN_URL` matches your domain
- Ensure your server can make outbound HTTPS requests

### "No roles assigned"
- Roles must be manually assigned by a PANEL admin
- Check the database to ensure roles exist in the `roles` table

### "RCON connection failed"
- Verify BattlEye RCON is enabled on your Arma 3 server
- Check RConPort and RConPassword in beserver.cfg
- Ensure RCON port is not blocked by firewall
- Test connectivity with `telnet server_ip rcon_port` from your web server

### "Composer command not found"
- Install Composer: https://getcomposer.org/download/
- Or use `php composer.phar` instead of `composer`

## Contributing

Contributions are welcome! Please open an issue or submit a pull request.

## License

This project is provided as-is for the 420th Delta community.

## Support

For issues or questions, please contact your system administrator or open an issue on GitHub.
