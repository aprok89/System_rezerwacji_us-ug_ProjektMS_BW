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
