# Features Documentation

## Overview

The 420th Delta Dashboard is a complete PHP-based whitelist management system with Steam OAuth authentication. It provides a user-friendly interface for viewing roles and an admin panel for managing user permissions.

## Key Features

### 1. Steam OAuth Authentication
- Secure login using Steam OpenID
- Automatic user profile synchronization
- Session management with configurable lifetime
- Logout functionality

### 2. Role-Based Access Control

#### Supported Roles
The system supports all 12 required whitelist roles:

| Role | Display Name | Description |
|------|--------------|-------------|
| S3 | S3 | S3 personnel |
| CAS | CAS | Close Air Support personnel |
| S1 | S1 | S1 personnel |
| OPFOR | OPFOR | Opposing Force personnel |
| ALL | ALL (Staff) | Staff role - all staff should have this |
| ADMIN | Administrator | Administrator with elevated privileges |
| MODERATOR | Moderator | Moderator with moderation privileges |
| TRUSTED | Trusted | Trusted community member |
| MEDIA | Media | Media team member |
| CURATOR | Curator | Content curator |
| DEVELOPER | Developer | Developer team member |
| PANEL | Panel Administrator | Panel admin with user management rights |

### 3. User Dashboard
- Display user's Steam profile information
- Show all assigned whitelist roles
- Visual role badges with color coding
- Special notification for PANEL administrators
- Quick access to admin panel (for PANEL users)

### 4. Admin Panel
Features available to users with PANEL role:

- **User Management**
  - View all users who have logged in
  - See each user's Steam ID and profile
  - Display current role assignments
  - Track last login timestamps

- **Role Management**
  - Add/remove roles for any user
  - Bulk role updates through modal interface
  - Real-time role assignment tracking
  - Audit trail showing who granted roles

- **RCON Server Management** (New!)
  - Configure Arma 3 server RCON connection
  - Enable/disable RCON functionality
  - Test RCON connection status
  - Issue server kicks via BattlEye RCON
  - Issue server bans via BattlEye RCON
  - Secure password storage for RCON credentials

### 5. Ban Management System
Features available to users with ALL role:

- **Whitelist Bans**
  - Ban users from whitelist roles (S3, CAS, or both)
  - Set temporary or permanent bans
  - Add ban reasons and expiration dates
  - View active and expired bans
  - Unban users with reason tracking

- **Server Actions** (New!)
  - Kick players from game server (requires RCON)
  - Ban players from game server (requires RCON)
  - Combined whitelist and server bans
  - Automatic BattlEye ban list updates

### 6. Database Architecture

#### Tables
1. **users** - Stores user accounts and Steam profile data
2. **roles** - Defines available whitelist roles
3. **whitelist_bans** - Tracks whitelist and server bans
4. **server_settings** - Stores RCON and other server configuration (New!)

#### Features
- Foreign key constraints for data integrity
- Cascade delete to maintain consistency
- Indexed columns for fast queries
- UTF-8 support for international characters

### 7. Security Features

- **Authentication**
  - Secure Steam OpenID validation
  - Session-based authentication
  - Automatic session expiration

- **Database Security**
  - PDO with prepared statements
  - Protection against SQL injection
  - Parameterized queries throughout

- **Input Validation**
  - All user inputs sanitized
  - HTML entity encoding for output
  - Type validation for all parameters

- **Configuration Security**
  - Environment variable support
  - .htaccess protection for sensitive files
  - Secure session configuration

- **API Security**
  - HTTPS for all external API calls
  - Error handling for API failures
  - Secure Steam API key storage
  - RCON password encryption in database (New!)

### 8. Installation Tools

- **verify.php** - Installation verification script
  - Checks PHP version and extensions
  - Tests database connectivity
  - Verifies table structure
  - Validates configuration
  - Provides setup guidance

- **migrate_add_rcon_settings.php** - RCON migration script (New!)
  - Creates server_settings table
  - Adds RCON configuration options
  - Updates ban table schema

