<?php
// Migration to add whitelist agreement setting

require_once 'db.php';

try {
    $db = Database::getInstance();
    
    echo "Adding whitelist agreement setting...\n";
    
    // Default whitelist agreement text
    $defaultAgreement = '<p><strong>By requesting whitelist, you agree to the following:</strong></p>
<ul>
    <li>
        <strong>Pilot Communication</strong> - All pilots are expected to communicate in-game via text or voice. You may be asked to switch role if unable to communicate.
    </li>
    <li>
        <strong>Waiting For Passengers</strong> - Transport Helicopters should wait in an orderly fashion on the side of the yellow barriers opposite from spawn, leaving the traffic lane clear for infantry and vehicles.
    </li>
    <li>
        <strong>No CAS on Kavala</strong> - All Close Air Support is forbidden to engage the Priority Mission Kavala. This mission is meant to be close-quarters combat. CAS can ruin the mission if they destroy buildings containing intel. Contact an in-game Zeus or use the vote-kick feature to enforce this rule as needed.
    </li>
</ul>';
    
    // Insert or update the whitelist_agreement setting
    $db->execute(
        "INSERT INTO server_settings (setting_key, setting_value) VALUES (?, ?) 
         ON DUPLICATE KEY UPDATE setting_value = IF(setting_value IS NULL OR setting_value = '', VALUES(setting_value), setting_value)",
        ['whitelist_agreement', $defaultAgreement]
    );
    
    echo "✓ Whitelist agreement setting added successfully!\n";
    echo "\nMigration completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error during migration: " . $e->getMessage() . "\n";
    exit(1);
}
