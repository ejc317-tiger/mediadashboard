CREATE TABLE IF NOT EXISTS users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, email VARCHAR(255) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL, display_name VARCHAR(120) NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, last_login_at TIMESTAMP NULL
);
CREATE TABLE IF NOT EXISTS ai_update_runs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 prompt TEXT NOT NULL,
 status ENUM('Running','Completed','Failed') NOT NULL DEFAULT 'Running',
 records_written INT UNSIGNED NOT NULL DEFAULT 0,
 error_message VARCHAR(500),
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 completed_at TIMESTAMP NULL,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 KEY idx_ai_runs_user_created(user_id,created_at)
);
CREATE TABLE IF NOT EXISTS ai_run_sources (
 run_id BIGINT UNSIGNED NOT NULL, provider VARCHAR(100) NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(run_id,provider), FOREIGN KEY(run_id) REFERENCES ai_update_runs(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS data_sources (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL UNIQUE,
 source_type ENUM('Web search','Licensed database','Regulatory','Company disclosure') NOT NULL,
 enabled BOOLEAN NOT NULL DEFAULT TRUE, last_success_at TIMESTAMP NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS app_settings (
 setting_key VARCHAR(100) PRIMARY KEY, setting_value TEXT NOT NULL,
 updated_by BIGINT UNSIGNED NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
);
INSERT INTO data_sources(name,source_type) VALUES
 ('Web search','Web search'),('PitchBook','Licensed database'),('Crunchbase','Licensed database'),
 ('SEC EDGAR','Regulatory'),('Company and investor disclosures','Company disclosure'),('Additional finance databases','Licensed database')
ON DUPLICATE KEY UPDATE source_type=VALUES(source_type);
DROP TABLE IF EXISTS ai_company_investors;
DROP TABLE IF EXISTS ai_company_vc_investments;
DROP TABLE IF EXISTS pe_companies;
DROP TABLE IF EXISTS ai_companies;
CREATE TABLE IF NOT EXISTS companies (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(180) NOT NULL UNIQUE,
 sector VARCHAR(120) NOT NULL,
 is_ai BOOLEAN NOT NULL DEFAULT FALSE,
 headquarters VARCHAR(180),
 founded_year SMALLINT UNSIGNED,
 description TEXT,
 last_round_date DATE,
 last_round_size DECIMAL(18,2),
 last_round_valuation DECIMAL(18,2),
 valuation_currency CHAR(3),
 source_name VARCHAR(180),
 source_url VARCHAR(2048),
 as_of_date DATE,
 confidence ENUM('Verified','Refresh','Review') DEFAULT 'Review',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_company_sector(sector), KEY idx_company_ai(is_ai), KEY idx_company_valuation(last_round_valuation),
 FULLTEXT KEY ft_company(name,sector,description)
);

CREATE TABLE IF NOT EXISTS private_equity_firms (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(180) NOT NULL UNIQUE,
 strategy VARCHAR(120) NOT NULL,
 headquarters VARCHAR(180),
 description TEXT,
 source_name VARCHAR(180), source_url VARCHAR(2048), as_of_date DATE,
 confidence ENUM('Verified','Refresh','Review') DEFAULT 'Review',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FULLTEXT KEY ft_pe_firm(name,strategy,description)
);

CREATE TABLE IF NOT EXISTS vc_firms (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(180) NOT NULL UNIQUE,
 category VARCHAR(100) NOT NULL DEFAULT 'Venture Capital', headquarters VARCHAR(180), description TEXT,
 source_name VARCHAR(180), source_url VARCHAR(2048), as_of_date DATE,
 confidence ENUM('Verified','Refresh','Review') DEFAULT 'Review',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FULLTEXT KEY ft_vc(name,description)
);

CREATE TABLE IF NOT EXISTS company_pe_ownership (
 company_id BIGINT UNSIGNED NOT NULL, pe_firm_id BIGINT UNSIGNED NOT NULL,
 acquired_date DATE, exited_date DATE, ownership_notes VARCHAR(255), source_url VARCHAR(2048),
 PRIMARY KEY(company_id,pe_firm_id), FOREIGN KEY(company_id) REFERENCES companies(id) ON DELETE CASCADE,
 FOREIGN KEY(pe_firm_id) REFERENCES private_equity_firms(id) ON DELETE CASCADE, KEY idx_pe_owner(pe_firm_id)
);

CREATE TABLE IF NOT EXISTS company_vc_investments (
 company_id BIGINT UNSIGNED NOT NULL, vc_firm_id BIGINT UNSIGNED NOT NULL,
 round_name VARCHAR(100) NOT NULL DEFAULT 'Undisclosed', announced_date DATE, amount DECIMAL(18,2), currency CHAR(3),
 is_lead BOOLEAN DEFAULT FALSE, source_url VARCHAR(2048),
 PRIMARY KEY(company_id,vc_firm_id,round_name), FOREIGN KEY(company_id) REFERENCES companies(id) ON DELETE CASCADE,
 FOREIGN KEY(vc_firm_id) REFERENCES vc_firms(id) ON DELETE CASCADE, KEY idx_vc_investor(vc_firm_id), KEY idx_round_date(announced_date)
);

CREATE TABLE IF NOT EXISTS spac_vehicles (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(180) NOT NULL UNIQUE, sponsor VARCHAR(180) NOT NULL,
 raised_date DATE NOT NULL, ipo_size DECIMAL(18,2) NOT NULL, currency CHAR(3) NOT NULL DEFAULT 'USD',
 deadline DATE NOT NULL, status VARCHAR(50) NOT NULL DEFAULT 'Active', description TEXT,
 source_name VARCHAR(180), source_url VARCHAR(2048), as_of_date DATE,
 confidence ENUM('Verified','Refresh','Review') DEFAULT 'Review',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_spac_deadline(deadline), KEY idx_spac_sponsor(sponsor), KEY idx_spac_raised(raised_date)
);

CREATE TABLE IF NOT EXISTS data_centers (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(180) NOT NULL UNIQUE,
 category VARCHAR(100) NOT NULL, location VARCHAR(180) NOT NULL, latitude DECIMAL(9,6), longitude DECIMAL(9,6),
 owner VARCHAR(180), builder VARCHAR(180), power_mw DECIMAL(10,2), tenant VARCHAR(180), financing VARCHAR(255), status VARCHAR(50),
 as_of_date DATE, description TEXT, source_name VARCHAR(180), source_url VARCHAR(2048), confidence ENUM('Verified','Refresh','Review') DEFAULT 'Review',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_dc_region(category), KEY idx_dc_owner(owner)
);
