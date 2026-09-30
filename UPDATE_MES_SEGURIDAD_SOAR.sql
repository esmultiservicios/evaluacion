-- Evaluación Premium v9.10
-- Asigna el contenido actual a la categoría/campaña "Mes de seguridad SOAR".
-- Puedes renombrarla después desde Administración > Categorías; el sistema
-- actualizará automáticamente preguntas, juegos y empleados relacionados.
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
ON DUPLICATE KEY UPDATE
  description=VALUES(description),
  active=1,
  question_visible=1,
  game_visible=1;

UPDATE questions SET group_name='Mes de seguridad SOAR';
UPDATE games SET group_name='Mes de seguridad SOAR', category='Mes de seguridad SOAR';
UPDATE employees SET question_group='Mes de seguridad SOAR', game_group='Mes de seguridad SOAR';

COMMIT;
