delete
from memos
where id not in (SELECT min(id)
                 FROM memos
                 WHERE deleted IS NULL
                 GROUP BY type, aktiv, name, kat, keywords, title, linkname, content, contenttyp, status, deleted, changed, lang, created)
