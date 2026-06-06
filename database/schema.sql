-- Complete production database schema
-- CREATE DATABASE IF NOT EXISTS whatsapp_prod;
-- USE whatsapp_prod;

-- Tenants table with rate limiting
CREATE TABLE IF NOT EXISTS tenants (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_key VARCHAR(50) UNIQUE NOT NULL,
    company_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20),
    subscription_plan ENUM('basic', 'professional', 'enterprise') DEFAULT 'basic',
    rate_limit_per_minute INT DEFAULT 20,
    rate_limit_per_hour INT DEFAULT 200,
    rate_limit_per_day INT DEFAULT 1000,
    messages_sent_today INT DEFAULT 0,
    total_messages_sent INT DEFAULT 0,
    max_messages_limit INT DEFAULT 1000,
    last_reset_date DATE,
    status ENUM('active', 'suspended', 'expired') DEFAULT 'active',
    settings JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    username VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    two_factor_secret VARCHAR(255) DEFAULT NULL,
    role ENUM('admin', 'manager', 'sender', 'superadmin') DEFAULT 'sender',
    permissions JSON DEFAULT NULL,
    last_login TIMESTAMP NULL DEFAULT NULL,
    last_ip VARCHAR(45) DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user (tenant_id, email)
);

-- WhatsApp sessions
CREATE TABLE IF NOT EXISTS whatsapp_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    session_id VARCHAR(100) UNIQUE NOT NULL,
    phone_number VARCHAR(20) DEFAULT NULL,
    device_name VARCHAR(255) DEFAULT NULL,
    qr_code TEXT DEFAULT NULL,
    status ENUM('pending', 'scanning', 'connecting', 'connected', 'disconnected', 'expired', 'banned') DEFAULT 'pending',
    connection_status JSON DEFAULT NULL,
    last_qr_generated TIMESTAMP NULL DEFAULT NULL,
    last_connected TIMESTAMP NULL DEFAULT NULL,
    last_disconnected TIMESTAMP NULL DEFAULT NULL,
    reconnect_attempts INT DEFAULT 0,
    is_default BOOLEAN DEFAULT FALSE,
    webhook_url VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- Templates
CREATE TABLE IF NOT EXISTS templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(50) DEFAULT NULL,
    message TEXT NOT NULL,
    image_url VARCHAR(500) DEFAULT NULL,
    image_base64 TEXT DEFAULT NULL,
    variables JSON DEFAULT NULL,
    delay_between_messages INT DEFAULT 2000,
    retry_attempts INT DEFAULT 3,
    status ENUM('active', 'inactive', 'draft') DEFAULT 'draft',
    version INT DEFAULT 1,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- Contact groups
CREATE TABLE IF NOT EXISTS contact_groups (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    total_contacts INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- Contacts
CREATE TABLE IF NOT EXISTS contacts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    group_id INT DEFAULT NULL,
    phone_number VARCHAR(20) NOT NULL,
    country_code VARCHAR(5) DEFAULT NULL,
    name VARCHAR(255) DEFAULT NULL,
    email VARCHAR(255) DEFAULT NULL,
    custom_fields JSON DEFAULT NULL,
    tags JSON DEFAULT NULL,
    status ENUM('active', 'unsubscribed', 'blocked', 'invalid') DEFAULT 'active',
    last_message_sent TIMESTAMP NULL DEFAULT NULL,
    total_messages_sent INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES contact_groups(id) ON DELETE SET NULL,
    UNIQUE KEY unique_contact_tenant (tenant_id, phone_number)
);

-- Bulk campaigns
CREATE TABLE IF NOT EXISTS bulk_campaigns (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    campaign_name VARCHAR(255) NOT NULL,
    template_id INT DEFAULT NULL,
    group_id INT DEFAULT NULL,
    schedule_type ENUM('immediate', 'scheduled', 'recurring') DEFAULT 'immediate',
    scheduled_time TIMESTAMP NULL DEFAULT NULL,
    recurring_pattern VARCHAR(50) DEFAULT NULL,
    total_contacts INT DEFAULT 0,
    sent_count INT DEFAULT 0,
    delivered_count INT DEFAULT 0,
    failed_count INT DEFAULT 0,
    pending_count INT DEFAULT 0,
    rate_limit INT DEFAULT 20,
    delay_between_messages INT DEFAULT 2000,
    status ENUM('draft', 'pending', 'processing', 'paused', 'completed', 'failed', 'scheduled') DEFAULT 'draft',
    priority INT DEFAULT 1,
    error_details TEXT DEFAULT NULL,
    start_time TIMESTAMP NULL DEFAULT NULL,
    end_time TIMESTAMP NULL DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES templates(id) ON DELETE SET NULL,
    FOREIGN KEY (group_id) REFERENCES contact_groups(id) ON DELETE SET NULL
);

-- Campaign contacts
CREATE TABLE IF NOT EXISTS campaign_contacts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    campaign_id INT NOT NULL,
    contact_id INT NOT NULL,
    status ENUM('pending', 'processing', 'sent', 'delivered', 'failed', 'read') DEFAULT 'pending',
    attempt_count INT DEFAULT 0,
    last_attempt TIMESTAMP NULL DEFAULT NULL,
    sent_at TIMESTAMP NULL DEFAULT NULL,
    delivered_at TIMESTAMP NULL DEFAULT NULL,
    read_at TIMESTAMP NULL DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    message_id VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (campaign_id) REFERENCES bulk_campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE
);

-- Detailed message logs
CREATE TABLE IF NOT EXISTS message_logs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    campaign_id INT DEFAULT NULL,
    contact_id INT DEFAULT NULL,
    session_id VARCHAR(100) DEFAULT NULL,
    phone_number VARCHAR(20) NOT NULL,
    message_content TEXT DEFAULT NULL,
    message_type ENUM('text', 'image', 'video', 'document', 'template') DEFAULT 'text',
    media_url TEXT DEFAULT NULL,
    status ENUM('queued', 'sent', 'delivered', 'read', 'failed', 'expired') DEFAULT 'queued',
    error_code VARCHAR(50) DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    whatsapp_message_id VARCHAR(255) DEFAULT NULL,
    sent_at TIMESTAMP NULL DEFAULT NULL,
    delivered_at TIMESTAMP NULL DEFAULT NULL,
    read_at TIMESTAMP NULL DEFAULT NULL,
    retry_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (campaign_id) REFERENCES bulk_campaigns(id) ON DELETE SET NULL,
    FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL
);

-- Rate limit logs
CREATE TABLE IF NOT EXISTS rate_limit_logs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    session_id VARCHAR(100) DEFAULT NULL,
    action VARCHAR(50) NOT NULL,
    phone_number VARCHAR(20) DEFAULT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- Blocklist
CREATE TABLE IF NOT EXISTS blocklist (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    reason VARCHAR(255) DEFAULT NULL,
    blocked_by INT DEFAULT NULL,
    blocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_block (tenant_id, phone_number),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- API keys
CREATE TABLE IF NOT EXISTS api_keys (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    api_key VARCHAR(64) UNIQUE NOT NULL,
    api_secret VARCHAR(255) NOT NULL,
    name VARCHAR(255) DEFAULT NULL,
    permissions JSON DEFAULT NULL,
    last_used TIMESTAMP NULL DEFAULT NULL,
    expires_at TIMESTAMP NULL DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
