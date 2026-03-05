<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/audit_logger.php';
require_once __DIR__ . '/security_helpers.php';
require_once __DIR__ . '/reputation_engine.php';

class AlertEngine {
    private static $rulesCache = null;
    private static $lastCleanup = null;
    private const CLEANUP_INTERVAL = 300;
    
    private static function getRules() {
        if (self::$rulesCache !== null) {
            return self::$rulesCache;
        }
        
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->query("
                SELECT id, rule_name, rule_type, threshold_warning, threshold_critical,
                       time_window_seconds, scope, severity, cooldown_seconds, auto_action, enabled
                FROM alert_rules
                WHERE enabled = 1
            ");
            $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($rules as &$rule) {
                if ($rule['auto_action']) {
                    $rule['auto_action'] = json_decode($rule['auto_action'], true);
                }
            }
            
            self::$rulesCache = $rules;
            return $rules;
        } catch (Exception $e) {
            error_log("AlertEngine: Failed to load rules - " . $e->getMessage());
            return [];
        }
    }
    
    public static function check($eventType, $context = []) {
        $rules = self::getRules();
        
        if (empty($rules)) {
            return;
        }
        
        $ipAddress = $context['ip_address'] ?? SecurityHelpers::getClientIp();
        $tokenHash = $context['token_hash'] ?? null;
        $userId = $context['user_id'] ?? null;
        $endpoint = $context['endpoint'] ?? self::getCurrentEndpoint();
        $httpMethod = $context['http_method'] ?? ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN');
        $status = $context['status'] ?? 'SUCCESS';
        
        foreach ($rules as $rule) {
            if (!self::shouldCheckRule($rule, $eventType, $endpoint)) {
                continue;
            }
            
            $sourceHash = self::getSourceHash($rule, $ipAddress, $tokenHash, $userId, $endpoint);
            $windowStart = self::getWindowStart($rule['time_window_seconds']);
            
            $count = self::incrementMetric($rule['id'], $sourceHash, $windowStart);
            
            if ($count >= $rule['threshold_critical']) {
                self::fireAlert($rule, 'CRITICAL', $sourceHash, $ipAddress, $tokenHash, $userId, $endpoint, $count, $context);
            } elseif ($count >= $rule['threshold_warning']) {
                self::fireAlert($rule, 'WARNING', $sourceHash, $ipAddress, $tokenHash, $userId, $endpoint, $count, $context);
            }
        }
        
        self::cleanupOldMetrics();
    }
    
    private static function shouldCheckRule($rule, $eventType, $endpoint) {
        if ($rule['rule_type'] === 'ENDPOINT_BASED' && $rule['scope']) {
            $pattern = str_replace('*', '.*', $rule['scope']);
            if (!preg_match('/^' . $pattern . '$/', $endpoint)) {
                return false;
            }
        }
        
        return true;
    }
    
    private static function getSourceHash($rule, $ipAddress, $tokenHash, $userId, $endpoint) {
        switch ($rule['rule_type']) {
            case 'IP_BASED':
                return hash('sha256', 'ip:' . $ipAddress);
            case 'TOKEN_BASED':
                return $tokenHash ? hash('sha256', 'token:' . $tokenHash) : null;
            case 'USER_BASED':
                return $userId ? hash('sha256', 'user:' . $userId) : null;
            case 'ENDPOINT_BASED':
                return hash('sha256', 'endpoint:' . $endpoint);
            default:
                return hash('sha256', $ipAddress . '|' . ($tokenHash ?? '') . '|' . ($userId ?? ''));
        }
    }
    
    private static function getWindowStart($windowSeconds) {
        return date('Y-m-d H:i:s', floor(time() / $windowSeconds) * $windowSeconds);
    }
    
