-- Local development only. Creates the separate test database the PHPUnit suite
-- uses. Tests run against PostgreSQL, never SQLite: the schema depends on
-- partial unique indexes and CHECK constraints SQLite does not share, so an
-- SQLite run would silently skip the guarantees that matter most (ADR-003).
CREATE DATABASE portal_karir_test OWNER portal_karir;
