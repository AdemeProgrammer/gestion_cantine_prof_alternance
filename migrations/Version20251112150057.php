<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251112150057 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE calendrier (id INT AUTO_INCREMENT NOT NULL, ref_promo_id INT DEFAULT NULL, date DATE NOT NULL, type_jour VARCHAR(255) NOT NULL, INDEX IDX_B2753CB956D7BC78 (ref_promo_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE description (id INT AUTO_INCREMENT NOT NULL, ref_professeur_id INT NOT NULL, ref_promo_id INT DEFAULT NULL, lundi TINYINT(1) NOT NULL, mardi TINYINT(1) NOT NULL, mercredi TINYINT(1) NOT NULL, jeudi TINYINT(1) NOT NULL, vendredi TINYINT(1) NOT NULL, report NUMERIC(10, 2) NOT NULL, prix_u NUMERIC(10, 2) NOT NULL, INDEX IDX_6DE440269EE27989 (ref_professeur_id), INDEX IDX_6DE4402656D7BC78 (ref_promo_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE facturation (id INT AUTO_INCREMENT NOT NULL, ref_professeur_id INT NOT NULL, mois VARCHAR(50) NOT NULL, montant_total NUMERIC(10, 2) NOT NULL, montant_regle NUMERIC(10, 2) NOT NULL, montant_restant NUMERIC(10, 2) NOT NULL, statut VARCHAR(255) NOT NULL, nb_repas INT NOT NULL, report_m_1 NUMERIC(10, 2) NOT NULL, INDEX IDX_17EB513A9EE27989 (ref_professeur_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE paiement (id INT AUTO_INCREMENT NOT NULL, ref_description_id_id INT NOT NULL, date_paiement DATETIME NOT NULL, montant NUMERIC(10, 2) NOT NULL, moyen_paiement VARCHAR(255) NOT NULL, INDEX IDX_B1DC7A1EB1053406 (ref_description_id_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE professeur (id INT AUTO_INCREMENT NOT NULL, prenom VARCHAR(255) NOT NULL, nom VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, code_compta INT DEFAULT NULL, est_actif TINYINT(1) NOT NULL, UNIQUE INDEX UNIQ_17A55299E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE promo (id INT AUTO_INCREMENT NOT NULL, annee_debut INT NOT NULL, annee_fin INT NOT NULL, UNIQUE INDEX UNIQ_B0139AFB3336103A (annee_debut), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE repas (id INT AUTO_INCREMENT NOT NULL, ref_calendrier_id INT NOT NULL, professeur_id INT NOT NULL, est_consomme TINYINT(1) NOT NULL, INDEX IDX_A8D351B3DB02AB31 (ref_calendrier_id), INDEX IDX_A8D351B3BAB22EE9 (professeur_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE reset_password_request (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, selector VARCHAR(20) NOT NULL, hashed_token VARCHAR(100) NOT NULL, requested_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_7CE748AA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, nom VARCHAR(255) NOT NULL, prenom VARCHAR(255) NOT NULL, reset_token VARCHAR(64) DEFAULT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE calendrier ADD CONSTRAINT FK_B2753CB956D7BC78 FOREIGN KEY (ref_promo_id) REFERENCES promo (id)');
        $this->addSql('ALTER TABLE description ADD CONSTRAINT FK_6DE440269EE27989 FOREIGN KEY (ref_professeur_id) REFERENCES professeur (id)');
        $this->addSql('ALTER TABLE description ADD CONSTRAINT FK_6DE4402656D7BC78 FOREIGN KEY (ref_promo_id) REFERENCES promo (id)');
        $this->addSql('ALTER TABLE facturation ADD CONSTRAINT FK_17EB513A9EE27989 FOREIGN KEY (ref_professeur_id) REFERENCES professeur (id)');
        $this->addSql('ALTER TABLE paiement ADD CONSTRAINT FK_B1DC7A1EB1053406 FOREIGN KEY (ref_description_id_id) REFERENCES description (id)');
        $this->addSql('ALTER TABLE repas ADD CONSTRAINT FK_A8D351B3DB02AB31 FOREIGN KEY (ref_calendrier_id) REFERENCES calendrier (id)');
        $this->addSql('ALTER TABLE repas ADD CONSTRAINT FK_A8D351B3BAB22EE9 FOREIGN KEY (professeur_id) REFERENCES professeur (id)');
        $this->addSql('ALTER TABLE reset_password_request ADD CONSTRAINT FK_7CE748AA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE calendrier DROP FOREIGN KEY FK_B2753CB956D7BC78');
        $this->addSql('ALTER TABLE description DROP FOREIGN KEY FK_6DE440269EE27989');
        $this->addSql('ALTER TABLE description DROP FOREIGN KEY FK_6DE4402656D7BC78');
        $this->addSql('ALTER TABLE facturation DROP FOREIGN KEY FK_17EB513A9EE27989');
        $this->addSql('ALTER TABLE paiement DROP FOREIGN KEY FK_B1DC7A1EB1053406');
        $this->addSql('ALTER TABLE repas DROP FOREIGN KEY FK_A8D351B3DB02AB31');
        $this->addSql('ALTER TABLE repas DROP FOREIGN KEY FK_A8D351B3BAB22EE9');
        $this->addSql('ALTER TABLE reset_password_request DROP FOREIGN KEY FK_7CE748AA76ED395');
        $this->addSql('DROP TABLE calendrier');
        $this->addSql('DROP TABLE description');
        $this->addSql('DROP TABLE facturation');
        $this->addSql('DROP TABLE paiement');
        $this->addSql('DROP TABLE professeur');
        $this->addSql('DROP TABLE promo');
        $this->addSql('DROP TABLE repas');
        $this->addSql('DROP TABLE reset_password_request');
        $this->addSql('DROP TABLE `user`');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
