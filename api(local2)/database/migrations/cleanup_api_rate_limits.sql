-- Optional cleanup query to run periodically (suggested: daily cron job)
-- Cleans up old rate limit records older than 24 hours

-- DELETE FROM api_rate_limits 
-- WHERE window_start < DATE_SUB(NOW(), INTERVAL 24 HOUR);
