INSERT INTO services (name) VALUES
('Netflix'),
('Disney+'),
('Spotify'),
('YouTube Premium');

INSERT INTO geographies (name, region) VALUES
('United States', 'North America'),
('Canada', 'North America'),
('United Kingdom', 'Europe'),
('Germany', 'Europe'),
('Brazil', 'Latin America'),
('Mexico', 'Latin America'),
('India', 'Asia-Pacific'),
('Japan', 'Asia-Pacific'),
('South Africa', 'Middle East & Africa');

INSERT INTO plans (service_id, name, tier, billing_period) VALUES
(1, 'Standard with Ads', 'Streaming', 'month'),
(1, 'Standard', 'Streaming', 'month'),
(1, 'Premium', 'Streaming', 'month'),
(2, 'Standard', 'Streaming', 'month'),
(2, 'Premium', 'Streaming', 'month'),
(2, 'Premium', 'Streaming', 'year'),
(3, 'Individual', 'Music', 'month'),
(3, 'Duo', 'Music', 'month'),
(3, 'Family', 'Music', 'month'),
(4, 'Individual', 'Video + Music', 'month'),
(4, 'Family', 'Video + Music', 'month');

INSERT INTO prices (plan_id, geography_id, currency, price_local, price_usd_monthly, effective_date) VALUES
-- Netflix (two points per market to demonstrate latest snapshot behavior)
(1, 1, 'USD', 6.99, 6.99, '2025-10-01'),
(1, 1, 'USD', 7.99, 7.99, '2026-03-01'),
(2, 1, 'USD', 15.49, 15.49, '2025-10-01'),
(2, 1, 'USD', 16.49, 16.49, '2026-03-01'),
(3, 1, 'USD', 22.99, 22.99, '2026-03-01'),
(1, 3, 'GBP', 5.99, 7.71, '2026-03-01'),
(2, 3, 'GBP', 10.99, 14.14, '2026-03-01'),
(3, 3, 'GBP', 17.99, 23.14, '2026-03-01'),
(1, 5, 'BRL', 18.90, 3.78, '2026-03-01'),
(2, 5, 'BRL', 39.90, 7.98, '2026-03-01'),
(3, 5, 'BRL', 55.90, 11.18, '2026-03-01'),
(1, 7, 'INR', 149.00, 1.79, '2026-03-01'),
(2, 7, 'INR', 499.00, 5.99, '2026-03-01'),
(3, 7, 'INR', 649.00, 7.79, '2026-03-01'),

-- Disney+
(4, 1, 'USD', 9.99, 9.99, '2026-03-01'),
(5, 1, 'USD', 15.99, 15.99, '2026-03-01'),
(6, 1, 'USD', 159.99, 13.33, '2026-03-01'),
(4, 4, 'EUR', 8.99, 9.81, '2026-03-01'),
(5, 4, 'EUR', 13.99, 15.26, '2026-03-01'),
(6, 4, 'EUR', 139.90, 12.72, '2026-03-01'),
(4, 6, 'MXN', 219.00, 12.88, '2026-03-01'),
(5, 6, 'MXN', 299.00, 17.59, '2026-03-01'),
(6, 6, 'MXN', 2989.00, 14.65, '2026-03-01'),

-- Spotify
(7, 1, 'USD', 11.99, 11.99, '2026-03-01'),
(8, 1, 'USD', 16.99, 16.99, '2026-03-01'),
(9, 1, 'USD', 19.99, 19.99, '2026-03-01'),
(7, 8, 'JPY', 1080, 7.10, '2026-03-01'),
(8, 8, 'JPY', 1480, 9.73, '2026-03-01'),
(9, 8, 'JPY', 1780, 11.70, '2026-03-01'),
(7, 9, 'ZAR', 69.99, 3.74, '2026-03-01'),
(8, 9, 'ZAR', 91.99, 4.92, '2026-03-01'),
(9, 9, 'ZAR', 119.99, 6.42, '2026-03-01'),

-- YouTube Premium
(10, 1, 'USD', 13.99, 13.99, '2026-03-01'),
(11, 1, 'USD', 22.99, 22.99, '2026-03-01'),
(10, 2, 'CAD', 12.99, 9.41, '2026-03-01'),
(11, 2, 'CAD', 22.99, 16.65, '2026-03-01'),
(10, 7, 'INR', 129.00, 1.55, '2026-03-01'),
(11, 7, 'INR', 189.00, 2.27, '2026-03-01');
