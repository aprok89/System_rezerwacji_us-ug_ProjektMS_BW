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
)  ENGINE=InnoDB;

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

CREATE TABLE employees (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employees id  INT UNSIGNED NOT NULL,
    user_id       INT UNSIGEND NOT NULL,
    description   VARCHAR(100) NOT NULL,
    active        TINYINT(1)   NOT NULL DEFAULT 1,
    CONSTRAINT fk_employees_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE CASCADE,

    CONSTRAINT uq_employees_user UNIQUE (user_id)
) ENGINE=InnoDB;
CREATE TABLE employee_services (
    employee_id   INT UNSIGNED NOT NULL,
    service_id    INT UNSIGNED NOT NULL,

    PRIMARY KEY (employee_id, service_id),

    CONSTRAINT fk_es_employee
        FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON UPDATE CASCADE ON DELETE CASCADE,

    CONSTRAINT fk_es_service
        FOREIGN KEY (service_id) REFERENCES services(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE employee_availability (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id   INT UNSIGNED NOT NULL,
    day_of_week   TINYINT UNSIGNED NOT NULL COMMENT '1=Pn ... 7=Nd',
    start_time    TIME NOT NULL,
    end_time      TIME NOT NULL,

    CONSTRAINT fk_availability_employee
        FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON UPDATE CASCADE ON DELETE CASCADE,

    CONSTRAINT chk_availability_day CHECK (day_of_week BETWEEN 1 AND 7),
    CONSTRAINT chk_availability_time CHECK (end_time > start_time)
) ENGINE=InnoDB;

CREATE INDEX idx_availability_employee ON employee_availability (employee_id, day_of_week);


CREATE TABLE employee_absences (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id   INT UNSIGNED NOT NULL,
    date_from     DATE NOT NULL,
    date_to       DATE NOT NULL,
    reason        VARCHAR(255) NULL,

    CONSTRAINT fk_absences_employee
        FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON UPDATE CASCADE ON DELETE CASCADE,

    CONSTRAINT chk_absences_dates CHECK (date_to >= date_from)
) ENGINE=InnoDB;

CREATE INDEX idx_absences_employee ON employee_absences (employee_id, date_from, date_to);
