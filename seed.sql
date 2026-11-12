-- Jeu de données de test pour la base `app`
-- Mot de passe de tous les utilisateurs : password
SET NAMES utf8mb4;
-- Vidage des tables (des enfants vers les parents pour respecter les clés étrangères)
DELETE FROM `message`;
DELETE FROM `exchange`;
DELETE FROM `wishlist`;
DELETE FROM `comment`;
DELETE FROM `book_page`;
DELETE FROM `book`;
DELETE FROM `announcement`;
DELETE FROM `user`;

ALTER TABLE `message`      AUTO_INCREMENT = 1;
ALTER TABLE `exchange`     AUTO_INCREMENT = 1;
ALTER TABLE `wishlist`     AUTO_INCREMENT = 1;
ALTER TABLE `comment`      AUTO_INCREMENT = 1;
ALTER TABLE `book_page`    AUTO_INCREMENT = 1;
ALTER TABLE `book`         AUTO_INCREMENT = 1;
ALTER TABLE `announcement` AUTO_INCREMENT = 1;
ALTER TABLE `user`         AUTO_INCREMENT = 1;

START TRANSACTION;

-- --------------------------------------------------------
-- user
-- --------------------------------------------------------
INSERT INTO `user` (`id`, `email`, `roles`, `password`, `username`, `avatar_url`, `created_at`, `cgu_validated_at`, `is_verified`, `inactivated_at`, `deleted_at`) VALUES
(1, 'admin@bookswap.test',  '["ROLE_ADMIN"]', '$2y$10$iGRChCDrkZqRNnwLmh4RX.QU1sLrWbeqhxYd0hi3rLRHRTa8H0Mvu', 'admin',  'https://i.pravatar.cc/150?img=1', '2026-09-01 10:00:00', '2026-09-01 10:00:00', 1, NULL, NULL),
(2, 'alice@bookswap.test',  '[]',             '$2y$10$iGRChCDrkZqRNnwLmh4RX.QU1sLrWbeqhxYd0hi3rLRHRTa8H0Mvu', 'alice',  'https://i.pravatar.cc/150?img=5', '2026-09-10 14:20:00', '2026-09-10 14:20:00', 1, NULL, NULL),
(3, 'bob@bookswap.test',    '[]',             '$2y$10$iGRChCDrkZqRNnwLmh4RX.QU1sLrWbeqhxYd0hi3rLRHRTa8H0Mvu', 'bob',    'https://i.pravatar.cc/150?img=12','2026-09-15 09:05:00', '2026-09-15 09:05:00', 1, NULL, NULL),
(4, 'chloe@bookswap.test',  '[]',             '$2y$10$iGRChCDrkZqRNnwLmh4RX.QU1sLrWbeqhxYd0hi3rLRHRTa8H0Mvu', 'chloe',  NULL,                              '2026-10-02 18:45:00', '2026-10-02 18:45:00', 1, NULL, NULL),
(5, 'david@bookswap.test',  '[]',             '$2y$10$iGRChCDrkZqRNnwLmh4RX.QU1sLrWbeqhxYd0hi3rLRHRTa8H0Mvu', 'david',  NULL,                              '2026-10-20 11:30:00', NULL,                  0, NULL, NULL),
(6, 'emma@bookswap.test',   '[]',             '$2y$10$iGRChCDrkZqRNnwLmh4RX.QU1sLrWbeqhxYd0hi3rLRHRTa8H0Mvu', 'emma',   NULL,                              '2026-08-12 08:00:00', '2026-08-12 08:00:00', 1, '2026-11-01 12:00:00', NULL);

-- --------------------------------------------------------
-- announcement  (type: donation|sale ; status: available|reserved|completed|cancelled)
-- --------------------------------------------------------
INSERT INTO `announcement` (`id`, `title`, `description`, `type`, `price`, `status`, `inactivated_at`, `created_at`, `user_id`) VALUES
(1, 'Pack Harry Potter tomes 1 à 3', 'Les trois premiers tomes en bon état, idéal pour commencer la saga.', 'sale',     15.00, 'available', NULL, '2026-10-05 10:00:00', 2),
(2, 'Romans classiques à donner',    'Je fais du tri, quelques classiques de la littérature française.',     'donation', NULL,  'available', NULL, '2026-10-08 16:30:00', 3),
(3, 'Manga One Piece vol. 1',        'Premier volume, quasiment neuf.',                                     'sale',     4.50,  'reserved',  NULL, '2026-10-12 09:15:00', 4),
(4, 'Livres de cours MMI',           'Livres de dev web et design utilisés en BUT MMI.',                    'sale',     25.00, 'completed', NULL, '2026-09-20 13:00:00', 2),
(5, 'Science-fiction en vrac',       'Asimov, Herbert, Dick... à récupérer sur place.',                     'donation', NULL,  'available', NULL, '2026-11-02 19:00:00', 3),
(6, 'Annonce annulée',               'Plus disponible.',                                                    'sale',     8.00,  'cancelled', '2026-11-05 10:00:00', '2026-10-25 10:00:00', 4);

