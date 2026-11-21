-- ========================================================================
-- ========================================================================

create table llx_kontava
(
  rowid                     integer AUTO_INCREMENT PRIMARY KEY,
  nom                       varchar(128),                               -- company reference name (should be same length than adherent.societe)
  forfait                   integer,                                    -- 1 = Jeune pousse, 2 = arbuste, 3 = Kombava
  stockage_quota            double(24,8)   DEFAULT NULL,                -- Quota en Go d'espace de stockage
  stockage_reel             double(24,8)   DEFAULT NULL,                -- Espace disque utilisé
  users_quota               integer        DEFAULT 1,                   -- Nombre d'utilisateurs max dans l'abonnement
  last_module_version       varchar(32)    DEFAULT '1.0.0',             -- Version du dernier module en tinytext
  prochain_paiement_montant double(24,8)   DEFAULT NULL,                -- Montant du prochain paiement (€)
  prochain_paiement_date    date           DEFAULT NULL,                -- date du prochain paiement (aaaa-mm-jj)
  lien_paiement             varchar(255)   DEFAULT NULL,                -- Lien vers le paiement lorsque pertinent
  compte_gratuit            boolean        DEFAULT FALSE                -- FALSE = compte payant, TRUE = compte gratuit
)ENGINE=innodb;
