SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP DATABASE IF EXISTS masaz_rezerwacje;
CREATE DATABASE masaz_rezerwacje CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
USE masaz_rezerwacje;

CREATE TABLE users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(50)  NOT NULL,
    surname       VARCHAR(50)  NOT NULL,
    email         VARCHAR(100) NOT NULL,
    password      VARCHAR(255) NOT NULL COMMENT 'hash bcrypt - password_hash()',
    phone         VARCHAR(20)  NULL,
    role          ENUM('client', 'employee', 'admin') NOT NULL DEFAULT 'client',
    active        TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT uq_users_email UNIQUE (email)
)
 ENGINE=InnoDB;

CREATE INDEX idx_users_role ON users (role);

CREATE TABLE service_categories (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    description   TEXT NULL,

    CONSTRAINT uq_category_name UNIQUE (name)
) ENGINE=InnoDB;


CREATE TABLE services (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id   INT UNSIGNED NOT NULL,
    name          VARCHAR(100) NOT NULL,
    description   TEXT NULL,
    duration      SMALLINT UNSIGNED NOT NULL COMMENT 'czas trwania w minutach',
    price         DECIMAL(8,2) NOT NULL,
    active        TINYINT(1)   NOT NULL DEFAULT 1,

    CONSTRAINT fk_services_category
        FOREIGN KEY (category_id) REFERENCES service_categories(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT chk_services_duration CHECK (duration > 0),
    CONSTRAINT chk_services_price CHECK (price >= 0)
) ENGINE=InnoDB;

CREATE INDEX idx_services_category ON services (category_id);
CREATE INDEX idx_services_active ON services (active);