-- --------------------------------------------------------
-- book  (book_condition: new|very_good|good|acceptable|poor)
-- --------------------------------------------------------
INSERT INTO `book` (`id`, `title`, `author`, `isbn`, `book_condition`, `genre`, `inactivated_at`, `announcement_id`) VALUES
(1,  'Harry Potter à l''école des sorciers', 'J.K. Rowling',          '9782070584628', 'good',       'Fantasy',          NULL, 1),
(2,  'Harry Potter et la Chambre des secrets','J.K. Rowling',         '9782070584642', 'good',       'Fantasy',          NULL, 1),
(3,  'Harry Potter et le Prisonnier d''Azkaban','J.K. Rowling',       '9782070584925', 'very_good',  'Fantasy',          NULL, 1),
(4,  'Les Misérables',                        'Victor Hugo',          '9782253096337', 'acceptable', 'Classique',        NULL, 2),
(5,  'Madame Bovary',                         'Gustave Flaubert',     '9782253004868', 'good',       'Classique',        NULL, 2),
(6,  'L''Étranger',                           'Albert Camus',         '9782070360024', 'poor',       'Classique',        NULL, 2),
(7,  'One Piece - Tome 1',                    'Eiichiro Oda',         '9782723434621', 'new',        'Manga',            NULL, 3),
(8,  'CSS Maintenables',                      'Kaelig Deloumeau',     '9782212145427', 'very_good',  'Informatique',     NULL, 4),
(9,  'Design Emotionnel',                     'Aarron Walter',        '9782212138771', 'good',       'Design',           NULL, 4),
(10, 'Fondation',                             'Isaac Asimov',         '9782070360536', 'good',       'Science-fiction',  NULL, 5),
(11, 'Dune',                                  'Frank Herbert',        '9782266320481', 'acceptable', 'Science-fiction',  NULL, 5),
(12, 'Ubik',                                  'Philip K. Dick',       '9782264034922', 'very_good',  'Science-fiction',  NULL, 5),
(13, 'Le Petit Prince',                       'Antoine de Saint-Exupéry', '9782070612758', 'good',   'Jeunesse',         '2026-11-05 10:00:00', 6);

-- --------------------------------------------------------
-- book_page
-- --------------------------------------------------------
INSERT INTO `book_page` (`id`, `img_url`, `page_order`, `uploaded_at`, `inactivated_at`, `book_id`) VALUES
(1,  'https://picsum.photos/seed/hp1-cover/400/600', 1, '2026-10-05 10:01:00', NULL, 1),
(2,  'https://picsum.photos/seed/hp1-back/400/600',  2, '2026-10-05 10:01:30', NULL, 1),
(3,  'https://picsum.photos/seed/hp2-cover/400/600', 1, '2026-10-05 10:02:00', NULL, 2),
(4,  'https://picsum.photos/seed/hp3-cover/400/600', 1, '2026-10-05 10:03:00', NULL, 3),
(5,  'https://picsum.photos/seed/miserables/400/600',1, '2026-10-08 16:31:00', NULL, 4),
(6,  'https://picsum.photos/seed/bovary/400/600',    1, '2026-10-08 16:32:00', NULL, 5),
(7,  'https://picsum.photos/seed/etranger/400/600',  1, '2026-10-08 16:33:00', NULL, 6),
(8,  'https://picsum.photos/seed/onepiece/400/600',  1, '2026-10-12 09:16:00', NULL, 7),
(9,  'https://picsum.photos/seed/css/400/600',       1, '2026-09-20 13:01:00', NULL, 8),
(10, 'https://picsum.photos/seed/design/400/600',    1, '2026-09-20 13:02:00', NULL, 9),
(11, 'https://picsum.photos/seed/fondation/400/600', 1, '2026-11-02 19:01:00', NULL, 10),
(12, 'https://picsum.photos/seed/dune/400/600',      1, '2026-11-02 19:02:00', NULL, 11),
(13, 'https://picsum.photos/seed/ubik/400/600',      1, '2026-11-02 19:03:00', NULL, 12),
(14, 'https://picsum.photos/seed/petitprince/400/600',1,'2026-10-25 10:01:00', '2026-11-05 10:00:00', 13);

