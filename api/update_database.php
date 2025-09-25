<?php
require_once '../includes/functions.php';

requireRole('admin');

header('Content-Type: application/json');

try {
    // Check if quotation_number column exists
    $columns = $db->fetchAll("DESCRIBE quotations");
    $columnNames = array_column($columns, 'Field');
    $needsUpdate = !in_array('quotation_number', $columnNames);

    if ($needsUpdate) {
        // Run the database updates
        $sqlFile = '../database/quotation_creator_updates.sql';
        if (file_exists($sqlFile)) {
            $sql = file_get_contents($sqlFile);

            // Split the SQL into individual statements
            $statements = array_filter(array_map('trim', explode(';', $sql)));

            $db->beginTransaction();

            foreach ($statements as $statement) {
                if (!empty($statement) && !str_starts_with($statement, '--')) {
                    $db->query($statement);
                }
            }

            $db->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Database updated successfully! Quotation Creator is now ready to use.'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Database update file not found'
            ]);
        }
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Database is already up to date!'
        ]);
    }

} catch (Exception $e) {
    if (isset($db)) {
        $db->rollback();
    }

    echo json_encode([
        'success' => false,
        'error' => 'Database update failed: ' . $e->getMessage()
    ]);
}
?>