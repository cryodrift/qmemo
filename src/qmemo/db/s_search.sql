WITH fts_results AS (SELECT id
                     FROM fts.memos_fts
                     WHERE memos_fts MATCH :query)
SELECT m.id,
       m.type,
       m.aktiv,
       m.kat,
       m.name,
       m.title,
       m.status,
       m.keywords,
       m.created,
       m.changed,
       m.deleted,
       m.contenttyp,
       createShortContent(m.content) as shortcontent
FROM main.memos m
         JOIN fts_results fr ON m.id = fr.id
where (:withdeleted != 'y' AND (deleted != 'y' OR deleted IS NULL))
   OR (:withdeleted = 'y' AND deleted = 'y')

ORDER BY m.created DESC, m.changed DESC
LIMIT :limit OFFSET :offset
