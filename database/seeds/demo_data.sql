-- Demo Data Seed
-- USE whatsapp_prod;

-- 1. Insert default tenant (Standard Business)
INSERT INTO tenants (tenant_key, company_name, email, phone, subscription_plan, rate_limit_per_minute, rate_limit_per_hour, rate_limit_per_day, status, settings)
VALUES (
    'demo-corp-key', 
    'Demo Corporation', 
    'admin@democorp.local', 
    '+15550199', 
    'professional', 
    30, 
    300, 
    2000, 
    'active', 
    '{"timezone": "America/New_York", "retry_delay": 5000}'
)
ON DUPLICATE KEY UPDATE id=id;

-- Get tenant ID for insertion
SET @tenant_id = (SELECT id FROM tenants WHERE tenant_key = 'demo-corp-key' LIMIT 1);

-- 2. Insert admin user (Password: "Password123!")
-- password_hash represents: PASSWORD_BCRYPT hashed value of "Password123!"
INSERT INTO users (tenant_id, username, email, password_hash, role, permissions, is_active)
VALUES (
    @tenant_id, 
    'demo_admin', 
    'admin@democorp.local', 
    '$2y$10$3SWwOa1ZkPfiQkAzNtjAzuxCXRgbuoQ36V.y31/TpbrnHER5uV97.', 
    'admin', 
    '{"all": true}', 
    1
)
ON DUPLICATE KEY UPDATE id=id;

-- 3. Insert demo contact group
INSERT INTO contact_groups (tenant_id, name, description, total_contacts)
VALUES (@tenant_id, 'Beta Users', 'Target list of early beta program participants', 2)
ON DUPLICATE KEY UPDATE id=id;

SET @group_id = (SELECT id FROM contact_groups WHERE name = 'Beta Users' AND tenant_id = @tenant_id LIMIT 1);

-- 4. Insert demo contacts
INSERT INTO contacts (tenant_id, group_id, phone_number, country_code, name, email, custom_fields, tags, status)
VALUES 
(@tenant_id, @group_id, '1555010001', '1', 'Alice Smith', 'alice@example.com', '{"city": "Seattle", "points": "120"}', '["active", "vip"]', 'active'),
(@tenant_id, @group_id, '1555010002', '1', 'Bob Johnson', 'bob@example.com', '{"city": "Boston", "points": "45"}', '["active"]', 'active')
ON DUPLICATE KEY UPDATE id=id;

-- Update group total count
UPDATE contact_groups SET total_contacts = 2 WHERE id = @group_id;

-- 5. Insert template
INSERT INTO templates (tenant_id, name, category, message, delay_between_messages, retry_attempts, status, version)
VALUES (
    @tenant_id, 
    'Welcome Broadcast', 
    'marketing', 
    'Hello {{name}} from {{city}}! Thanks for joining our loyalty program. You currently have {{points}} points.', 
    3000, 
    3, 
    'active', 
    1
)
ON DUPLICATE KEY UPDATE id=id;

-- 6. Insert demo API Key
INSERT INTO api_keys (tenant_id, api_key, api_secret, name, permissions, is_active)
VALUES (
    @tenant_id,
    'apikey_demo_corp_super_secret_hash_64_chars_long_val_here_12345',
    '$2y$10$w09Zk28JkR4aZp9yYq2v7OBzF831uI4Wp5bfeCjT9p07/4KkL34bW',
    'Primary ERP API Key',
    '{"read": true, "write": true}',
    1
)
ON DUPLICATE KEY UPDATE id=id;

-- 7. Seed super admin user
INSERT INTO tenants (tenant_key, company_name, email, subscription_plan, status) 
VALUES ('system', 'System Management', 'superadmin@system.local', 'enterprise', 'active') 
ON DUPLICATE KEY UPDATE id=id;

SET @system_tenant_id = (SELECT id FROM tenants WHERE tenant_key = 'system' LIMIT 1);

INSERT INTO users (tenant_id, username, email, password_hash, role, is_active) 
VALUES (@system_tenant_id, 'superadmin', 'superadmin@system.local', '$2y$10$4kIwjr4CoALCkGoq3QHXzeGw4hly/YBQebySWnMT9lEXQaomULu/y', 'superadmin', 1) 
ON DUPLICATE KEY UPDATE id=id;
