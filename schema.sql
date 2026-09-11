CREATE DATABASE IF NOT EXISTS northstar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE northstar;

CREATE TABLE companies (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(180) NOT NULL,
  category VARCHAR(100) NOT NULL,
  ownership VARCHAR(180) NOT NULL,
  status VARCHAR(50) NOT NULL,
  metric VARCHAR(180),
  as_of_date DATE,
  description TEXT,
  source_name VARCHAR(180),
  source_url VARCHAR(2048),
  confidence ENUM('Verified','Refresh','Review') NOT NULL DEFAULT 'Review',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_company_name (name), KEY idx_company_category (category), KEY idx_company_owner (ownership), FULLTEXT KEY ft_company_research (name, category, ownership, description)
);

CREATE TABLE data_centers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(180) NOT NULL, category VARCHAR(100) NOT NULL, location VARCHAR(180) NOT NULL,
  owner VARCHAR(180), builder VARCHAR(180), power VARCHAR(100), tenant VARCHAR(180), financing VARCHAR(255), status VARCHAR(50),
  as_of_date DATE, description TEXT, source_name VARCHAR(180), source_url VARCHAR(2048),
  confidence ENUM('Verified','Refresh','Review') NOT NULL DEFAULT 'Review',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_dc_name (name), KEY idx_dc_region (category), KEY idx_dc_owner (owner)
);

CREATE TABLE spacs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(180) NOT NULL, category VARCHAR(100) NOT NULL, sponsor VARCHAR(180), ipo_size VARCHAR(80), deadline DATE,
  remaining VARCHAR(80), status VARCHAR(50), as_of_date DATE, description TEXT, source_name VARCHAR(180), source_url VARCHAR(2048),
  confidence ENUM('Verified','Refresh','Review') NOT NULL DEFAULT 'Review',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_spac_name (name), KEY idx_spac_deadline (deadline), KEY idx_spac_sponsor (sponsor)
);

INSERT INTO companies (name,category,ownership,status,metric,as_of_date,description,source_name,source_url,confidence) VALUES
('Model N','Enterprise Software','Vista Equity Partners','Private','$1.25B take-private','2024-06-27','Revenue optimization and compliance software for life sciences and high-tech companies.','Company announcement','https://www.modeln.com/company/news/media-center/vista-equity-partners-completes-acquisition-of-model-n/','Verified'),
('Cloudera','Data & Analytics','CD&R / KKR','PE-backed','$5.3B take-private','2021-10-08','Enterprise data cloud platform spanning data engineering, analytics, and machine learning.','Company announcement','https://www.cloudera.com/about/news-and-blogs/press-releases/2021-10-08-cloudera-completes-agreement-to-be-acquired-by-cd-r-and-kkr.html','Verified'),
('Proofpoint','Cybersecurity','Thoma Bravo','PE-backed','$12.3B take-private','2021-08-31','Human-centric cybersecurity and compliance software for enterprises.','Company announcement','https://www.proofpoint.com/us/newsroom/press-releases/proofpoint-announces-closing-acquisition-thoma-bravo','Verified'),
('Harvey','AI Applications','Venture-backed','Private','Valuation requires refresh','2026-09-11','Domain-specific generative AI platform for legal and professional services workflows.','Workspace research','#','Review'),
('Dataiku','AI Platforms','Venture-backed','Private','Valuation requires refresh','2026-09-11','Collaborative enterprise platform for analytics, machine learning, and generative AI.','Workspace research','#','Review'),
('CoreWeave','AI Compute','Public','Public','Public market','2026-09-11','Cloud infrastructure optimized for accelerated computing and AI workloads.','Company filings','https://investors.coreweave.com/','Refresh');
