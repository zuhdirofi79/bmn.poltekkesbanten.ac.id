<?php
class SecurityHelpers {
    public static function getClientIp() {
        $ipKeys = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        $fallbackIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        
        if (filter_var($fallbackIp, FILTER_VALIDATE_IP) !== false) {
            return $fallbackIp;
        }
        
        return '0.0.0.0';
    }
    
    public static function getUserAgent() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return substr(trim($userAgent), 0, 255);
    }
    
    public static function isSameSubnet($ip1, $ip2) {
        if ($ip1 === $ip2) {
            return true;
        }
        
        if (!filter_var($ip1, FILTER_VALIDATE_IP) || !filter_var($ip2, FILTER_VALIDATE_IP)) {
            return false;
        }
        
        if (filter_var($ip1, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && 
            filter_var($ip2, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return self::isSameIPv4Subnet($ip1, $ip2);
        }
        
        if (filter_var($ip1, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && 
            filter_var($ip2, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return self::isSameIPv6Prefix($ip1, $ip2);
        }
        
        return false;
    }
    
    private static function isSameIPv4Subnet($ip1, $ip2) {
        $parts1 = explode('.', $ip1);
        $parts2 = explode('.', $ip2);
        
        if (count($parts1) !== 4 || count($parts2) !== 4) {
            return false;
        }
        
        return $parts1[0] === $parts2[0] && 
               $parts1[1] === $parts2[1] && 
               $parts1[2] === $parts2[2];
    }
    
    private static function isSameIPv6Prefix($ip1, $ip2) {
        $ip1Bin = inet_pton($ip1);
        $ip2Bin = inet_pton($ip2);
        
        if ($ip1Bin === false || $ip2Bin === false) {
            return false;
        }
        
        return substr($ip1Bin, 0, 8) === substr($ip2Bin, 0, 8);
    }
}
