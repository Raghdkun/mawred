-- Extensions required by ADR-0005 (ltree), ADR-0013 (pg_trgm, unaccent) on both databases.
CREATE DATABASE mawred_testing OWNER mawred;
CREATE EXTENSION IF NOT EXISTS ltree;
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE EXTENSION IF NOT EXISTS unaccent;
\connect mawred_testing
CREATE EXTENSION IF NOT EXISTS ltree;
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE EXTENSION IF NOT EXISTS unaccent;
