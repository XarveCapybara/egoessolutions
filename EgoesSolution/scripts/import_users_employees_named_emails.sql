-- Import users + employees from name/email list
-- Password hash (bcrypt): egoes default for this batch
USE egoessolution;

SET @pwd = '$2y$10$rRPMLpzfaTgQdVUg465PY.IW//Ps4PGCGLQCxQ5HmRaJ9tXCTeF/2';

DROP TEMPORARY TABLE IF EXISTS tmp_name_email;
CREATE TEMPORARY TABLE tmp_name_email (
  full_name VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  email VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
);

INSERT INTO tmp_name_email (full_name, email) VALUES
('Villanueva, Anna Marie', 'AnnaMarieVillanueva@egoes.com'),
('Barbadillo, Kim Lloyd', 'KimLloydBarbadillo@egoes.com'),
('Reponte, Hanes Michael', 'HanesMichaelReponte@egoes.com'),
('Paragile, Lester', 'LesterParagile@egoes.com'),
('Lampitao, Marianyl', 'MarianylLampitao@egoes.com'),
('Dimas Jr., Ronnie', 'RonnieDimas@egoes.com'),
('Daging, Lourince', 'LourinceDaging@egoes.com'),
('Cañar Jr., Ronnie', 'RonnieCanar@egoes.com'),
('Altamera, CJ', 'CJAltamera@egoes.com'),
('Badillo, Princess Hope', 'PrincessHopeBadillo@egoes.com'),
('Basmayon, Sherwin', 'SherwinBasmayon@egoes.com'),
('Brillo, Carl Amyd', 'CarlAmydBrillo@egoes.com'),
('Buno, Vincent Fel', 'VincentFelBuno@egoes.com'),
('Dawing, Bernadeth', 'BernadethDawing@egoes.com'),
('Del Rosario, Clifford', 'CliffordDelRosario@egoes.com'),
('Dela Pena, Yna Kristine', 'YnaKristineDelaPena@egoes.com'),
('Engbino, Ara', 'AraEngbino@egoes.com'),
('Flores, Angel Maxine', 'AngelMaxineFlores@egoes.com'),
('Gabuya, Lourince', 'LourinceGabuya@egoes.com'),
('Hagonos, Kristel', 'KristelHagonos@egoes.com'),
('Ganad, Gena', 'GenaGanad@egoes.com'),
('Javellana Jr., Florencio', 'FlorencioJavellana@egoes.com'),
('Labiton, Gerald', 'GeraldLabiton@egoes.com'),
('Labuni, Bonn Enrico', 'BonnEnricoLabuni@egoes.com'),
('Mentino, Johnrey', 'JohnreyMentino@egoes.com'),
('Mirafuentes, Kissie', 'KissieMirafuentes@egoes.com'),
('Mindo, Angelyn', 'AngelynMindo@egoes.com'),
('Sultan, Christine', 'ChristineSultan@egoes.com'),
('Barera, Juan Paolo', 'JuanPaoloBarera@egoes.com'),
('Bitazar, Bernie', 'BernieBitazar@egoes.com'),
('Cala, Loreen', 'LoreenCala@egoes.com'),
('Castillo, Haniyah', 'HaniyahCastillo@egoes.com'),
('Ebreo, Elaine', 'ElaineEbreo@egoes.com'),
('Gargar, Cyd', 'CydGargar@egoes.com'),
('Inot, Christian', 'ChristianInot@egoes.com'),
('Felipe, Emerald', 'EmeraldFelipe@egoes.com'),
('Liray, Dee Lawrence', 'DeeLawrenceLiray@egoes.com'),
('Morata, Lea', 'LeaMorata@egoes.com'),
('Alberca, Kian', 'KianAlberca@egoes.com'),
('Amatong, Lady Joy', 'LadyJoyAmatong@egoes.com'),
('Abatayo, Chylle', 'ChylleAbatayo@egoes.com'),
('Austria, Arvie', 'ArvieAustria@egoes.com'),
('Catane, Rachell', 'RachellCatane@egoes.com'),
('Damsid, Mariella', 'MariellaDamsid@egoes.com'),
('Talledo, Claire', 'ClaireTalledo@egoes.com'),
('Ramos, Jyceneth', 'JycenethRamos@egoes.com'),
('Adormeo, Christian', 'ChristianAdormeo@egoes.com'),
('Enghog, Ranerv', 'RanervEnghog@egoes.com'),
('Abadiez, Jhony Roy', 'JhonyRoyAbadiez@egoes.com'),
('Celin, Krissa Jane', 'KrissaJaneCelin@egoes.com'),
('Leyson, Princess Diane', 'PrincessDianeLeyson@egoes.com'),
('Solis, Marc Nataniel', 'MarcNatanielSolis@egoes.com'),
('Rebucas, Jemylyn', 'JemylynRebucas@egoes.com'),
('Gonzaga, Jake', 'JakeGonzaga@egoes.com'),
('Lisondra, Shiela Marie', 'ShielaMarieLisondra@egoes.com'),
('Hinoguin, Ana Rose', 'AnaRoseHinoguin@egoes.com'),
('Tungal, Grechel', 'GrechelTungal@egoes.com'),
('Quimada, Karen', 'KarenQuimada@egoes.com'),
('Abalayan, Alce Mae', 'AlceMaeAbalayan@egoes.com'),
('Arreza, Anjayla', 'AnjaylaArreza@egoes.com'),
('Amacio, Angelo', 'AngeloAmacio@egoes.com'),
('Anas, Jan', 'JanAnas@egoes.com'),
('Bautista, Judy', 'JudyBautista@egoes.com'),
('Bunod, Brazell', 'BrazellBunod@egoes.com'),
('Cuadra, Cassiopia', 'CassiopiaCuadra@egoes.com'),
('Donayre, Anikka', 'AnikkaDonayre@egoes.com'),
('Flores, Princess Abegail', 'PrincessAbegailFlores@egoes.com'),
('Torres, Lux', 'LuxTorres@egoes.com'),
('Malinao, Kurt', 'KurtMalinao@egoes.com'),
('Sialonggo, Jessie', 'JessieSialonggo@egoes.com'),
('Tabasa, Ressamae', 'RessamaeTabasa@egoes.com'),
('Yecyec, Mekyla', 'MekylaYecyec@egoes.com'),
('Adona, Jolia Denise', 'JoliaDeniseAdona@egoes.com'),
('Alberca, Aireen', 'AireenAlberca@egoes.com'),
('Balboa, Edmund John', 'EdmundJohnBalboa@egoes.com'),
('Binoya, Rose', 'RoseBinoya@egoes.com'),
('Bughaw, Karen', 'KarenBughaw@egoes.com'),
('Caballero, Jenny', 'JennyCaballero@egoes.com'),
('Dag-uman, Precious', 'PreciousDaguman@egoes.com'),
('Duhilag, Novie Jean', 'NovieJeanDuhilag@egoes.com'),
('Gambong, Blessie', 'BlessieGambong@egoes.com'),
('Gapor, Lovely Kate', 'LovelyKateGapor@egoes.com'),
('Jamad, Davey', 'DaveyJamad@egoes.com'),
('Martizano, Reymark', 'ReymarkMartizano@egoes.com'),
('Matab, Louise Mikaella', 'LouiseMikaellaMatab@egoes.com'),
('Mentino, Cherie Ann', 'CherieAnnMentino@egoes.com'),
('Pacheco, Leah', 'LeahPacheco@egoes.com'),
('Robledo, Christine', 'ChristineRobledo@egoes.com'),
('Sabay, Faith', 'FaithSabay@egoes.com'),
('Sombilon, Joriz', 'JorizSombilon@egoes.com'),
('Superales, Lorive', 'LoriveSuperales@egoes.com'),
('Tawi, Khenrich', 'KhenrichTawi@egoes.com'),
('Velchez, Irish', 'IrishVelchez@egoes.com'),
('Estoconing, Jasmin Beth', 'JasminBethEstoconing@egoes.com'),
('Abapo, Zein', 'ZeinAbapo@egoes.com'),
('Alquizar, Dante', 'DanteAlquizar@egoes.com'),
('Amad, Lee Arthur', 'LeeArthurAmad@egoes.com'),
('Madrid, Joy Love', 'JoyLoveMadrid@egoes.com'),
('Serrano, Jan', 'JanSerrano@egoes.com'),
('Parantar, Deza', 'DezaParantar@egoes.com'),
('Ymas, Angelo', 'AngeloYmas@egoes.com');

-- Insert users (skip if email already exists)
INSERT INTO users (role, full_name, email, password_hash, is_active)
SELECT
  'employee',
  t.full_name,
  LOWER(TRIM(t.email)),
  @pwd,
  1
FROM tmp_name_email t
WHERE NOT EXISTS (
  SELECT 1 FROM users u
  WHERE LOWER(u.email) COLLATE utf8mb4_general_ci = LOWER(TRIM(t.email)) COLLATE utf8mb4_general_ci
);

-- Insert employees for matching users (skip if employee row exists)
INSERT INTO employees (user_id, employee_code)
SELECT
  u.id,
  CONCAT('E-', LPAD(u.id, 5, '0'))
FROM users u
JOIN tmp_name_email t
  ON LOWER(u.email) COLLATE utf8mb4_general_ci = LOWER(TRIM(t.email)) COLLATE utf8mb4_general_ci
LEFT JOIN employees e ON e.user_id = u.id
WHERE e.user_id IS NULL;
