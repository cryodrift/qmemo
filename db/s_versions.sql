SELECT id,
       memos_id,
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
       createShortContent(content) as shortcontent
FROM memos_versions
where memos_id = :id
order by CASE
             WHEN changed IS NOT NULL THEN changed
             ELSE created
             END DESC
LIMIT :limit OFFSET :offset
