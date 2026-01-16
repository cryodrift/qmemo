CREATE TABLE IF NOT EXISTS memos
(
    id         INTEGER PRIMARY KEY,
    type       TEXT,
    aktiv      TEXT,
    name       TEXT,
    kat        TEXT,
    keywords   TEXT,
    title      TEXT,
    linkname   TEXT,
    content    TEXT,
    contenttyp TEXT,
    status     TEXT,
    deleted    TEXT,
    changed    NUMERIC,
    lang       TEXT,
    created    NUMERIC DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS memos_versions
(
    id         INTEGER PRIMARY KEY,
    memos_id   INTEGER,
    type       TEXT,
    aktiv      TEXT,
    name       TEXT,
    kat        TEXT,
    keywords   TEXT,
    title      TEXT,
    linkname   TEXT,
    content    TEXT,
    contenttyp TEXT,
    status     TEXT,
    deleted    TEXT,
    changed    NUMERIC,
    lang       TEXT,
    created    NUMERIC
);

CREATE TABLE IF NOT EXISTS memos_files
(
    id       INTEGER PRIMARY KEY,
    memo_id INTEGER,
    file_id INTEGER
);

CREATE TABLE IF NOT EXISTS memos_memos
(
    id        INTEGER PRIMARY KEY,
    memo1_id INTEGER,
    memo2_id INTEGER
);

CREATE TABLE IF NOT EXISTS trigger_control
(
    trigger_update INTEGER unique,
    trigger_versions INTEGER unique
);

INSERT or replace INTO trigger_control (trigger_update,trigger_versions) values (1,1);


