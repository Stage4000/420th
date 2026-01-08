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

### 5. Database Architecture

#### Tables
1. **users** - Stores user accounts and Steam profile data
2. **roles** - Defines available whitelist roles
3. **user_roles** - Maps users to their assigned roles

#### Features
- Foreign key constraints for data integrity
- Cascade delete to maintain consistency
- Indexed columns for fast queries
- UTF-8 support for international characters

### 6. Security Features

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

### 7. Installation Tools

- **verify.php** - Installation verification script
  - Checks PHP version and extensions
  - Tests database connectivity
  - Verifies table structure
  - Validates configuration
  - Provides setup guidance

- **database.sql** - Database schema
  - Creates database and tables
  - Inserts default roles
  - Sets up foreign key relationships

- **config.example.php** - Configuration template
  - Shows all required settings
  - Documents configuration options
  - Includes setup instructions

### 8. User Interface

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
├── index.php                # Login page
├── callback.php             # OAuth callback
├── dashboard.php            # User dashboard
├── admin.php                # Admin panel
├── logout.php               # Logout handler
└── verify.php               # Installation verification
```

## Future Enhancement Possibilities

While not currently implemented, the system could be extended with:

- Role-based permissions for different admin levels
- Audit log for all role changes
- User search and filtering in admin panel
- Role assignment expiration dates
- Email notifications for role changes
- API endpoints for external integrations
- Multi-language support
- Dark mode theme option
- Export user lists and reports
- Integration with game servers

## Support

For issues, questions, or contributions:
- Check the README.md for documentation
- Review INSTALL.md for setup help
- Run verify.php to diagnose problems
- Contact system administrator for access issues
