# Quick Installation Guide

## Prerequisites

- PHP 7.4 or higher with PDO, JSON, Session, and OpenSSL extensions
- MySQL 5.7+ or MariaDB 10.2+
- Apache or Nginx web server
- Composer (for installing PHP dependencies)
- Steam API key
- (Optional) Arma 3 server with BattlEye RCON enabled

## Step 1: Database Setup

```bash
# Create and import the database
mysql -u root -p < database.sql
```

## Step 2: Install PHP Dependencies

```bash
# Install Composer dependencies (includes RCON library)
composer install
```

## Step 3: Configuration

```bash
# Copy the example config
cp config.example.php config.php

# Edit config.php and update:
# - Database credentials (DB_HOST, DB_NAME, DB_USER, DB_PASS)
# - Steam API key (STEAM_API_KEY)
# - Return URL (STEAM_RETURN_URL)
```

## Step 4: Verify Installation

Navigate to `verify.php` in your browser to check that everything is configured correctly.

## Step 5: First Admin User

1. Log in via Steam OAuth
2. Find your user ID in the database:
   ```sql
   SELECT id FROM users WHERE steam_id = 'YOUR_STEAM_ID';
   ```
3. Grant yourself PANEL role:
   ```sql
   UPDATE users SET role_panel = 1 WHERE id = YOUR_USER_ID;
   ```

## Step 6: RCON Configuration (Optional)

If you want to enable server kicks and bans via RCON:

### 6.1: Configure BattlEye RCON on your Arma 3 server

Edit your `beserver.cfg` or `beserver_x64.cfg` file and add:

```
RConPort 2306
RConPassword your_secure_password_here
```

**Note:** The RConPort is typically your game server port + 4. For example, if your game server runs on port 2302, RCON should be 2306.

### 6.2: Restart your Arma 3 server

After editing the config file, restart the server for changes to take effect.

### 6.3: Configure RCON in Admin Panel

1. Log in to the dashboard with a PANEL admin account
2. Navigate to Admin Panel (admin.php)
3. Scroll to "Arma 3 Server RCON Configuration"
4. Fill in the following:
   - Enable RCON: Check the box
   - RCON Host/IP: Your server's IP address (e.g., 127.0.0.1 or server.example.com)
   - RCON Port: The port you set in beserver.cfg (e.g., 2306)
   - RCON Password: The password you set in beserver.cfg
5. Click "Save RCON Settings"
6. Click "Test RCON Connection" to verify it works

## Step 7: Security

- Delete `verify.php` after confirming installation
- Enable HTTPS in production
- Update `.htaccess` to force HTTPS redirect
- Ensure `config.php` has restricted permissions (chmod 600)
- Keep your RCON password secure and change it regularly
- Restrict RCON port access using firewall rules

## Getting Your Steam API Key

1. Visit https://steamcommunity.com/dev/apikey
2. Log in with your Steam account
3. Fill out the form with your domain name
4. Copy the generated API key
5. Paste it in `config.php` as the `STEAM_API_KEY` value

## Troubleshooting

### Can't connect to database
- Check MySQL/MariaDB is running
- Verify credentials in `config.php`
- Ensure database exists

### Steam login fails
- Check Steam API key is valid
- Verify STEAM_RETURN_URL matches your domain
- Ensure outbound HTTPS connections are allowed

### No roles showing
- Verify roles were inserted in database
- Check users table for role columns
- Use admin panel to assign roles

### RCON connection fails
- Verify BattlEye RCON is enabled on your Arma 3 server
- Check that RConPort and RConPassword are set correctly in beserver.cfg
- Ensure the RCON port is not blocked by firewall
- Verify the server IP and port are correct in admin panel
- Check that your web server can reach the game server on the RCON port
- Use `telnet server_ip rcon_port` from your web server to test connectivity

### Composer install fails
- Ensure Composer is installed: `composer --version`
- Try running `composer update` instead
- Check PHP version is 7.4 or higher
- Ensure internet connection is available

## Migration from Older Versions

If you're upgrading from a version without RCON support:

```bash
# Run the RCON migration script
php migrate_add_rcon_settings.php
```

This will add the necessary database tables and columns for RCON functionality.
