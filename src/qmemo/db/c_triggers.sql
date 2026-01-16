DROP TRIGGER IF EXISTS set_created_at_memos;
--END;

DROP TRIGGER IF EXISTS set_updated_at_memos;
--END;

CREATE TRIGGER IF NOT EXISTS set_updated_at_memos
    AFTER UPDATE
    ON memos
    FOR EACH ROW
    WHEN (SELECT trigger_update
          FROM trigger_control) = 1
BEGIN
    UPDATE trigger_control SET trigger_update = 0;
    UPDATE memos
    SET changed = CURRENT_TIMESTAMP
    WHERE id = NEW.id;
    UPDATE trigger_control SET trigger_update = 1;
END;
--END;

DROP TRIGGER IF EXISTS before_update_memos;
--END;

CREATE TRIGGER IF NOT EXISTS before_update_memos
    BEFORE UPDATE
    ON memos
    FOR EACH ROW
    WHEN (SELECT trigger_versions
          FROM trigger_control) = 1
BEGIN
    INSERT INTO memos_versions (memos_id, type, aktiv, name, kat, keywords, title, linkname, content, contenttyp, status, deleted, changed, lang, created)
    VALUES (OLD.id, OLD.type, OLD.aktiv, OLD.name, OLD.kat, OLD.keywords, OLD.title, OLD.linkname, OLD.content, OLD.contenttyp, OLD.status, OLD.deleted, OLD.changed, OLD.lang, OLD.created);
END;

