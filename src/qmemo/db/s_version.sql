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
       content
FROM memos_versions
where id = :id
