-- CIBERSEGURIDAD 2026
-- Crea la categoría, agrupa TODO el contenido actual y la asigna a TODOS los empleados.
-- También reinicia las participaciones actuales para que la nueva asignación quede limpia.
SET NAMES utf8mb4;
START TRANSACTION;

CREATE TABLE IF NOT EXISTS content_groups (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  description VARCHAR(500) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  question_visible TINYINT(1) NOT NULL DEFAULT 1,
  game_visible TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX(active,name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE questions ADD COLUMN IF NOT EXISTS group_name VARCHAR(120) NULL AFTER time_limit_seconds;
ALTER TABLE games ADD COLUMN IF NOT EXISTS group_name VARCHAR(120) NULL AFTER category;
ALTER TABLE employees ADD COLUMN IF NOT EXISTS question_group VARCHAR(120) NULL AFTER department;
ALTER TABLE employees ADD COLUMN IF NOT EXISTS game_group VARCHAR(120) NULL AFTER question_group;
ALTER TABLE content_groups ADD COLUMN IF NOT EXISTS question_visible TINYINT(1) NOT NULL DEFAULT 1 AFTER active;
ALTER TABLE content_groups ADD COLUMN IF NOT EXISTS game_visible TINYINT(1) NOT NULL DEFAULT 1 AFTER question_visible;

INSERT INTO content_groups(name,description,active,question_visible,game_visible)
VALUES ('Mes de seguridad SOAR','Campaña de concientización y evaluación del Mes de seguridad SOAR.',1,1,1)
ON DUPLICATE KEY UPDATE description=VALUES(description),active=1,question_visible=1,game_visible=1;

UPDATE questions SET group_name='Mes de seguridad SOAR';
UPDATE games SET group_name='Mes de seguridad SOAR';
UPDATE employees SET question_group='Mes de seguridad SOAR', game_group='Mes de seguridad SOAR';

DELETE FROM evaluations;
DELETE FROM game_attempts;
DELETE FROM game_assignments;

COMMIT;
