CREATE DATABASE IF NOT EXISTS trailbound CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE trailbound;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(80) NOT NULL,
    avatar_class VARCHAR(50) DEFAULT 'wayfarer',
    level INT UNSIGNED NOT NULL DEFAULT 1,
    xp_total BIGINT UNSIGNED NOT NULL DEFAULT 0,
    xp_current_level BIGINT UNSIGNED NOT NULL DEFAULT 0,
    skill_points INT UNSIGNED NOT NULL DEFAULT 0,
    coins BIGINT UNSIGNED NOT NULL DEFAULT 0,
    current_region_id BIGINT UNSIGNED NULL,
    privacy_route_visibility ENUM('private','friends','public') NOT NULL DEFAULT 'private',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS strava_connections (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    strava_athlete_id BIGINT UNSIGNED NOT NULL UNIQUE,
    access_token TEXT NOT NULL,
    refresh_token TEXT NOT NULL,
    token_expires_at DATETIME NOT NULL,
    scope VARCHAR(255) NULL,
    connected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_sync_at DATETIME NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    INDEX idx_strava_athlete_id (strava_athlete_id),
    CONSTRAINT fk_strava_connections_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS activities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    provider ENUM('strava','garmin','manual') NOT NULL DEFAULT 'strava',
    provider_activity_id VARCHAR(100) NOT NULL,
    activity_type VARCHAR(50) NOT NULL,
    name VARCHAR(255) NULL,
    distance_m DECIMAL(10,2) NOT NULL,
    moving_time_s INT UNSIGNED NOT NULL,
    elapsed_time_s INT UNSIGNED NULL,
    average_speed_mps DECIMAL(8,4) NULL,
    average_pace_sec_per_km INT UNSIGNED NULL,
    start_date DATETIME NOT NULL,
    timezone VARCHAR(80) NULL,
    summary_polyline TEXT NULL,
    start_lat DECIMAL(10,7) NULL,
    start_lng DECIMAL(10,7) NULL,
    end_lat DECIMAL(10,7) NULL,
    end_lng DECIMAL(10,7) NULL,
    raw_json JSON NULL,
    validation_status ENUM('valid','flagged','ignored') NOT NULL DEFAULT 'valid',
    validation_reason VARCHAR(255) NULL,
    xp_awarded INT UNSIGNED NOT NULL DEFAULT 0,
    processed_at DATETIME NULL,
    imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_provider_activity (provider, provider_activity_id),
    INDEX idx_activities_user_date (user_id, start_date),
    CONSTRAINT fk_activities_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_stats (
    user_id BIGINT UNSIGNED PRIMARY KEY,
    total_distance_m DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_moving_time_s BIGINT UNSIGNED NOT NULL DEFAULT 0,
    total_runs INT UNSIGNED NOT NULL DEFAULT 0,
    current_week_runs INT UNSIGNED NOT NULL DEFAULT 0,
    current_week_distance_m DECIMAL(12,2) NOT NULL DEFAULT 0,
    current_streak_weeks INT UNSIGNED NOT NULL DEFAULT 0,
    best_streak_weeks INT UNSIGNED NOT NULL DEFAULT 0,
    chests_opened INT UNSIGNED NOT NULL DEFAULT 0,
    areas_discovered INT UNSIGNED NOT NULL DEFAULT 0,
    last_activity_at DATETIME NULL,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_stats_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS regions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(80) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    description TEXT NULL,
    lore TEXT NULL,
    unlock_requirement_type VARCHAR(80) NULL,
    unlock_requirement_value INT UNSIGNED NULL,
    distance_required_m DECIMAL(12,2) NOT NULL DEFAULT 0,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    map_asset VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_regions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    region_id BIGINT UNSIGNED NOT NULL,
    progress_m DECIMAL(12,2) NOT NULL DEFAULT 0,
    is_unlocked TINYINT(1) NOT NULL DEFAULT 0,
    unlocked_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_region (user_id, region_id),
    INDEX idx_user_regions_user (user_id),
    CONSTRAINT fk_user_regions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_regions_region FOREIGN KEY (region_id) REFERENCES regions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS quests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(160) NOT NULL,
    description TEXT NOT NULL,
    quest_type ENUM('main','daily','weekly','exploration','challenge','boss') NOT NULL,
    objective_type VARCHAR(80) NOT NULL,
    target_value DECIMAL(12,2) NOT NULL,
    reward_xp INT UNSIGNED NOT NULL DEFAULT 0,
    reward_coins INT UNSIGNED NOT NULL DEFAULT 0,
    reward_chest_type VARCHAR(80) NULL,
    required_region_id BIGINT UNSIGNED NULL,
    repeatable TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_quests_type_active (quest_type, is_active),
    CONSTRAINT fk_quests_region FOREIGN KEY (required_region_id) REFERENCES regions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_quests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    quest_id BIGINT UNSIGNED NOT NULL,
    status ENUM('active','completed','claimed','expired') NOT NULL DEFAULT 'active',
    progress_value DECIMAL(12,2) NOT NULL DEFAULT 0,
    completed_at DATETIME NULL,
    claimed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_quest (user_id, quest_id),
    INDEX idx_user_quests_status (user_id, status),
    CONSTRAINT fk_user_quests_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_quests_quest FOREIGN KEY (quest_id) REFERENCES quests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS chests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(80) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    rarity ENUM('common','magic','rare','epic','legendary') NOT NULL DEFAULT 'common',
    description TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_chests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    chest_id BIGINT UNSIGNED NOT NULL,
    source_type VARCHAR(80) NULL,
    source_id BIGINT UNSIGNED NULL,
    opened_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_chests_user_opened (user_id, opened_at),
    CONSTRAINT fk_user_chests_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_chests_chest FOREIGN KEY (chest_id) REFERENCES chests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(160) NOT NULL,
    item_type ENUM('head','cloak','boots','weapon_skin','aura','title','badge','frame') NOT NULL,
    rarity ENUM('common','magic','rare','epic','legendary') NOT NULL DEFAULT 'common',
    description TEXT NULL,
    asset_path VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS chest_loot_table (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chest_id BIGINT UNSIGNED NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    weight INT UNSIGNED NOT NULL DEFAULT 100,
    min_level INT UNSIGNED NOT NULL DEFAULT 1,
    CONSTRAINT fk_chest_loot_chest FOREIGN KEY (chest_id) REFERENCES chests(id) ON DELETE CASCADE,
    CONSTRAINT fk_chest_loot_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    acquired_from VARCHAR(80) NULL,
    acquired_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_equipped TINYINT(1) NOT NULL DEFAULT 0,
    INDEX idx_user_items_user (user_id),
    CONSTRAINT fk_user_items_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_items_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS skill_nodes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(160) NOT NULL,
    branch ENUM('endurance','explorer','tempo') NOT NULL,
    description TEXT NOT NULL,
    effect_type VARCHAR(80) NOT NULL,
    effect_value DECIMAL(10,4) NOT NULL DEFAULT 0,
    cost INT UNSIGNED NOT NULL DEFAULT 1,
    position_x INT NOT NULL DEFAULT 0,
    position_y INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS skill_node_prerequisites (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    node_id BIGINT UNSIGNED NOT NULL,
    prerequisite_node_id BIGINT UNSIGNED NOT NULL,
    UNIQUE KEY uniq_skill_prereq (node_id, prerequisite_node_id),
    CONSTRAINT fk_skill_prereq_node FOREIGN KEY (node_id) REFERENCES skill_nodes(id) ON DELETE CASCADE,
    CONSTRAINT fk_skill_prereq_required FOREIGN KEY (prerequisite_node_id) REFERENCES skill_nodes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_skill_nodes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    node_id BIGINT UNSIGNED NOT NULL,
    unlocked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_skill_node (user_id, node_id),
    CONSTRAINT fk_user_skill_nodes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_skill_nodes_node FOREIGN KEY (node_id) REFERENCES skill_nodes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rewards_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    activity_id BIGINT UNSIGNED NULL,
    reward_type ENUM('xp','coins','chest','item','skill_point','region_unlock','title') NOT NULL,
    reward_ref_id BIGINT UNSIGNED NULL,
    amount INT UNSIGNED NULL,
    description VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rewards_user_date (user_id, created_at),
    CONSTRAINT fk_rewards_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_rewards_activity FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    type VARCHAR(80) NOT NULL,
    title VARCHAR(160) NOT NULL,
    message TEXT NOT NULL,
    data_json JSON NULL,
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notifications_user_read (user_id, read_at),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS strava_webhook_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    object_type VARCHAR(50) NOT NULL,
    object_id BIGINT UNSIGNED NOT NULL,
    aspect_type VARCHAR(50) NOT NULL,
    owner_id BIGINT UNSIGNED NOT NULL,
    event_time INT UNSIGNED NOT NULL,
    updates_json JSON NULL,
    processed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_strava_webhook_unprocessed (processed_at, created_at),
    INDEX idx_strava_webhook_owner (owner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO regions (code, name, description, lore, distance_required_m, sort_order)
VALUES
('first_road', 'The First Road', 'Your journey begins here.', 'A cold road cuts through the mist. Every step wakes the world.', 2000, 1),
('ashwood_gate', 'Ashwood Gate', 'An old forest gate hidden in fog.', 'The trees lean inward as if listening.', 5000, 2),
('broken_shrine', 'The Broken Shrine', 'A ruined shrine beyond the first forest.', 'Stone, moss, and something still glowing beneath the cracks.', 10000, 3),
('mistfen_crossing', 'Mistfen Crossing', 'A wetland path swallowed by pale mist.', 'The ground moves softly underfoot.', 15000, 4),
('ember_hills', 'The Ember Hills', 'Hills lit by distant red fire.', 'At sunset the stones pulse like coals.', 25000, 5);

INSERT IGNORE INTO chests (code, name, rarity, description)
VALUES
('old_travel_chest', 'Old Travel Chest', 'common', 'A worn chest found beside the first road.'),
('scout_chest', 'Scout Chest', 'magic', 'A light chest marked with a faded trail rune.'),
('shrine_chest', 'Shrine Chest', 'rare', 'A sealed chest recovered from a forgotten shrine.');

INSERT IGNORE INTO items (code, name, item_type, rarity, description)
VALUES
('weathered_boots', 'Weathered Boots', 'boots', 'common', 'Old boots with more road left in them.'),
('ashwood_cloak', 'Ashwood Cloak', 'cloak', 'magic', 'A dark cloak carrying the scent of rain and bark.'),
('lantern_first_road', 'Lantern of the First Road', 'aura', 'rare', 'A soft lantern glow that follows every step.'),
('title_first_steps', 'Title: First Steps', 'title', 'common', 'Awarded to those who begin the road.');

INSERT IGNORE INTO skill_nodes (code, name, branch, description, effect_type, effect_value, cost, position_x, position_y)
VALUES
('steady_feet', 'Steady Feet', 'endurance', '+5% XP from runs over 3km.', 'xp_over_distance_percent', 0.05, 1, 0, 0),
('long_road', 'Long Road', 'endurance', '+10% area progress from runs over 5km.', 'area_progress_over_distance_percent', 0.10, 1, 100, 80),
('cartographer', 'Cartographer', 'explorer', '+10% discovery progress on new routes.', 'new_route_discovery_percent', 0.10, 1, 0, 160),
('hidden_trails', 'Hidden Trails', 'explorer', 'Small bonus chance to earn exploration chests.', 'exploration_chest_chance', 0.03, 1, 100, 240),
('quickstep', 'Quickstep', 'tempo', 'Bonus XP when beating your previous average pace.', 'pace_improvement_xp_percent', 0.10, 1, 0, 320);

INSERT IGNORE INTO quests (code, name, description, quest_type, objective_type, target_value, reward_xp, reward_chest_type)
VALUES
('the_first_road', 'The First Road', 'Complete one run of at least 2km.', 'main', 'single_run_distance_m', 2000, 300, 'old_travel_chest'),
('footsteps_through_fog', 'Footsteps Through Fog', 'Complete 3 runs this week.', 'weekly', 'weekly_run_count', 3, 600, 'scout_chest'),
('beyond_the_watchfire', 'Beyond the Watchfire', 'Run 10km total and reach the Broken Shrine.', 'main', 'total_distance_m', 10000, 700, 'shrine_chest');
