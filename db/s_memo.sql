SELECT id,
       type,
       aktiv,
       kat,
       name,
       title,
       keywords,
       status,
       created,
       changed,
       deleted,
       contenttyp,
       content,
       (select group_concat(mm.memo2_id, ',') as ids from main.memos_memos mm where m1.id=mm.memo1_id group by mm.memo1_id) as 'memoids'
        ,createShortContent(content) as shortcontent
FROM main.memos m1
WHERE
    (:withdeleted != 'y' AND id = :id AND (deleted != 'y' OR deleted IS NULL))
    OR (:withdeleted = 'y' AND id = :id AND (deleted = 'y'))


limit 1