- **database.sql** - Database schema
  - Creates database and tables
  - Inserts default roles
  - Sets up foreign key relationships
  - Includes RCON settings table

- **config.example.php** - Configuration template
  - Shows all required settings
  - Documents configuration options
  - Includes setup instructions

### 9. User Interface

- **Modern Design**
  - Responsive layout
  - Mobile-friendly interface
  - Gradient backgrounds
  - Clean typography

- **Visual Elements**
  - Color-coded role badges
  - User avatars from Steam
  - Icon-based navigation
  - Modal dialogs for actions

- **User Experience**
  - Simple one-click Steam login
  - Intuitive admin panel
  - Clear status messages
  - Helpful error messages

## Technical Specifications

### Requirements
- PHP 7.4 or higher
- MySQL 5.7+ or MariaDB 10.2+
- Web server (Apache/Nginx)
- Steam API key
- Composer (for RCON library installation) (New!)
- Arma 3 server with BattlEye RCON enabled (optional) (New!)

### PHP Extensions Required
- PDO
- PDO MySQL
- JSON
- Session
- OpenSSL

### Browser Compatibility
- Modern browsers (Chrome, Firefox, Safari, Edge)
- Responsive design for mobile devices

## API Integrations

### Steam Web API
- **ISteamUser/GetPlayerSummaries** - Retrieves user profile data
  - Username
  - Avatar images
  - Profile URL
  - Steam ID

### Steam OpenID
- Used for authentication
- Validates user identity
- Returns Steam ID after successful login

### Arma 3 BattlEye RCON (New!)
- **Remote console protocol** for server management
- Player kick and ban commands
- Real-time server status
- Secure password-protected connection

## Configuration Options

### Database
- Host, name, username, password
- Configurable via environment variables

### Steam
- API key (required)
- Return URL for OAuth callback
- Login URL (Steam OpenID endpoint)

### Session
- Session name
- Session lifetime (default: 24 hours)
- Cookie settings

### RCON (New!)
- RCON enabled/disabled flag
- Server host/IP address
- Server RCON port (typically game port + 4)
- RCON password (encrypted in database)
- All configurable via admin panel interface

## File Structure

```
420th/
├── .htaccess                 # Apache security configuration
├── .gitignore               # Git ignore rules
├── README.md                # Main documentation
├── INSTALL.md               # Installation guide
├── config.php               # Configuration (not in repo)
├── config.example.php       # Configuration template
├── database.sql             # Database schema
├── db.php                   # Database connection class
├── steam_auth.php           # Steam authentication handler
├── ban_manager.php          # Ban management class
├── role_manager.php         # Role management class
├── rcon_manager.php         # RCON management class (New!)
├── composer.json            # PHP dependencies (New!)
├── vendor/                  # Composer dependencies (New!)
├── index.php                # Login page
├── callback.php             # OAuth callback
├── dashboard.php            # User dashboard
├── admin.php                # Admin panel with RCON config
├── users.php                # User management with ban/kick
├── logout.php               # Logout handler
├── verify.php               # Installation verification
├── migrate_add_rcon_settings.php  # RCON migration script (New!)
└── migrate_*.php            # Other migration scripts
```

## Future Enhancement Possibilities

While not currently implemented, the system could be extended with:

- Role-based permissions for different admin levels
- Audit log for all role changes and server actions
- User search and filtering in admin panel
- Role assignment expiration dates
- Email notifications for role changes and bans
- API endpoints for external integrations
- Multi-language support
- Dark mode theme option
- Export user lists and reports
- Advanced RCON commands and monitoring
- Player session tracking and statistics
- Scheduled ban expirations with automatic unbans

## Support

For issues, questions, or contributions:
- Check the README.md for documentation
- Review INSTALL.md for setup help
- Run verify.php to diagnose problems
- Contact system administrator for access issues
