-- OPTIONAL COMPATIBILITY TABLE
-- Create ONLY if you want to keep legacy queries that reference `project_engineers`.
-- Current app model already uses `project_assignments` with role_in_project='engineer'.

CREATE TABLE IF NOT EXISTS project_engineers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    engineer_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_project_engineer (project_id, engineer_id),
    KEY idx_project_id (project_id),
    KEY idx_engineer_id (engineer_id),
    CONSTRAINT fk_pe_project FOREIGN KEY (project_id) REFERENCES projects(project_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_pe_engineer FOREIGN KEY (engineer_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
);

-- Optional backfill from normalized assignment table:
INSERT IGNORE INTO project_engineers (project_id, engineer_id)
SELECT pa.project_id, pa.user_id
FROM project_assignments pa
WHERE pa.role_in_project = 'engineer';
