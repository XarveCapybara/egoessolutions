USE egoessolution;

DROP TEMPORARY TABLE IF EXISTS tmp_names;
CREATE TEMPORARY TABLE tmp_names (
  name VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
);

INSERT INTO tmp_names (name) VALUES
('Villanueva, Anna Marie'),
('Barbadillo, Kim Lloyd'),
('Reponte, Hanes Michael'),
('Paragile, Lester'),
('Lampitao, Marianyl'),
('Dimas Jr., Ronnie'),
('Daging, Lourince'),
('Cañar Jr., Ronnie'),
('Altamera, CJ'),
('Badillo, Princess Hope'),
('Basmayon, Sherwin'),
('Brillo, Carl Amyd'),
('Buno, Vincent Fel'),
('Dawing, Bernadeth'),
('Del Rosario, Clifford'),
('Dela Pena, Yna Kristine'),
('Engbino, Ara'),
('Flores, Angel Maxine'),
('Gabuya, Lourince'),
('Hagonos, Kristel'),
('Ganad, Gena'),
('Javellana Jr., Florencio'),
('Labiton, Gerald'),
('Labuni, Bonn Enrico'),
('Mentino, Johnrey'),
('Mirafuentes, Kissie'),
('Mindo, Angelyn'),
('Sultan, Christine'),
('Barera, Juan Paolo'),
('Bitazar, Bernie'),
('Cala, Loreen'),
('Castillo, Haniyah'),
('Ebreo, Elaine'),
('Gargar, Cyd'),
('Inot, Christian'),
('Felipe, Emerald'),
('Liray, Dee Lawrence'),
('Morata, Lea'),
('Alberca, Kian'),
('Amatong, Lady Joy'),
('Abatayo, Chylle'),
('Austria, Arvie'),
('Catane, Rachell'),
('Damsid, Mariella'),
('Talledo, Claire'),
('Ramos, Jyceneth'),
('Adormeo, Christian'),
('Enghog, Ranerv'),
('Abadiez, Jhony Roy'),
('Celin, Krissa Jane'),
('Leyson, Princess Diane'),
('Solis, Marc Nataniel'),
('Rebucas, Jemylyn'),
('Gonzaga, Jake'),
('Lisondra, Shiela Marie'),
('Hinoguin, Ana Rose'),
('Tungal, Grechel'),
('Quimada, Karen'),
('Abalayan, Alce Mae'),
('Arreza, Anjayla'),
('Amacio, Angelo'),
('Anas, Jan'),
('Bautista, Judy'),
('Bunod, Brazell'),
('Cuadra, Cassiopia'),
('Donayre, Anikka'),
('Flores, Princess Abegail'),
('Torres. Lux'),
('Malinao, Kurt'),
('Sialonggo, Jessie'),
('Tabasa, Ressamae'),
('Yecyec, Mekyla'),
('Adona, Jolia Denise'),
('Alberca, Aireen'),
('Balboa, Edmund John'),
('Binoya, Rose'),
('Bughaw, Karen'),
('Caballero, Jenny'),
('Dag-uman, Precious'),
('Duhilag, Novie Jean'),
('Gambong, Blessie'),
('Gapor, Lovely Kate'),
('Jamad, Davey'),
('Martizano, Reyamrk'),
('Matab, Loiuse Mikaella'),
('Mentino, Cherie Ann'),
('Pacheco, Leah'),
('Robledo, Christine'),
('Sabay, Faith'),
('Sombilon, Joriz'),
('Superales, Lorive'),
('Tawi, Khenrich'),
('Velchez, Irish'),
('Estoconing, Jasmin Beth'),
('Abapo, Zein'),
('Alquizar, Dante'),
('Amad, Lee Arthur'),
('Madrid, Joy Love'),
('Serrano, Jan'),
('Parantar, Deza'),
('Ymas, Angelo');

DROP TEMPORARY TABLE IF EXISTS tmp_names_distinct;
CREATE TEMPORARY TABLE tmp_names_distinct AS
SELECT DISTINCT TRIM(name) AS name
FROM tmp_names
WHERE TRIM(name) <> '';

SET @pwd_hash = '$2y$10$KmrzkEGFf8N85FEuFvBg4O/ptyE74nGYTVSmUVfxOQds08zrDdb62';

INSERT INTO users (role, full_name, email, password_hash, is_active)
SELECT
  'employee' AS role,
  n.name AS full_name,
  LOWER(CONCAT('emp', LPAD(CRC32(n.name), 10, '0'), '@egoes.com')) AS email,
  @pwd_hash AS password_hash,
  1 AS is_active
FROM tmp_names_distinct n
WHERE NOT EXISTS (
  SELECT 1
  FROM users u
  WHERE u.full_name COLLATE utf8mb4_general_ci = n.name COLLATE utf8mb4_general_ci
);

INSERT INTO employees (user_id, employee_code)
SELECT
  u.id,
  CONCAT('E-', LPAD(u.id, 5, '0')) AS employee_code
FROM users u
JOIN tmp_names_distinct n
  ON u.full_name COLLATE utf8mb4_general_ci = n.name COLLATE utf8mb4_general_ci
LEFT JOIN employees e ON e.user_id = u.id
WHERE e.user_id IS NULL;

SELECT
  (SELECT COUNT(*)
   FROM users u
   JOIN tmp_names_distinct n
     ON u.full_name COLLATE utf8mb4_general_ci = n.name COLLATE utf8mb4_general_ci) AS users_total_from_list,
  (SELECT COUNT(*)
   FROM employees e
   JOIN users u ON u.id = e.user_id
   JOIN tmp_names_distinct n
     ON u.full_name COLLATE utf8mb4_general_ci = n.name COLLATE utf8mb4_general_ci) AS employees_total_from_list;
