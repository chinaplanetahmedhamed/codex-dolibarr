-- Migration des données de llx_kontava vers llx_konst
-- On lit la ligne unique rowid = 1

INSERT IGNORE INTO llx_konst (nom, valeur, type, note, tms)
SELECT 'KONTAVA_PLAN', forfait, 'bool', '1 = Jeune pousse, 2 = arbuste, 3 = Kombava', NOW()
FROM llx_kontava WHERE rowid = 1;

INSERT IGNORE INTO llx_konst (nom, valeur, type, note, tms)
SELECT 'STOCKAGE_QUOTA', stockage_quota, 'double', 'Quota en Go d''espace de stockage', NOW()
FROM llx_kontava WHERE rowid = 1;

INSERT IGNORE INTO llx_konst (nom, valeur, type, note, tms)
SELECT 'STOCKAGE_REEL', stockage_reel, 'double', 'Espace disque utilisé, rempli par cmde shell linux', NOW()
FROM llx_kontava WHERE rowid = 1;

INSERT IGNORE INTO llx_konst (nom, valeur, type, note, tms)
SELECT 'USERS_QUOTA', users_quota, 'integer', 'Nombre d''utilisateurs max dans l''abonnement', NOW()
FROM llx_kontava WHERE rowid = 1;

INSERT IGNORE INTO llx_konst (nom, valeur, type, note, tms)
SELECT 'PROCHAIN_PAIEMENT_MONTANT', prochain_paiement_montant, 'double', 'Montant du prochain paiement (€)', NOW()
FROM llx_kontava WHERE rowid = 1;

INSERT IGNORE INTO llx_konst (nom, valeur, type, note, tms)
SELECT 'PROCHAIN_PAIEMENT_DATE', prochain_paiement_date, 'date', 'Date du prochain paiement (aaaa-mm-jj)', NOW()
FROM llx_kontava WHERE rowid = 1;

INSERT IGNORE INTO llx_konst (nom, valeur, type, note, tms)
SELECT 'LIEN_PAIEMENT', lien_paiement, 'chaine', 'Lien vers le paiement lorsque pertinent', NOW()
FROM llx_kontava WHERE rowid = 1;

INSERT IGNORE INTO llx_konst (nom, valeur, type, note, tms)
SELECT 'COMPTE_GRATUIT', compte_gratuit, 'bool', 'FALSE = compte payant, TRUE = compte gratuit', NOW()
FROM llx_kontava WHERE rowid = 1;
