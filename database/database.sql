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
CREATE TABLE reservations (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id            INT UNSIGNED NOT NULL COMMENT 'klient',
    employee_id        INT UNSIGNED NOT NULL,
    service_id         INT UNSIGNED NOT NULL,
    reservation_date   DATE NOT NULL,
    start_time         TIME NOT NULL,
    end_time           TIME NOT NULL,
    status             ENUM('oczekująca', 'potwierdzona', 'zrealizowana', 'anulowana')
                            NOT NULL DEFAULT 'oczekująca',
    comment            VARCHAR(500) NULL,
    created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_reservations_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT fk_reservations_employee
        FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT fk_reservations_service
        FOREIGN KEY (service_id) REFERENCES services(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT chk_reservations_time CHECK (end_time > start_time)
) ENGINE=InnoDB;


CREATE INDEX idx_reservations_employee_date ON reservations (employee_id, reservation_date, status);
CREATE INDEX idx_reservations_user ON reservations (user_id);
CREATE INDEX idx_reservations_status ON reservations (status);


CREATE TABLE reservation_status_history (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT UNSIGNED NOT NULL,
    old_status     ENUM('oczekująca', 'potwierdzona', 'zrealizowana', 'anulowana') NULL,
    new_status     ENUM('oczekująca', 'potwierdzona', 'zrealizowana', 'anulowana') NOT NULL,
    changed_by     INT UNSIGNED NULL COMMENT 'użytkownik, który zmienił status',
    changed_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_history_reservation
        FOREIGN KEY (reservation_id) REFERENCES reservations(id)
        ON UPDATE CASCADE ON DELETE CASCADE,

    CONSTRAINT fk_history_user
        FOREIGN KEY (changed_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_history_reservation ON reservation_status_history (reservation_id);

CREATE TABLE admin_logs (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id      INT UNSIGNED NOT NULL,
    action        VARCHAR(255) NOT NULL,
    target_table  VARCHAR(50)  NULL,
    target_id     INT UNSIGNED NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_logs_admin
        FOREIGN KEY (admin_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

DELIMITER $$

CREATE TRIGGER trg_reservations_no_overlap
BEFORE INSERT ON reservations
FOR EACH ROW
BEGIN
    DECLARE conflict_count INT;

    SELECT COUNT(*) INTO conflict_count
    FROM reservations
    WHERE employee_id = NEW.employee_id
      AND reservation_date = NEW.reservation_date
      AND status <> 'anulowana'
      AND NEW.start_time < end_time
      AND NEW.end_time > start_time;

    IF conflict_count > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Termin koliduje z inną aktywną rezerwacją tego pracownika.';
    END IF;
END$$ 

DELIMITER ;

--DANE TESTOWE
--Hasło dla kont testowych: haslo123
INSERT INTO users (name, surname, email, password, phone, role, active) VALUES
('Anna',    'Kowalska',     'admin@masaz.pl',      '$2y$10$u7x80CNaRTvi2Tkl5L5hZOL/3YKQWAF1CPEN/v65.kKpVaHwAe.5S', '500100100', 'admin',    1),
('Piotr',   'Nowak',        'piotr.nowak@masaz.pl','$2y$10$u7x80CNaRTvi2Tkl5L5hZOL/3YKQWAF1CPEN/v65.kKpVaHwAe.5S', '500200200', 'employee', 1),
('Katarzyna','Wiśniewska',  'kasia.w@masaz.pl',    '$2y$10$u7x80CNaRTvi2Tkl5L5hZOL/3YKQWAF1CPEN/v65.kKpVaHwAe.5S', '500200201', 'employee', 1),
('Marek',   'Zieliński',    'marek.z@masaz.pl',    '$2y$10$u7x80CNaRTvi2Tkl5L5hZOL/3YKQWAF1CPEN/v65.kKpVaHwAe.5S', '500200202', 'employee', 1),
('Jan',     'Kowalski',     'jan.kowalski@wp.pl',  '$2y$10$u7x80CNaRTvi2Tkl5L5hZOL/3YKQWAF1CPEN/v65.kKpVaHwAe.5S', '600300300', 'client',   1),
('Maria',   'Lewandowska',  'maria.l@wp.pl',       '$2y$10$u7x80CNaRTvi2Tkl5L5hZOL/3YKQWAF1CPEN/v65.kKpVaHwAe.5S', '600300301', 'client',   1),
('Tomasz',  'Wójcik',       'tomasz.w@wp.pl',      '$2y$10$u7x80CNaRTvi2Tkl5L5hZOL/3YKQWAF1CPEN/v65.kKpVaHwAe.5S', '600300302', 'client',   1);


INSERT INTO service_categories (name, description) VALUES
('Masaż relaksacyjny', 'Masaże wyciszające, redukujące stres i napięcie mięśniowe'),
('Masaż leczniczy',    'Masaże terapeutyczne zalecane przy dolegliwościach kręgosłupa i mięśni'),
('Masaż sportowy',     'Masaże dla osób aktywnych fizycznie, regeneracyjne i przygotowujące do wysiłku'),
('Masaże specjalistyczne', 'Masaż twarzy, gorącymi kamieniami oraz masaż dla kobiet w ciąży');


INSERT INTO services (category_id, name, description, duration, price, active) VALUES
(1, 'Masaż relaksacyjny całego ciała', 'Delikatny masaż redukujący stres, olejki zapachowe', 60, 150.00, 1),
(1, 'Masaż relaksacyjny pleców',       'Krótki masaż odprężający partię pleców i karku',      30,  80.00, 1),
(2, 'Masaż leczniczy kręgosłupa',      'Masaż terapeutyczny dla osób z bólami pleców',        45, 140.00, 1),
(2, 'Masaż tkanek głębokich',          'Intensywny masaż rozluźniający głębokie warstwy mięśni', 60, 180.00, 1),
(3, 'Masaż sportowy',                  'Masaż regeneracyjny przed/po wysiłku fizycznym',      60, 170.00, 1),
(4, 'Masaż gorącymi kamieniami',       'Masaż z wykorzystaniem rozgrzanych kamieni bazaltowych', 90, 220.00, 1),
(4, 'Masaż twarzy i głowy',            'Relaksujący masaż okolic twarzy, szyi i skóry głowy', 30,  70.00, 1),
(4, 'Masaż dla kobiet w ciąży',        'Delikatny masaż bezpieczny w okresie ciąży',          45, 130.00, 1);


INSERT INTO employees (user_id, description, active) VALUES
(2, 'Masażysta z 8-letnim doświadczeniem, specjalizacja: masaż leczniczy i sportowy', 1),
(3, 'Certyfikowana masażystka, specjalizacja: relaksacja i masaż gorącymi kamieniami', 1),
(4, 'Fizjoterapeuta i masażysta, specjalizacja: tkanki głębokie i rehabilitacja', 1);


INSERT INTO employee_services (employee_id, service_id) VALUES
(1, 3), (1, 4), (1, 5),
(2, 1), (2, 2), (2, 6), (2, 7),
(3, 3), (3, 4), (3, 8);  


INSERT INTO employee_availability (employee_id, day_of_week, start_time, end_time) VALUES
(1, 1, '08:00:00', '16:00:00'),
(1, 2, '08:00:00', '16:00:00'),
(1, 3, '08:00:00', '16:00:00'),
(1, 4, '08:00:00', '16:00:00'),
(1, 5, '08:00:00', '14:00:00'),
(2, 2, '10:00:00', '18:00:00'),
(2, 3, '10:00:00', '18:00:00'),
(2, 4, '10:00:00', '18:00:00'),
(2, 5, '10:00:00', '18:00:00'),
(2, 6, '09:00:00', '13:00:00'),
(3, 1, '09:00:00', '17:00:00'),
(3, 3, '09:00:00', '17:00:00'),
(3, 5, '09:00:00', '17:00:00'),
(3, 6, '09:00:00', '13:00:00');


INSERT INTO employee_absences (employee_id, date_from, date_to, reason) VALUES
(2, '2026-12-22', '2026-12-31', 'Urlop wypoczynkowy');
--Rezerwacje testowe
INSERT INTO reservations (user_id, employee_id, service_id, reservation_date, start_time, end_time, status, comment) VALUES
(5, 1, 5, '2026-12-01', '10:00:00', '11:00:00', 'potwierdzona', 'Pierwsza wizyta'),
(6, 2, 1, '2026-12-02', '11:00:00', '12:00:00', 'oczekująca', NULL),
(7, 3, 3, '2026-12-03', '09:00:00', '09:45:00', 'zrealizowana', 'Zalecenie kontynuacji za 2 tygodnie'),
(5, 2, 6, '2026-12-05', '10:30:00', '12:00:00', 'anulowana', 'Klient odwołał wizytę'),
(6, 1, 4, '2026-12-06', '13:00:00', '14:00:00', 'potwierdzona', NULL);

INSERT INTO reservation_status_history (reservation_id, old_status, new_status, changed_by) VALUES
(4, 'oczekująca', 'anulowana', 5);
--Przykładowy wpis w logu administratora
INSERT INTO admin_logs (admin_id, action, target_table, target_id) VALUES
(1, 'Dodano nową usługę: Masaż dla kobiet w ciąży', 'services', 8);