    private static function incrementMetric($ruleId, $sourceHash, $windowStart) {
        if (!$sourceHash) {
            return 0;
        }
        
        try {
            $db = Database::getInstance()->getConnection();
            
            $stmt = $db->prepare("
                INSERT INTO alert_metrics (rule_id, source_hash, window_start, count, last_updated)
                VALUES (:rule_id, :source_hash, :window_start, 1, NOW())
                ON DUPLICATE KEY UPDATE
                count = count + 1,
                last_updated = NOW()
            ");
            
            $stmt->execute([
                'rule_id' => $ruleId,
                'source_hash' => $sourceHash,
                'window_start' => $windowStart
            ]);
            
            $stmt = $db->prepare("
                SELECT count FROM alert_metrics
                WHERE rule_id = :rule_id
                AND source_hash = :source_hash
                AND window_start = :window_start
            ");
            
            $stmt->execute([
                'rule_id' => $ruleId,
                'source_hash' => $sourceHash,
                'window_start' => $windowStart
            ]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? (int)$result['count'] : 0;
            
        } catch (Exception $e) {
            error_log("AlertEngine: Failed to increment metric - " . $e->getMessage());
            return 0;
        }
    }
    
    private static function fireAlert($rule, $severity, $sourceHash, $ipAddress, $tokenHash, $userId, $endpoint, $count, $context) {
        if (!$sourceHash) {
            return;
        }
        
        try {
            $db = Database::getInstance()->getConnection();
            
            $db->beginTransaction();
            
            $stateHash = hash('sha256', $rule['id'] . '|' . $sourceHash);
            
            $stmt = $db->prepare("
                SELECT last_fired_at, fire_count, cooldown_until, escalated
                FROM alert_state
                WHERE rule_id = :rule_id
                AND source_hash = :state_hash
            FOR UPDATE
            ");
            
            $stmt->execute([
                'rule_id' => $rule['id'],
                'state_hash' => $stateHash
            ]);
            
            $state = $stmt->fetch(PDO::FETCH_ASSOC);
            $currentTime = date('Y-m-d H:i:s');
            
            if ($state) {
                if (strtotime($state['cooldown_until']) > time()) {
                    $db->rollBack();
                    return;
                }
                
                $newFireCount = (int)$state['fire_count'] + 1;
                $escalated = $state['escalated'] || ($newFireCount > 1 && $severity === 'CRITICAL');
                $cooldownUntil = date('Y-m-d H:i:s', time() + $rule['cooldown_seconds']);
                
                $stmt = $db->prepare("
                    UPDATE alert_state
                    SET last_fired_at = :last_fired_at,
                        fire_count = :fire_count,
                        escalated = :escalated,
                        cooldown_until = :cooldown_until,
                        updated_at = NOW()
                    WHERE rule_id = :rule_id
                    AND source_hash = :state_hash
                ");
                
                $stmt->execute([
                    'last_fired_at' => $currentTime,
                    'fire_count' => $newFireCount,
                    'escalated' => $escalated ? 1 : 0,
                    'cooldown_until' => $cooldownUntil,
                    'rule_id' => $rule['id'],
                    'state_hash' => $stateHash
                ]);
            } else {
                $cooldownUntil = date('Y-m-d H:i:s', time() + $rule['cooldown_seconds']);
                
                $stmt = $db->prepare("
                    INSERT INTO alert_state (rule_id, source_hash, last_fired_at, fire_count, escalated, cooldown_until)
                    VALUES (:rule_id, :state_hash, :last_fired_at, 1, 0, :cooldown_until)
                ");
                
                $stmt->execute([
                    'rule_id' => $rule['id'],
                    'state_hash' => $stateHash,
                    'last_fired_at' => $currentTime,
                    'cooldown_until' => $cooldownUntil
                ]);
            }
            
            $sourceValue = self::getSourceValue($rule, $ipAddress, $tokenHash, $userId, $endpoint);
            $sourceType = self::getSourceType($rule);
            
            $metadata = [
                'trigger_count' => $count,
                'time_window_seconds' => $rule['time_window_seconds'],
                'endpoint' => $endpoint,
                'http_method' => $context['http_method'] ?? $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
                'request_id' => AuditLogger::getRequestId()
            ];
            
            if ($ipAddress) $metadata['ip_address'] = $ipAddress;
            if ($tokenHash) $metadata['token_hash'] = substr($tokenHash, 0, 16) . '...';
            if ($userId) $metadata['user_id'] = $userId;
            
            $stmt = $db->prepare("
                INSERT INTO alert_events (
                    rule_id, rule_name, severity, source_type, source_value,
                    trigger_count, time_window_seconds, metadata, fired_at
                ) VALUES (
                    :rule_id, :rule_name, :severity, :source_type, :source_value,
                    :trigger_count, :time_window_seconds, :metadata, :fired_at
                )
            ");
            
            $stmt->execute([
                'rule_id' => $rule['id'],
                'rule_name' => $rule['rule_name'],
                'severity' => $severity,
                'source_type' => $sourceType,
                'source_value' => $sourceValue,
                'trigger_count' => $count,
                'time_window_seconds' => $rule['time_window_seconds'],
                'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
                'fired_at' => $currentTime
            ]);
            
            $alertId = $db->lastInsertId();
            $db->commit();
            
            $autoBlocked = false;
            if ($severity === 'CRITICAL' && isset($rule['auto_action']['block_ip']) && $rule['auto_action']['block_ip']) {
                $autoBlocked = true;
            }
            
            if ($ipAddress && $ipAddress !== '0.0.0.0') {
                ReputationEngine::recordIncident($ipAddress, $severity, $rule['rule_name'], $autoBlocked);
            }
            
            self::executeAutoActions($rule, $severity, $ipAddress, $tokenHash, $userId, $alertId);
            self::outputAlert($rule, $severity, $sourceValue, $sourceType, $count, $metadata, $alertId);
            
            AuditLogger::log(
                'ALERT_FIRED',
                $severity === 'CRITICAL' ? AuditLogger::SEVERITY_CRITICAL : AuditLogger::SEVERITY_WARNING,
                [
                    'alert_id' => $alertId,
                    'rule_name' => $rule['rule_name'],
                    'severity' => $severity,
                    'source_type' => $sourceType,
                    'source_value' => $sourceValue,
                    'trigger_count' => $count
                ]
            );
            
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log("AlertEngine: Failed to fire alert - " . $e->getMessage());
        }
    }
    
    private static function getSourceValue($rule, $ipAddress, $tokenHash, $userId, $endpoint) {
        switch ($rule['rule_type']) {
            case 'IP_BASED':
                return $ipAddress;
            case 'TOKEN_BASED':
                return $tokenHash ? substr($tokenHash, 0, 16) . '...' : 'unknown';
            case 'USER_BASED':
                return (string)$userId;
            case 'ENDPOINT_BASED':
                return $endpoint;
            default:
                return $ipAddress ?? 'unknown';
        }
    }
    
    private static function getSourceType($rule) {
        switch ($rule['rule_type']) {
            case 'IP_BASED':
                return 'IP';
            case 'TOKEN_BASED':
                return 'TOKEN';
            case 'USER_BASED':
                return 'USER';
            case 'ENDPOINT_BASED':
                return 'ENDPOINT';
            default:
                return 'IP';
        }
    }
    
    private static function executeAutoActions($rule, $severity, $ipAddress, $tokenHash, $userId, $alertId) {
        if ($severity !== 'CRITICAL' || !isset($rule['auto_action']) || !is_array($rule['auto_action'])) {
            return;
        }
        
        $actions = $rule['auto_action'];
        
        if (isset($actions['block_ip']) && $actions['block_ip'] && $ipAddress) {
            self::blockIp($ipAddress, $rule['rule_name'], $alertId, $actions['duration_seconds'] ?? 3600);
        }
        
        if (isset($actions['revoke_token']) && $actions['revoke_token'] && $tokenHash) {
            self::revokeToken($tokenHash, $rule['rule_name']);
        }
        
        if (isset($actions['flag_user']) && $actions['flag_user'] && $userId) {
            self::flagUser($userId, $rule['rule_name']);
        }
    }
    
    private static function blockIp($ipAddress, $reason, $alertId, $durationSeconds) {
        try {
            $db = Database::getInstance()->getConnection();
            
            $reputation = ReputationEngine::getReputation($ipAddress);
            $multiplier = $reputation['block_multiplier'];
            $adjustedDuration = (int)($durationSeconds * $multiplier);
            
            $blockedUntil = date('Y-m-d H:i:s', time() + $adjustedDuration);
            
            $stmt = $db->prepare("
                INSERT INTO blocked_ips (ip_address, blocked_at, blocked_until, reason, alert_id, auto_unblock)
                VALUES (:ip_address, NOW(), :blocked_until, :reason, :alert_id, 1)
                ON DUPLICATE KEY UPDATE
                blocked_until = GREATEST(blocked_until, :blocked_until),
                reason = :reason,
                alert_id = :alert_id
            ");
            
            $stmt->execute([
                'ip_address' => $ipAddress,
                'blocked_until' => $blockedUntil,
                'reason' => $reason . ' (reputation: ' . $reputation['score'] . ', multiplier: ' . $multiplier . 'x)',
                'alert_id' => $alertId
            ]);
            
            error_log("AlertEngine: Blocked IP $ipAddress until $blockedUntil - Reason: $reason - Reputation: {$reputation['score']} - Duration: {$adjustedDuration}s ({$multiplier}x)");
        } catch (Exception $e) {
            error_log("AlertEngine: Failed to block IP - " . $e->getMessage());
        }
    }
    
    private static function revokeToken($tokenHash, $reason) {
        try {
            $db = Database::getInstance()->getConnection();
            
            $stmt = $db->prepare("
                UPDATE api_tokens
                SET revoked_at = NOW(),
                    revoked_reason = :reason
                WHERE token_hash = :token_hash
            ");
            
            $stmt->execute([
                'token_hash' => $tokenHash,
                'reason' => 'alert:' . $reason
            ]);
            
            error_log("AlertEngine: Revoked token " . substr($tokenHash, 0, 16) . "... - Reason: $reason");
        } catch (Exception $e) {
            error_log("AlertEngine: Failed to revoke token - " . $e->getMessage());
        }
    }
    
    private static function flagUser($userId, $reason) {
        try {
            AuditLogger::suspiciousUser($userId, 'alert:' . $reason);
            error_log("AlertEngine: Flagged user $userId - Reason: $reason");
        } catch (Exception $e) {
            error_log("AlertEngine: Failed to flag user - " . $e->getMessage());
        }
    }
    
    private static function outputAlert($rule, $severity, $sourceValue, $sourceType, $count, $metadata, $alertId) {
        $alert = [
            'alert_id' => $alertId,
            'rule_name' => $rule['rule_name'],
            'severity' => $severity,
            'source_type' => $sourceType,
            'source_value' => $sourceValue,
            'trigger_count' => $count,
            'time_window_seconds' => $rule['time_window_seconds'],
            'endpoint' => $metadata['endpoint'] ?? 'unknown',
            'request_id' => $metadata['request_id'] ?? null,
            'timestamp' => date('c'),
            'suggested_action' => self::getSuggestedAction($rule, $severity),
            'metadata' => $metadata
        ];
        
        self::writeToLogFile($alert);
        self::prepareEmailAlert($alert);
    }
    
    private static function writeToLogFile($alert) {
        $logPath = __DIR__ . '/../../logs/security.log';
        $logDir = dirname($logPath);
        
        if (!file_exists($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        $logLine = sprintf(
            "[ALERT] [%s] [%s] Rule: %s | Source: %s:%s | Count: %d/%ds | Endpoint: %s | AlertID: %d | Action: %s\n",
            $alert['timestamp'],
            $alert['severity'],
            $alert['rule_name'],
            $alert['source_type'],
            $alert['source_value'],
            $alert['trigger_count'],
            $alert['time_window_seconds'],
            $alert['endpoint'],
            $alert['alert_id'],
            $alert['suggested_action']
        );
        
        @file_put_contents($logPath, $logLine, FILE_APPEND | LOCK_EX);
    }
    
    private static function prepareEmailAlert($alert) {
        if ($alert['severity'] !== 'CRITICAL') {
            return;
        }
        
        $subject = "[CRITICAL ALERT] " . $alert['rule_name'] . " - " . $alert['source_value'];
        $body = self::formatEmailAlert($alert);
        
        error_log("AlertEngine: Email alert prepared - Subject: $subject");
    }
    
    private static function formatEmailAlert($alert) {
        return <<<EMAIL
CRITICAL SECURITY ALERT

Alert ID: {$alert['alert_id']}
Rule: {$alert['rule_name']}
Severity: {$alert['severity']}
Timestamp: {$alert['timestamp']}

Source:
  Type: {$alert['source_type']}
  Value: {$alert['source_value']}

Trigger Details:
  Count: {$alert['trigger_count']}
  Time Window: {$alert['time_window_seconds']} seconds
  Endpoint: {$alert['endpoint']}
  Request ID: {$alert['request_id']}

Suggested Action: {$alert['suggested_action']}

Metadata:
{$alert['metadata']}

---
This is an automated alert from the API Security Monitoring System.
EMAIL;
    }
    
    private static function getSuggestedAction($rule, $severity) {
        if ($severity !== 'CRITICAL') {
            return 'Review and monitor';
        }
        
        if (isset($rule['auto_action']['block_ip']) && $rule['auto_action']['block_ip']) {
            return 'IP has been automatically blocked';
        }
        
        if (isset($rule['auto_action']['revoke_token']) && $rule['auto_action']['revoke_token']) {
            return 'Token has been automatically revoked';
        }
        
        return 'Immediate manual review required';
    }
    
    private static function getCurrentEndpoint() {
        $endpoint = $_SERVER['REQUEST_URI'] ?? '/';
        $endpoint = parse_url($endpoint, PHP_URL_PATH);
        return substr($endpoint, 0, 255) ?: '/';
    }
    
    private static function cleanupOldMetrics() {
        $now = time();
        
        if (self::$lastCleanup && ($now - self::$lastCleanup) < self::CLEANUP_INTERVAL) {
            return;
        }
        
        try {
            $db = Database::getInstance()->getConnection();
            
            $rules = self::getRules();
            $maxAge = 0;
            
            foreach ($rules as $rule) {
                $maxAge = max($maxAge, $rule['time_window_seconds'] * 2);
            }
            
            $cutoffTime = date('Y-m-d H:i:s', time() - $maxAge);
            
            $stmt = $db->prepare("
                DELETE FROM alert_metrics
                WHERE window_start < :cutoff_time
            ");
            
            $stmt->execute(['cutoff_time' => $cutoffTime]);
            
            $stmt = $db->prepare("
                DELETE FROM blocked_ips
                WHERE auto_unblock = 1
                AND blocked_until < NOW()
            ");
            
            $stmt->execute();
            
            self::$lastCleanup = $now;
        } catch (Exception $e) {
            error_log("AlertEngine: Cleanup failed - " . $e->getMessage());
        }
    }
    
    public static function isIpBlocked($ipAddress) {
        try {
            $db = Database::getInstance()->getConnection();
            
            $stmt = $db->prepare("
                SELECT 1 FROM blocked_ips
                WHERE ip_address = :ip_address
                AND blocked_until > NOW()
                LIMIT 1
            ");
            
            $stmt->execute(['ip_address' => $ipAddress]);
            return $stmt->fetch() !== false;
        } catch (Exception $e) {
            error_log("AlertEngine: Failed to check IP block - " . $e->getMessage());
            return false;
        }
    }
    
    public static function clearCache() {
        self::$rulesCache = null;
    }
}
