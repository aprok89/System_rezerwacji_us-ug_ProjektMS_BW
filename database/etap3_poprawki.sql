-- Poprawka tabeli employees w istniejącej bazie.
-- Kolumna employees_id jest NOT NULL bez wartości domyślnej, więc każdy
-- INSERT INTO employees (user_id, description, active) kończy się błędem.
-- Panel admina jej nie używa - można ją bezpiecznie usunąć.
USE masaz_rezerwacje;
ALTER TABLE employees DROP COLUMN employees_id;
