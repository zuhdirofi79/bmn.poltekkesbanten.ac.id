<?php
require_once __DIR__ . '/../config/reputation_engine.php';

$count = ReputationEngine::applyDecay();
echo date('Y-m-d H:i:s') . " - Reputation decay applied to $count IPs\n";
