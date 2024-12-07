<?php

require_once('config/db.php');

echo "Zeus backup utility\n";


$cwd = getcwd();

// Database configuration
$dbHost = DB_HOST;          // Database host
$dbUser = DB_USER;      // Database username
$dbPass = DB_PASS;      // Database password
$dbName = DB_NAME;      // Database name
$backupDir = $cwd . '/backups'; // Directory to store backups
// $backupFile = $backupDir . '/backup_' . date('Y-m-d_H-i-s') . '.sql';

// table list:
//
// analytics
// appointments (dyn)
// content (st)   
// feed_class (st)
// feed_hashes (st)  
// locations (st)
// pageanalytics    
// patients (dyn)
// user_tokens
// users

// Predefined exclusion lists
$exportGroups = [
    // static is content that never changes in the production and need to be transfered from development to production
    'static' => ['content', 'feed_class', 'feed_hashes', 'locations'],

    // fixed is content that never changes in the production but should not be transferred from development to production
    'fixed' => ['users', 'pageanalytics'],

    // dynamic is content that changes in the production server and must be transferred from production to testing
    'dynamic' => ['patients', 'appointments'],

    // volatile is content that changes in each server and is never transferred to the othis servers
    'volatile' => ['analytics', 'user_tokens']
];

$alltables = [];
foreach($exportGroups as $grp) {
    $alltables = array_merge($alltables, $grp);
}

$exportGroups['all'] = $alltables;

// --exportGroup=group  --> export group in file backup???.group.sql
// group:
// - static
// - fixed
// - dynamic
// - volatile
// - all (all of the above)

// --single     --> export all tables in separate files

// Parse command-line argument
$options = getopt("", ["exportGroup:", "single", "git"]);
$exportGroup = $options['exportGroup'] ?? null;

// export each table in separate file
$single = isset($options['single'])?1:null;

// export tables in repo directory, implies --single
$git = isset($options['git'])?1:null;


if($git) {
    $backupDir .= '/repo';
    $single=1;
}



echo("Command arguments: " . print_r($exportGroup, 1) . '\n' . print_r($single, 1));
// Validate selected group
if (!$exportGroup || !array_key_exists($exportGroup, $exportGroups)) {
    echo "Error: Invalid or missing export group. Available groups: " . implode(', ', array_keys($exportGroups)) . "\n";
    exit(1);
}


// Build --ignore-table options
$exportTables = '';
foreach ($exportGroups[$exportGroup] as $table) {
    // $exportTables .= " --ignore-table=$dbName.$table";
    $exportTables .= " $table";
}

// Ensure backup directory exists"
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$dbcommand = "mysqldump -y -h $dbHost -u $dbUser -p$dbPass $dbName | grep 'CREATE TABLE' - | cut -d\` -f2 -";
exec($dbcommand, $alltables, $result);
echo("All database tables: " . print_r($alltables, 1));

$diff = array_diff($alltables, $exportGroups[$exportGroup]);
echo "Different in tables database - exportGroup: " . print_r($diff, 1) . "\n";
// Database tables: " . print_r($output, 1) . "\n";
// exit(-1);


$mysqldump_options = "--no-tablespaces --skip-opt --skip-extended-insert --add-drop-table";
// Command to dump the database

if($git) {
    $dateStr = "";
} else {
    $dateStr = "_" . date('Y-m-d_H-i');
}


if($single) {
    foreach($exportGroups[$exportGroup] as $table) {
        $backupFile = $backupDir . '/backup' . $dateStr . '.' . $table . '.sql';
        $command = "mysqldump $mysqldump_options -h $dbHost -u $dbUser -p$dbPass $dbName $table > $backupFile";
        echo "Command: $command\n";
        exec($command, $output, $result);
    }
} else {
    $backupFile = $backupDir . '/backup' . $dateStr . '.' . $exportGroup . '.sql';
    
    $command = "mysqldump $mysqldump_options -h $dbHost -u $dbUser -p$dbPass $dbName $exportTables > $backupFile";
    echo "Command: $command\n";
    exec($command, $output, $result);
}

// Check if the backup was successful
if ($result === 0) {
    echo "Database backup successful! Saved to: $backupFile\n";
} else {
    echo "Database backup failed. Error: " . implode("\n", $output) . "\n";
}
?>