-- --------------------------------------------------------
-- comment
-- --------------------------------------------------------
INSERT INTO `comment` (`id`, `content`, `created_at`, `inactivated_at`, `announcement_id`, `user_id`) VALUES
(1, 'Est-ce que le prix est négociable ?',            '2026-10-06 12:00:00', NULL, 1, 3),
(2, 'Oui, je peux descendre à 12 €.',                 '2026-10-06 14:10:00', NULL, 1, 2),
(3, 'Super initiative, merci pour le don !',          '2026-10-09 08:45:00', NULL, 2, 4),
(4, 'Le tome est-il dédicacé ?',                      '2026-10-13 17:20:00', NULL, 3, 2),
(5, 'Commentaire supprimé par la modération.',        '2026-11-03 20:00:00', '2026-11-04 09:00:00', 5, 5),
(6, 'Je suis intéressée par Dune !',                  '2026-11-03 21:15:00', NULL, 5, 4);

-- --------------------------------------------------------
-- exchange  (status: pending|accepted|refused|completed|cancelled)
-- --------------------------------------------------------
INSERT INTO `exchange` (`id`, `status`, `initiated_at`, `completed_at`, `announcement_id`, `requester_id`) VALUES
(1, 'pending',   '2026-10-07 09:00:00', NULL,                  1, 3),
(2, 'accepted',  '2026-10-14 10:30:00', NULL,                  3, 2),
(3, 'completed', '2026-09-22 15:00:00', '2026-09-25 18:00:00', 4, 4),
(4, 'refused',   '2026-10-10 11:00:00', NULL,                  2, 5),
(5, 'pending',   '2026-11-04 08:00:00', NULL,                  5, 4);

-- --------------------------------------------------------
-- message
-- --------------------------------------------------------
INSERT INTO `message` (`id`, `content`, `sent_at`, `inactivated_at`, `exchange_id`, `sender_id`) VALUES
(1,  'Bonjour, je suis intéressé par le pack Harry Potter.',  '2026-10-07 09:00:00', NULL, 1, 3),
(2,  'Bonjour Bob, il est toujours disponible !',              '2026-10-07 11:20:00', NULL, 1, 2),
(3,  'Je peux venir le chercher samedi ?',                     '2026-10-07 11:45:00', NULL, 1, 3),
(4,  'Salut, je prends le One Piece.',                         '2026-10-14 10:30:00', NULL, 2, 2),
(5,  'Parfait, je te le réserve.',                             '2026-10-14 12:00:00', NULL, 2, 4),
(6,  'Je voudrais les livres de cours MMI.',                   '2026-09-22 15:00:00', NULL, 3, 4),
(7,  'Ok, rendez-vous à l''IUT jeudi.',                        '2026-09-22 16:30:00', NULL, 3, 2),
(8,  'Merci, échange effectué !',                              '2026-09-25 18:00:00', NULL, 3, 4),
(9,  'Je peux avoir les classiques ?',                         '2026-10-10 11:00:00', NULL, 4, 5),
(10, 'Désolé, ils sont déjà promis.',                          '2026-10-10 13:00:00', NULL, 4, 3),
(11, 'Bonjour, Dune est encore dispo ?',                       '2026-11-04 08:00:00', NULL, 5, 4);

-- --------------------------------------------------------
-- wishlist
-- --------------------------------------------------------
INSERT INTO `wishlist` (`id`, `added_at`, `inactivated_at`, `user_id`, `book_id`) VALUES
(1, '2026-10-06 12:05:00', NULL, 3, 1),
(2, '2026-10-06 12:06:00', NULL, 3, 3),
(3, '2026-10-09 09:00:00', NULL, 4, 5),
(4, '2026-11-03 21:20:00', NULL, 4, 11),
(5, '2026-10-13 17:25:00', NULL, 2, 7),
(6, '2026-11-02 20:00:00', '2026-11-06 10:00:00', 2, 12);

COMMIT;
