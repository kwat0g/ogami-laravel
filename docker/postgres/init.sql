-- PostgreSQL initialization — runs once when the container is first created.
-- Creates the application database and the test database.
-- The main database is already created by POSTGRES_DB env var;
-- we only need to ensure the test database exists.

SELECT 'CREATE DATABASE ogami_erp_test'
WHERE NOT EXISTS (
    SELECT FROM pg_database WHERE datname = 'ogami_erp_test'
)\gexec

-- Enable pgcrypto for encrypted sensitive columns (salary, govt IDs)
\c ogami_erp
CREATE EXTENSION IF NOT EXISTS pgcrypto;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS btree_gist;  -- Needed for GIST exclusion constraints (period overlaps)

\c ogami_erp_test
CREATE EXTENSION IF NOT EXISTS pgcrypto;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS btree_gist;
