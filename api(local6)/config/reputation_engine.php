<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/audit_logger.php';
require_once __DIR__ . '/security_helpers.php';

class ReputationEngine {
    private static $cache = [];
    private const SCORE_WARNING = 1;
    private const SCORE_CRITICAL = 3;
    private const SCORE_AUTO_BLOCK = 5;
    private const SCORE_MIN = -100;
    private const SCORE_MAX = 1000;
    
    private const STATUS_NORMAL = 'NORMAL';
    private const STATUS_SUSPICIOUS = 'SUSPICIOUS';
    private const STATUS_MALICIOUS = 'MALICIOUS';
    
    private const THRESHOLD_NORMAL = 10;
    private const THRESHOLD_SUSPICIOUS = 50;
    private const THRESHOLD_MALICIOUS = 51;
    private const THRESHOLD_AUTO_BLOCK = 30;
    
    private const DECAY_INTERVAL_HOURS = 24;
    private const DECAY_RATE = 0.1;
    private const MIN_SCORE_FOR_DECAY = 1;
    
    private const ESCALATION_WINDOW_HOURS = 24;
    private const ESCALATION_MULTIPLIER_BASE = 1.0;
    private const ESCALATION_MULTIPLIER_MAX = 3.0;
    
    public static function recordIncident($ipAddress, $severity, $alertType, $autoBlocked = false) {
        if (!$ipAddress || $ipAddress === '0.0.0.0') {
            return;
        }
        
        try {
            $db = Database::getInstance()->getConnection();
            $currentTime = date('Y-m-d H:i:s');
            
            $db->beginTransaction();
            
            $stmt = $db->prepare("
                SELECT id, reputation_score, last_incident_at, total_alerts, critical_alerts, auto_block_count
                FROM ip_reputation
                WHERE ip_address = :ip_address
            FOR UPDATE
            ");
            
            $stmt->execute(['ip_address' => $ipAddress]);
            $reputation = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($reputation) {
                $lastIncidentAt = $reputation['last_incident_at'] ? strtotime($reputation['last_incident_at']) : 0;
                $hoursSinceLastIncident = $lastIncidentAt > 0 ? (time() - $lastIncidentAt) / 3600 : 999;
                
                $escalationMultiplier = self::calculateEscalationMultiplier($hoursSinceLastIncident);
                
                $baseScore = $severity === 'CRITICAL' ? self::SCORE_CRITICAL : self::SCORE_WARNING;
                
                if ($autoBlocked) {
                    $baseScore += self::SCORE_AUTO_BLOCK;
                }
                
                $scoreIncrease = (int)ceil($baseScore * $escalationMultiplier);
                $newScore = min(self::SCORE_MAX, $reputation['reputation_score'] + $scoreIncrease);
                
                $newTotalAlerts = (int)$reputation['total_alerts'] + 1;
                $newCriticalAlerts = $severity === 'CRITICAL' ? (int)$reputation['critical_alerts'] + 1 : (int)$reputation['critical_alerts'];
                $newAutoBlockCount = $autoBlocked ? (int)$reputation['auto_block_count'] + 1 : (int)$reputation['auto_block_count'];
                
                $newStatus = self::calculateStatus($newScore);
                
                $metadata = json_decode($reputation['metadata'] ?? '{}', true);
                if (!is_array($metadata)) {
                    $metadata = [];
                }
                
                $metadata['last_alert_type'] = $alertType;
                $metadata['last_alert_severity'] = $severity;
                $metadata['last_alert_at'] = $currentTime;
                
                if (!isset($metadata['alert_history'])) {
                    $metadata['alert_history'] = [];
                }
                
                $metadata['alert_history'][] = [
                    'type' => $alertType,
                    'severity' => $severity,
                    'score_increase' => $scoreIncrease,
                    'timestamp' => $currentTime
                ];
                
                if (count($metadata['alert_history']) > 50) {
                    array_shift($metadata['alert_history']);
                }
                
                $stmt = $db->prepare("
                    UPDATE ip_reputation
                    SET reputation_score = :reputation_score,
                        last_seen = :last_seen,
                        last_incident_at = :last_incident_at,
                        total_alerts = :total_alerts,
                        critical_alerts = :critical_alerts,
                        auto_block_count = :auto_block_count,
                        status = :status,
                        metadata = :metadata,
                        updated_at = NOW()
                    WHERE id = :id
                ");
                
                $stmt->execute([
                    'reputation_score' => $newScore,
                    'last_seen' => $currentTime,
                    'last_incident_at' => $currentTime,
                    'total_alerts' => $newTotalAlerts,
                    'critical_alerts' => $newCriticalAlerts,
                    'auto_block_count' => $newAutoBlockCount,
                    'status' => $newStatus,
                    'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
                    'id' => $reputation['id']
                ]);
                
                self::$cache[$ipAddress] = [
                    'score' => $newScore,
                    'status' => $newStatus,
                    'last_incident_at' => $currentTime
                ];
                
            } else {
                $baseScore = $severity === 'CRITICAL' ? self::SCORE_CRITICAL : self::SCORE_WARNING;
                if ($autoBlocked) {
                    $baseScore += self::SCORE_AUTO_BLOCK;
                }
                
                $newStatus = self::calculateStatus($baseScore);
                
                $metadata = [
                    'first_alert_type' => $alertType,
                    'first_alert_severity' => $severity,
                    'alert_history' => [[
                        'type' => $alertType,
                        'severity' => $severity,
                        'score_increase' => $baseScore,
                        'timestamp' => $currentTime
                    ]]
                ];
                
                $stmt = $db->prepare("
                    INSERT INTO ip_reputation (
                        ip_address, reputation_score, first_seen, last_seen,
                        last_incident_at, total_alerts, critical_alerts,
                        auto_block_count, status, metadata
                    ) VALUES (
                        :ip_address, :reputation_score, :first_seen, :last_seen,
                        :last_incident_at, :total_alerts, :critical_alerts,
                        :auto_block_count, :status, :metadata
                    )
                ");
                
                $stmt->execute([
                    'ip_address' => $ipAddress,
                    'reputation_score' => $baseScore,
                    'first_seen' => $currentTime,
                    'last_seen' => $currentTime,
                    'last_incident_at' => $currentTime,
                    'total_alerts' => 1,
                    'critical_alerts' => $severity === 'CRITICAL' ? 1 : 0,
                    'auto_block_count' => $autoBlocked ? 1 : 0,
                    'status' => $newStatus,
                    'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE)
                ]);
                
                self::$cache[$ipAddress] = [
                    'score' => $baseScore,
                    'status' => $newStatus,
                    'last_incident_at' => $currentTime
                ];
            }
            
            $db->commit();
            
            if ($newScore >= self::THRESHOLD_AUTO_BLOCK && !$autoBlocked) {
                self::triggerPreemptiveBlock($ipAddress, $newScore, $db);
            }
            
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log("ReputationEngine: Failed to record incident - " . $e->getMessage());
        }
    }
    
    private static function calculateEscalationMultiplier($hoursSinceLastIncident) {
        if ($hoursSinceLastIncident >= self::ESCALATION_WINDOW_HOURS) {
            return self::ESCALATION_MULTIPLIER_BASE;
        }
        
        $multiplier = self::ESCALATION_MULTIPLIER_BASE + (1 - ($hoursSinceLastIncident / self::ESCALATION_WINDOW_HOURS)) * (self::ESCALATION_MULTIPLIER_MAX - self::ESCALATION_MULTIPLIER_BASE);
        
        return min(self::ESCALATION_MULTIPLIER_MAX, $multiplier);
    }
    
    private static function calculateStatus($score) {
        if ($score >= self::THRESHOLD_MALICIOUS) {
            return self::STATUS_MALICIOUS;
        } elseif ($score >= self::THRESHOLD_SUSPICIOUS) {
            return self::STATUS_SUSPICIOUS;
        } else {
            return self::STATUS_NORMAL;
        }
    }
    
    public static function getReputation($ipAddress) {
        if (!$ipAddress || $ipAddress === '0.0.0.0') {
            return [
                'score' => 0,
                'status' => self::STATUS_NORMAL,
                'block_multiplier' => 1.0,
                'rate_limit_multiplier' => 1.0
            ];
        }
        
        if (isset(self::$cache[$ipAddress])) {
            $cached = self::$cache[$ipAddress];
            return [
                'score' => $cached['score'],
                'status' => $cached['status'],
                'block_multiplier' => self::getBlockDurationMultiplier($cached['score']),
                'rate_limit_multiplier' => self::getRateLimitMultiplier($cached['score'])
            ];
        }
        
        try {
            $db = Database::getInstance()->getConnection();
            
            $stmt = $db->prepare("
                SELECT reputation_score, status, last_incident_at
                FROM ip_reputation
                WHERE ip_address = :ip_address
            ");
            
            $stmt->execute(['ip_address' => $ipAddress]);
            $reputation = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($reputation) {
                $score = (int)$reputation['reputation_score'];
                $status = $reputation['status'];
                
                self::$cache[$ipAddress] = [
                    'score' => $score,
                    'status' => $status,
                    'last_incident_at' => $reputation['last_incident_at']
                ];
                
                return [
                    'score' => $score,
                    'status' => $status,
                    'block_multiplier' => self::getBlockDurationMultiplier($score),
                    'rate_limit_multiplier' => self::getRateLimitMultiplier($score)
                ];
            } else {
                return [
                    'score' => 0,
                    'status' => self::STATUS_NORMAL,
                    'block_multiplier' => 1.0,
                    'rate_limit_multiplier' => 1.0
                ];
            }
        } catch (Exception $e) {
            error_log("ReputationEngine: Failed to get reputation - " . $e->getMessage());
            return [
                'score' => 0,
                'status' => self::STATUS_NORMAL,
                'block_multiplier' => 1.0,
                'rate_limit_multiplier' => 1.0
            ];
        }
    }
    
    public static function getBlockDurationMultiplier($score) {
        if ($score <= 0) {
            return 1.0;
        } elseif ($score < 20) {
            return 1.0;
        } elseif ($score < 40) {
            return 1.5;
        } elseif ($score < 60) {
            return 2.0;
        } elseif ($score < 80) {
            return 3.0;
        } else {
            return 5.0;
        }
    }
    
    public static function getRateLimitMultiplier($score) {
        if ($score <= 0) {
            return 0.9;
        } elseif ($score < 20) {
            return 1.0;
        } elseif ($score < 40) {
            return 1.5;
        } elseif ($score < 60) {
            return 2.0;
        } else {
            return 3.0;
        }
    }
    
    private static function triggerPreemptiveBlock($ipAddress, $score, $db) {
        try {
            $multiplier = self::getBlockDurationMultiplier($score);
            $baseDuration = 3600;
            $blockDuration = (int)($baseDuration * $multiplier);
            $blockedUntil = date('Y-m-d H:i:s', time() + $blockDuration);
            
            $stmt = $db->prepare("
                INSERT INTO blocked_ips (ip_address, blocked_at, blocked_until, reason, auto_unblock, rule_id)
                VALUES (:ip_address, NOW(), :blocked_until, :reason, 1, NULL)
                ON DUPLICATE KEY UPDATE
                blocked_until = GREATEST(blocked_until, :blocked_until),
                reason = :reason
            ");
            
            $stmt->execute([
                'ip_address' => $ipAddress,
                'blocked_until' => $blockedUntil,
                'reason' => 'REPUTATION_BASED: score=' . $score
            ]);
            
            AuditLogger::log(
                'IP_PREEMPTIVE_BLOCK',
                AuditLogger::SEVERITY_WARNING,
                [
                    'ip_address' => $ipAddress,
                    'reputation_score' => $score,
                    'block_duration' => $blockDuration,
                    'status' => AuditLogger::STATUS_SUCCESS
                ]
            );
            
            error_log("ReputationEngine: Preemptive block triggered for IP $ipAddress (score: $score, duration: $blockDuration)");
        } catch (Exception $e) {
            error_log("ReputationEngine: Failed to trigger preemptive block - " . $e->getMessage());
        }
    }
    
    public static function applyDecay() {
        try {
            $db = Database::getInstance()->getConnection();
            
            $decayCutoff = date('Y-m-d H:i:s', time() - (self::DECAY_INTERVAL_HOURS * 3600));
            
            $stmt = $db->prepare("
                SELECT id, reputation_score, last_incident_at, status
                FROM ip_reputation
                WHERE reputation_score >= :min_score
                AND last_incident_at < :decay_cutoff
                AND status != :normal_status
            ");
            
            $stmt->execute([
                'min_score' => self::MIN_SCORE_FOR_DECAY,
                'decay_cutoff' => $decayCutoff,
                'normal_status' => self::STATUS_NORMAL
            ]);
            
            $reputations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $decayCount = 0;
            
            foreach ($reputations as $rep) {
                $oldScore = (int)$rep['reputation_score'];
                $decayAmount = max(1, (int)ceil($oldScore * self::DECAY_RATE));
                $newScore = max(self::SCORE_MIN, $oldScore - $decayAmount);
                $newStatus = self::calculateStatus($newScore);
                
                $stmt = $db->prepare("
                    UPDATE ip_reputation
                    SET reputation_score = :reputation_score,
                        status = :status,
                        last_decay_at = NOW(),
                        updated_at = NOW()
                    WHERE id = :id
                ");
                
                $stmt->execute([
                    'reputation_score' => $newScore,
                    'status' => $newStatus,
                    'id' => $rep['id']
                ]);
                
                $decayCount++;
                
                if (isset(self::$cache[$rep['ip_address']])) {
                    unset(self::$cache[$rep['ip_address']]);
                }
            }
            
            if ($decayCount > 0) {
                error_log("ReputationEngine: Applied decay to $decayCount IPs");
            }
            
            return $decayCount;
        } catch (Exception $e) {
            error_log("ReputationEngine: Decay failed - " . $e->getMessage());
            return 0;
        }
    }
    
    public static function cleanupOldRecords($daysOld = 365) {
        try {
            $db = Database::getInstance()->getConnection();
            
            $cutoffDate = date('Y-m-d H:i:s', time() - ($daysOld * 24 * 3600));
            
            $stmt = $db->prepare("
                DELETE FROM ip_reputation
                WHERE last_seen < :cutoff_date
                AND status = :normal_status
                AND reputation_score <= 0
                AND total_alerts <= 1
            ");
            
            $stmt->execute([
                'cutoff_date' => $cutoffDate,
                'normal_status' => self::STATUS_NORMAL
            ]);
            
            $deletedCount = $stmt->rowCount();
            
            if ($deletedCount > 0) {
                error_log("ReputationEngine: Cleaned up $deletedCount old reputation records");
            }
            
            return $deletedCount;
        } catch (Exception $e) {
            error_log("ReputationEngine: Cleanup failed - " . $e->getMessage());
            return 0;
        }
    }
    
    public static function getTopMalicious($limit = 20) {
        try {
            $db = Database::getInstance()->getConnection();
            
            $stmt = $db->prepare("
                SELECT ip_address, reputation_score, status, total_alerts, critical_alerts, last_incident_at
                FROM ip_reputation
                WHERE status = :malicious_status
                ORDER BY reputation_score DESC, last_incident_at DESC
                LIMIT :limit
            ");
            
            $stmt->bindValue(':malicious_status', self::STATUS_MALICIOUS, PDO::PARAM_STR);
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("ReputationEngine: Failed to get top malicious - " . $e->getMessage());
            return [];
        }
    }
    
    public static function clearCache() {
        self::$cache = [];
    }
}
