-- Nokime Jobs accounts, phase 1: MySQL 8.4 (the hosting's included database), imported once in phpMyAdmin.
-- The rig turns this file into SQLite with sqlite-from-mysql.py, so it keeps to the subset both engines read
-- (as Copius's api/_schema.sql). Times are Unix seconds set by PHP, so nothing depends on the SQL server's clock.
-- Hashes and rate keys are hex, so no binary string crosses the connection's character set.
-- Addresses are ASCII with a binary collation: under MySQL's default collations an address with an accent
-- would match the same address without it.

CREATE TABLE accounts (
  id                 INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email              VARCHAR(254) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
  kind               VARCHAR(13) CHARACTER SET ascii NOT NULL,
  lang               CHAR(2) CHARACTER SET ascii NOT NULL DEFAULT 'fr',
  password_hash      VARCHAR(255) CHARACTER SET ascii NOT NULL,
  email_verified_at  INT NULL DEFAULT NULL,
  created_at         INT NOT NULL,
  last_seen_at       INT NOT NULL,
  inactive_warned_at INT NULL DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE sessions (
  id_hash      CHAR(64) CHARACTER SET ascii NOT NULL PRIMARY KEY,
  account_id   INT NOT NULL,
  created_at   INT NOT NULL,
  last_seen_at INT NOT NULL,
  expires_at   INT NOT NULL,
  KEY (account_id)
) ENGINE=InnoDB;

CREATE TABLE tokens (
  hash       CHAR(64) CHARACTER SET ascii NOT NULL PRIMARY KEY,
  account_id INT NOT NULL,
  purpose    VARCHAR(12) CHARACTER SET ascii NOT NULL,
  new_email  VARCHAR(254) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  created_at INT NOT NULL,
  expires_at INT NOT NULL,
  used_at    INT NULL DEFAULT NULL,
  KEY (account_id)
) ENGINE=InnoDB;

CREATE TABLE rate_events (
  k  CHAR(32) CHARACTER SET ascii NOT NULL,
  at INT NOT NULL,
  KEY (k, at)
) ENGINE=InnoDB;

CREATE TABLE login_failures (
  account_id   INT NOT NULL PRIMARY KEY,
  count        INT NOT NULL,
  first_at     INT NOT NULL,
  last_at      INT NOT NULL,
  locked_until INT NULL DEFAULT NULL
) ENGINE=InnoDB;

-- An erasure request by hand: the account's id first, then everything it owns, in this order.
--   SELECT id FROM accounts WHERE email = 'someone@example.com';
--   DELETE FROM sessions WHERE account_id = <id>;
--   DELETE FROM tokens WHERE account_id = <id>;
--   DELETE FROM login_failures WHERE account_id = <id>;
--   DELETE FROM accounts WHERE id = <id>;
-- Rate-limit fingerprints hold no address and go by themselves within 24 hours.
