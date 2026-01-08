# Quick Installation Guide

## Step 1: Database Setup

```bash
# Create and import the database
mysql -u root -p < database.sql
```

## Step 2: Configuration

```bash
# Copy the example config
cp config.example.php config.php

# Edit config.php and update:
# - Database credentials (DB_HOST, DB_NAME, DB_USER, DB_PASS)
# - Steam API key (STEAM_API_KEY)
# - Return URL (STEAM_RETURN_URL)
```

## Step 3: Verify Installation

Navigate to `verify.php` in your browser to check that everything is configured correctly.

## Step 4: First Admin User

1. Log in via Steam OAuth
2. Find your user ID in the database:
   ```sql
   SELECT id FROM users WHERE steam_id = 'YOUR_STEAM_ID';
   ```
3. Grant yourself PANEL role:
   ```sql
   INSERT INTO user_roles (user_id, role_id) 
   VALUES (YOUR_USER_ID, (SELECT id FROM roles WHERE name = 'PANEL'));
   ```

## Step 5: Security

- Delete `verify.php` after confirming installation
- Enable HTTPS in production
- Update `.htaccess` to force HTTPS redirect
- Ensure `config.php` has restricted permissions (chmod 600)

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
- Check user_roles table for assignments
- Use admin panel to assign roles
