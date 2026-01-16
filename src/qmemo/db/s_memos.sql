SELECT id,
       type,
       aktiv,
       kat,
       name,
       title,
       status,
       keywords,
       created,
       changed,
       deleted,
       contenttyp,
       (select group_concat(mm.memo2_id, ',') as ids from main.memos_memos mm where m1.id=mm.memo1_id group by mm.memo1_id) as 'memoids'
    ,   createShortContent(content) as shortcontent
FROM main.memos m1
where (:withdeleted != 'y' AND (deleted != 'y' OR deleted IS NULL))
   OR (:withdeleted = 'y' AND deleted = 'y')

order by CASE
             WHEN changed IS NOT NULL THEN changed
             ELSE created
             END DESC
LIMIT :limit OFFSET :offset
