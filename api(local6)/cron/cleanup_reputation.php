<?php
require_once __DIR__ . '/../config/reputation_engine.php';

$count = ReputationEngine::cleanupOldRecords(365);
echo date('Y-m-d H:i:s') . " - Cleaned up $count old reputation records\n";
