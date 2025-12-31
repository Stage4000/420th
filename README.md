# 420th Delta Dashboard

A PHP-based whitelist management panel with Steam OAuth authentication for the 420th Delta community.

## ✨ New Features

### 🎯 Auto-Whitelist Button
Users can click the "Whitelist Me!" button on their dashboard to automatically receive S3 and CAS roles. Once whitelisted, the button is replaced with a confirmation badge.

### 🏷️ Role Aliases
Admins can set custom display names (aliases) for any role in the admin panel. When an alias is set, it will be shown to users instead of the default role name.

### 🚀 PHP Installer
First-run installation wizard that:
- Collects database credentials
- Sets up the database schema automatically
- Configures Steam API key
- Makes the first Steam login a PANEL administrator
- No manual SQL imports needed!

## Features

- **Steam OAuth Login**: Secure authentication using Steam OpenID
- **Role-Based Access Control**: Support for multiple whitelist roles
- **User Dashboard**: View assigned whitelist roles
- **Admin Panel**: Manage user roles (requires PANEL role)
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

## Installation

### Prerequisites

- PHP 7.4 or higher
- MySQL 5.7+ or MariaDB 10.2+
- Web server (Apache, Nginx, etc.)
- Steam API Key ([Get one here](https://steamcommunity.com/dev/apikey))

### Setup Steps

#### Option 1: Automated Installation (Recommended)

1. **Clone the repository**
   ```bash
   git clone https://github.com/Stage4000/420th.git
   cd 420th
   ```

2. **Upload to your web server**
   - Upload all files to your web server's document root or subdirectory

3. **Run the installer**
   - Navigate to your installation URL (e.g., `http://yourdomain.com`)
   - The installer will automatically detect first run and guide you through setup
   - Provide database credentials and Steam API key
   - Log in with Steam to create your admin account
   - Done! The first user automatically gets PANEL administrator role

#### Option 2: Manual Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/Stage4000/420th.git
   cd 420th
   ```

2. **Create the database**
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
   
   -- Get PANEL role ID
   SELECT id FROM roles WHERE name = 'PANEL';
   
   -- Grant PANEL role
   INSERT INTO user_roles (user_id, role_id) VALUES (YOUR_USER_ID, PANEL_ROLE_ID);
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
2. Click "Access Admin Panel" from the dashboard
3. **Manage Role Aliases:**
   - Scroll to the "Role Aliases" section
   - Enter custom display names for any role
   - Click "Update Alias" to save
   - Users will see the alias instead of the default name
4. **Manage User Roles:**
   - View all users in the "All Users" section
   - Click "Manage Roles" for any user
   - Select/deselect roles and click "Save Changes"

## File Structure

```
420th/
├── config.php          # Configuration file
├── database.sql        # Database schema
├── db.php             # Database connection handler
├── steam_auth.php     # Steam OAuth authentication
├── index.php          # Login page
├── callback.php       # OAuth callback handler
├── dashboard.php      # User dashboard
├── admin.php          # Admin panel
├── logout.php         # Logout handler
└── README.md          # This file
```

## Security Considerations

- **HTTPS Required**: Always use HTTPS in production
- **Secure Configuration**: Keep `config.php` outside the web root or protect it with `.htaccess`
- **Database Security**: Use strong passwords and limit database user privileges
- **Session Security**: Sessions are configured with secure settings
- **Input Validation**: All user inputs are sanitized and validated
- **Prepared Statements**: All database queries use prepared statements to prevent SQL injection

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

## Contributing

Contributions are welcome! Please open an issue or submit a pull request.

## License

This project is provided as-is for the 420th Delta community.

## Support

For issues or questions, please contact your system administrator or open an issue on GitHub.
