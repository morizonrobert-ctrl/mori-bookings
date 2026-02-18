-- Sample Buses
INSERT INTO buses (bus_number, plate_number, bus_name, bus_type, total_seats, seat_layout, amenities) VALUES
('MB001', 'KAA 001A', 'MORI Express', 'standard', 40, '2x2', '["WiFi", "AC", "TV", "USB Chargers", "Water"]'),
('MB002', 'KAA 002B', 'MORI Premium', 'premium', 36, '2x2', '["WiFi", "AC", "TV", "USB Chargers", "Water", "Snacks", "Blankets"]'),
('MB003', 'KAA 003C', 'MORI Luxury', 'luxury', 32, '2x2', '["WiFi", "AC", "TV", "USB Chargers", "Water", "Snacks", "Blankets", "Massage Seats", "Entertainment System"]'),
('MB004', 'KAA 004D', 'MORI Sleeper', 'sleeper', 30, '1x2', '["WiFi", "AC", "USB Chargers", "Water", "Blankets", "Pillows", "Privacy Curtains"]'),
('MB005', 'KAA 005E', 'MORI Executive', 'executive', 28, '2x2', '["WiFi", "AC", "TV", "USB Chargers", "Water", "Snacks", "Blankets", "Massage Seats", "Entertainment System", "Meal Service"]');

-- Sample Routes
INSERT INTO routes (route_code, origin_city, origin_terminal, destination_city, destination_terminal, distance_km, estimated_hours, base_fare, premium_fare, luxury_fare, is_active) VALUES
('NRB-MSA', 'Nairobi', 'Machakos Country Bus', 'Mombasa', 'Mombasa Main Bus Station', 485, 8, 1200, 1800, 2400, 1),
('MSA-NRB', 'Mombasa', 'Mombasa Main Bus Station', 'Nairobi', 'Machakos Country Bus', 485, 8, 1200, 1800, 2400, 1),
('NRB-KSM', 'Nairobi', 'Nairobi Railway Station', 'Kisumu', 'Kisumu Bus Park', 345, 6, 900, 1350, 1800, 1),
('KSM-NRB', 'Kisumu', 'Kisumu Bus Park', 'Nairobi', 'Nairobi Railway Station', 345, 6, 900, 1350, 1800, 1),
('NRB-NKR', 'Nairobi', 'Accra Road Station', 'Nakuru', 'Nakuru Bus Stage', 160, 2.5, 500, 750, 1000, 1),
('NKR-NRB', 'Nakuru', 'Nakuru Bus Stage', 'Nairobi', 'Accra Road Station', 160, 2.5, 500, 750, 1000, 1),
('NRB-ELD', 'Nairobi', 'Fig Tree', 'Eldoret', 'Eldoret Bus Park', 310, 5, 800, 1200, 1600, 1),
('ELD-NRB', 'Eldoret', 'Eldoret Bus Park', 'Nairobi', 'Fig Tree', 310, 5, 800, 1200, 1600, 1);

-- Sample Schedules for next 7 days
INSERT INTO schedules (bus_id, route_id, departure_date, departure_time, arrival_date, arrival_time, available_seats, price_factor, status) 
SELECT 
    b.id as bus_id,
    r.id as route_id,
    DATE_ADD(CURDATE(), INTERVAL FLOOR(RAND() * 7) DAY) as departure_date,
    CASE 
        WHEN FLOOR(RAND() * 4) = 0 THEN '06:00:00'
        WHEN FLOOR(RAND() * 4) = 1 THEN '08:00:00'
        WHEN FLOOR(RAND() * 4) = 2 THEN '14:00:00'
        ELSE '19:00:00'
    END as departure_time,
    DATE_ADD(CURDATE(), INTERVAL FLOOR(RAND() * 7) DAY) as arrival_date,
    CASE 
        WHEN FLOOR(RAND() * 4) = 0 THEN '14:00:00'
        WHEN FLOOR(RAND() * 4) = 1 THEN '16:00:00'
        WHEN FLOOR(RAND() * 4) = 2 THEN '22:00:00'
        ELSE '03:00:00'
    END as arrival_time,
    b.total_seats as available_seats,
    1.0 + (RAND() * 0.5) as price_factor, -- Random price factor between 1.0 and 1.5
    'scheduled' as status
FROM buses b
CROSS JOIN routes r
WHERE b.status = 'active' 
AND r.is_active = 1
LIMIT 50;

-- Generate seat maps for all schedules
INSERT INTO seat_maps (schedule_id, seat_number, seat_type, seat_row, seat_column, status)
SELECT 
    s.id as schedule_id,
    CONCAT(
        CHAR(65 + FLOOR((n-1)/4)), -- Row letter A, B, C, etc.
        MOD(n-1, 4) + 1 -- Seat number 1-4
    ) as seat_number,
    CASE 
        WHEN MOD(n-1, 4) + 1 = 1 OR MOD(n-1, 4) + 1 = 4 THEN 'window'
        WHEN MOD(n-1, 4) + 1 = 2 OR MOD(n-1, 4) + 1 = 3 THEN 'aisle'
        ELSE 'standard'
    END as seat_type,
    FLOOR((n-1)/4) + 1 as seat_row,
    MOD(n-1, 4) + 1 as seat_column,
    CASE 
        WHEN RAND() < 0.3 THEN 'booked' -- 30% booked for demo
        ELSE 'available'
    END as status
FROM schedules s
CROSS JOIN (
    SELECT 1 as n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 
    UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11 UNION SELECT 12
    UNION SELECT 13 UNION SELECT 14 UNION SELECT 15 UNION SELECT 16 UNION SELECT 17 UNION SELECT 18
    UNION SELECT 19 UNION SELECT 20 UNION SELECT 21 UNION SELECT 22 UNION SELECT 23 UNION SELECT 24
    UNION SELECT 25 UNION SELECT 26 UNION SELECT 27 UNION SELECT 28 UNION SELECT 29 UNION SELECT 30
    UNION SELECT 31 UNION SELECT 32 UNION SELECT 33 UNION SELECT 34 UNION SELECT 35 UNION SELECT 36
    UNION SELECT 37 UNION SELECT 38 UNION SELECT 39 UNION SELECT 40
) numbers
WHERE n <= (SELECT total_seats FROM buses WHERE id = s.bus_id)
ORDER BY s.id, seat_row, seat_column;