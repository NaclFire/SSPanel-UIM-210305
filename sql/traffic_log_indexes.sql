-- Apply once to existing databases. Creating indexes on large tables may take time;
-- schedule this during a maintenance window and check for existing index names first.
ALTER TABLE `user_traffic_log`
  ADD INDEX `idx_traffic_type_id` (`type`, `id`),
  ADD INDEX `idx_traffic_type_log_time` (`type`, `log_time`),
  ADD INDEX `idx_traffic_user_type_id` (`user_id`, `type`, `id`);

ALTER TABLE `user`
  ADD INDEX `idx_user_ref_by` (`ref_by`);
