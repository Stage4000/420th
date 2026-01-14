# RCON Feature Testing Guide

## Testing the RCON Implementation

This guide explains how to test the RCON kick and ban features.

### Prerequisites for Testing

1. **Local Setup:**
   - PHP 7.4+ with required extensions
   - MySQL/MariaDB running
   - Web server (Apache/Nginx)
   - Composer installed
   - Database imported from database.sql

2. **For Full RCON Testing:**
   - Arma 3 server with BattlEye RCON enabled
   - RCON credentials (host, port, password)

### Testing Without RCON Server (UI Only)

1. **Install Dependencies:**
   ```bash
   composer install
   ```

2. **Import Database:**
   ```bash
   mysql -u root -p < database.sql
   ```

3. **Run Migration:**
   ```bash
   php migrate_add_rcon_settings.php
   ```

4. **Access Admin Panel:**
   - Log in with a PANEL admin account
   - Navigate to admin.php
   - Verify RCON configuration section appears
   - Fields should include: Enable toggle, Host, Port, Password

5. **Test Ban Modal:**
   - Navigate to users.php
   - Click "Ban" on any user
   - Modal should show ban options
   - If RCON is disabled: No server action checkboxes visible
   - If RCON is enabled: Server kick and ban checkboxes visible

### Testing With RCON Server

1. **Configure BattlEye RCON on Arma 3 Server:**
   
   Edit `beserver.cfg` or `beserver_x64.cfg`:
   ```
   RConPort 2306
   RConPassword your_secure_password
   ```
   
   Restart the Arma 3 server.

2. **Configure RCON in Admin Panel:**
   - Enable RCON: Check the box
   - RCON Host: Your server IP (e.g., 127.0.0.1 or server.example.com)
   - RCON Port: 2306 (or your configured port)
   - RCON Password: Your beserver.cfg password
   - Click "Save RCON Settings"

3. **Test Connection:**
   - Click "Test RCON Connection"
   - Should show success message with player count
   - If failed, check firewall and credentials

4. **Test Kick:**
   - Ensure a player is online on the server
   - Navigate to users.php
   - Click "Ban" on the test user
   - Check "Kick player from game server"
   - Enter a reason
   - Click "Issue Ban"
   - Player should be kicked from server
   - Verify ban record in database has `server_kick = 1`

5. **Test Ban:**
   - Have a test player online
   - Click "Ban" on test user
   - Check "Ban player from game server"
   - Enter a reason
   - Click "Issue Ban"
   - Player should be kicked AND added to BattlEye ban list
   - Verify ban record in database has `server_ban = 1`
   - Check server's ban.txt file for Steam ID

### Expected Results

✅ **Pass Criteria:**
- All syntax checks pass
- Migration script runs without errors
- RCON configuration UI displays correctly
- Ban modal shows server action options when RCON enabled
- Test connection succeeds with valid credentials
- Kicks work when RCON enabled
- Bans work when RCON enabled
- Whitelist bans work even with RCON disabled
- Error messages are helpful and don't expose sensitive data

❌ **Known Limitations:**
- RCON connection requires network access to server
- Cannot test actual kicks/bans without running Arma 3 server
- Password in database is not encrypted (should use encryption in production)

### Troubleshooting

**"Could not connect to RCON server":**
- Check firewall allows connections to RCON port
- Verify RCON is enabled in beserver.cfg
- Test with `telnet server_ip rcon_port` from web server
- Ensure password is correct

**"Failed to kick/ban player":**
- Verify player is actually online
- Check player Steam ID matches database
- Look for errors in server logs

**"RCON is not enabled":**
- Make sure "Enable RCON" checkbox is checked in admin panel
- Verify all required fields are filled
- Check database server_settings table has values

### Security Testing

⚠️ **Important Security Checks:**
- RCON password should not appear in browser dev tools
- Connection errors should not expose password
- Only PANEL admins can access RCON settings
- Only ALL role users can kick/ban
- SQL injection protection verified (using prepared statements)

### Manual Code Review Checklist

- ✅ All PHP syntax errors checked
- ✅ Prepared statements used for all queries
- ✅ Input validation on all user inputs
- ✅ Error handling for RCON failures
- ✅ Password masked in UI
- ✅ Constants used instead of magic numbers
- ✅ Documentation complete and accurate

## Final Notes

This implementation provides a solid foundation for RCON integration. For production use, consider:

1. **Encryption**: Encrypt RCON password in database
2. **Logging**: Add audit trail for all RCON actions
3. **Rate Limiting**: Prevent abuse of kick/ban features
4. **Advanced Features**: Add player list display, server commands, etc.
5. **Testing**: Set up automated tests with mock RCON server
